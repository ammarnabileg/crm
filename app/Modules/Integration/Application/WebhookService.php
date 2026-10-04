<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Manages outbound webhook endpoints (event subscriptions) as workspace data.
 * The signing secret is generated server-side and used to HMAC every delivery
 * (docs/INTEGRATION_PLATFORM.md §5).
 */
final class WebhookService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param  list<string>  $events
     * @return array{id: string, secret: string}
     */
    public function createEndpoint(string $workspaceId, string $url, array $events, ?string $createdBy = null, ?string $description = null): array
    {
        $id = Ulid::generate();
        $secret = bin2hex(random_bytes(32));
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->statement(
            'INSERT INTO webhook_endpoints (id, workspace_id, url, secret, events, description, enabled, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $url, $secret, json_encode(array_values($events)), $description, 1, $createdBy, $now, $now],
        );

        return ['id' => $id, 'secret' => $secret];
    }

    /** @return list<array<string, mixed>> with decoded `events` */
    public function listForWorkspace(string $workspaceId): array
    {
        $rows = $this->connection->select(
            'SELECT id, workspace_id, url, events, description, enabled, failure_count, last_delivered_at, created_at
               FROM webhook_endpoints WHERE workspace_id = ? AND deleted_at IS NULL ORDER BY created_at DESC',
            [$workspaceId],
        );

        foreach ($rows as &$row) {
            $row['events'] = $this->decodeEvents($row['events'] ?? null);
        }

        return $rows;
    }

    /**
     * Enabled endpoints in a workspace subscribed to a given event.
     *
     * @return list<array<string, mixed>>
     */
    public function findEnabledForEvent(string $workspaceId, string $event): array
    {
        $rows = $this->connection->select(
            'SELECT * FROM webhook_endpoints WHERE workspace_id = ? AND enabled = 1 AND deleted_at IS NULL',
            [$workspaceId],
        );

        $matched = [];
        foreach ($rows as $row) {
            if (in_array($event, $this->decodeEvents($row['events'] ?? null), true)) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    public function setEnabled(string $workspaceId, string $endpointId, bool $enabled): void
    {
        $this->connection->statement(
            'UPDATE webhook_endpoints SET enabled = ?, updated_at = ? WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$enabled ? 1 : 0, gmdate('Y-m-d H:i:s'), $endpointId, $workspaceId],
        );
    }

    public function delete(string $workspaceId, string $endpointId): void
    {
        $this->connection->statement(
            'UPDATE webhook_endpoints SET deleted_at = ?, enabled = 0 WHERE id = ? AND workspace_id = ?',
            [gmdate('Y-m-d H:i:s'), $endpointId, $workspaceId],
        );
    }

    /** @return list<array<string, mixed>> */
    public function recentDeliveries(string $workspaceId, int $limit = 25): array
    {
        $limit = max(1, min(200, $limit));

        return $this->connection->select(
            'SELECT id, endpoint_id, event, status, response_status, attempts, created_at
               FROM webhook_deliveries WHERE workspace_id = ? ORDER BY created_at DESC LIMIT ' . $limit,
            [$workspaceId],
        );
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private function decodeEvents(mixed $raw): array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? array_values(array_map(static fn ($e): string => (string) $e, $decoded)) : [];
    }
}
