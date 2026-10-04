<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Integration\Contracts\HttpClient;
use HaHireAI\Shared\Ulid;
use Throwable;

/**
 * Delivers domain events to subscribed webhook endpoints as signed HTTP POSTs,
 * recording every attempt. Like the Workflow Engine, it is a reactor on the
 * event bus — actor modules never call it directly. Synchronous now,
 * queue-ready (docs/INTEGRATION_PLATFORM.md §5).
 */
final class WebhookDispatcher
{
    private const SIGNATURE_HEADER = 'X-HaHireAI-Signature';

    public function __construct(
        private readonly Connection $connection,
        private readonly WebhookService $webhooks,
        private readonly HttpClient $http,
    ) {
    }

    /**
     * Deliver an event to every subscribed endpoint in the workspace.
     *
     * @param  array<string, mixed>  $payload
     * @return int number of endpoints delivered to (attempted)
     */
    public function dispatch(string $workspaceId, string $event, array $payload): int
    {
        $endpoints = $this->webhooks->findEnabledForEvent($workspaceId, $event);
        $count = 0;

        foreach ($endpoints as $endpoint) {
            $this->deliver($workspaceId, $event, $payload, $endpoint);
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $endpoint
     */
    private function deliver(string $workspaceId, string $event, array $payload, array $endpoint): void
    {
        $body = (string) json_encode([
            'event' => $event,
            'workspace_id' => $workspaceId,
            'delivered_at' => gmdate('c'),
            'data' => $payload,
        ], JSON_UNESCAPED_SLASHES);

        $signature = 'sha256=' . hash_hmac('sha256', $body, (string) $endpoint['secret']);
        $deliveryId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');

        try {
            $response = $this->http->post((string) $endpoint['url'], $body, [
                'Content-Type' => 'application/json',
                self::SIGNATURE_HEADER => $signature,
                'X-HaHireAI-Event' => $event,
                'X-HaHireAI-Delivery' => $deliveryId,
            ]);

            $ok = $response->successful();
            $this->record($deliveryId, $workspaceId, (string) $endpoint['id'], $event, $payload, $signature, $ok ? 'delivered' : 'failed', $response->status, $response->error, $now);
            $this->touchEndpoint((string) $endpoint['id'], $ok, $now);
        } catch (Throwable $e) {
            $this->record($deliveryId, $workspaceId, (string) $endpoint['id'], $event, $payload, $signature, 'failed', null, $e->getMessage(), $now);
            $this->touchEndpoint((string) $endpoint['id'], false, $now);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(string $id, string $workspaceId, string $endpointId, string $event, array $payload, string $signature, string $status, ?int $responseStatus, ?string $error, string $now): void
    {
        $this->connection->statement(
            'INSERT INTO webhook_deliveries (id, workspace_id, endpoint_id, event, payload, status, response_status, error, attempts, signature, delivered_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $workspaceId, $endpointId, $event, json_encode($payload), $status, $responseStatus,
                $error !== null ? mb_substr($error, 0, 1000) : null, 1, $signature,
                $status === 'delivered' ? $now : null, $now,
            ],
        );
    }

    private function touchEndpoint(string $endpointId, bool $ok, string $now): void
    {
        if ($ok) {
            $this->connection->statement(
                'UPDATE webhook_endpoints SET last_delivered_at = ?, failure_count = 0 WHERE id = ?',
                [$now, $endpointId],
            );

            return;
        }

        $this->connection->statement(
            'UPDATE webhook_endpoints SET failure_count = failure_count + 1 WHERE id = ?',
            [$endpointId],
        );
    }
}
