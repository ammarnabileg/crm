<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Contracts;

/**
 * A hosted-checkout payment provider used to fund the wallet (top-up). Unlike the
 * direct-charge PaymentGateway, money is collected on the provider's own page
 * (iframe) and confirmed asynchronously via a signed webhook
 * (docs/WALLET_AND_BILLING.md §9). Fawaterak is the built-in implementation; the
 * platform never embeds a provider SDK in a business module (Constitution §4).
 */
interface HostedCheckoutGateway
{
    public function key(): string;

    /** True when real credentials are configured; false → offline simulate mode. */
    public function enabled(): bool;

    /**
     * Create a top-up session for $amountCents and return where to pay.
     *
     * @param  array<string,scalar|null>  $meta
     * @return array{invoice_id:string, iframe_url:string}
     */
    public function createTopUpSession(string $workspaceId, int $amountCents, string $currency, array $meta = []): array;

    /**
     * Verify an inbound webhook's signature against the provider HASH key.
     *
     * @param  array<string,mixed>  $payload
     */
    public function verifySignature(array $payload, string $signature): bool;

    /**
     * Extract the provider invoice id and paid amount (cents) from a webhook
     * payload, plus whether it represents a successful payment.
     *
     * @param  array<string,mixed>  $payload
     * @return array{invoice_id:string, amount_cents:int, paid:bool}
     */
    public function parseWebhook(array $payload): array;
}
