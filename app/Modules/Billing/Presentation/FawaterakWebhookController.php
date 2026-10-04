<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Modules\Billing\Application\TopUpService;

/**
 * Public Fawaterak webhook endpoint (docs/WALLET_AND_BILLING.md §9). No session /
 * CSRF — trust is established by the provider HASH-key signature, verified inside
 * TopUpService. Crediting is idempotent, so provider retries are safe.
 */
final class FawaterakWebhookController
{
    public function __construct(private readonly TopUpService $topup)
    {
    }

    public function paid(Request $request): Response
    {
        $payload = $this->payload($request);
        $signature = (string) ($request->header('x-fawaterak-signature')
            ?? ($payload['hashKey'] ?? $payload['signature'] ?? ''));

        $credited = $this->topup->handleWebhook($payload, $signature);

        // Always 200 so the provider stops retrying a processed/duplicate event;
        // the body reflects whether a credit was applied.
        return Response::json(['ok' => true, 'credited' => $credited]);
    }

    /** @return array<string,mixed> */
    private function payload(Request $request): array
    {
        $body = $request->all();
        if ($body !== []) {
            return $body;
        }

        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
