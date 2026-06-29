<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Infrastructure;

use HaHireAI\Modules\Billing\Contracts\HostedCheckoutGateway;
use HaHireAI\Shared\Ulid;

/**
 * Fawaterak hosted-checkout gateway for wallet top-ups (docs/WALLET_AND_BILLING.md
 * §9). The platform is the merchant; credentials are platform-level env secrets.
 *
 * When credentials are absent (`enabled() === false`) the gateway returns an
 * **offline simulate** session — an internal confirm URL — so the platform is
 * fully functional without the live PSP (the billing analogue of the AI Echo
 * provider). No business module ever touches the provider directly: callers use
 * the HostedCheckoutGateway contract only.
 */
final class FawaterakGateway implements HostedCheckoutGateway
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config = [])
    {
    }

    public function key(): string
    {
        return 'fawaterak';
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && (string) ($this->config['api_key'] ?? '') !== '';
    }

    public function createTopUpSession(string $workspaceId, int $amountCents, string $currency, array $meta = []): array
    {
        // Offline / not configured → simulate: pay on an internal confirm page.
        if (! $this->enabled()) {
            $ref = 'sim_' . strtolower(Ulid::generate());
            $paymentId = (string) ($meta['payment_id'] ?? $ref);

            return ['invoice_id' => $ref, 'iframe_url' => '/billing/topup/simulate/' . $paymentId];
        }

        return $this->createRemoteInvoice($workspaceId, $amountCents, $currency, $meta);
    }

    public function verifySignature(array $payload, string $signature): bool
    {
        $secret = (string) ($this->config['webhook_hash'] ?? '');
        if ($secret === '' || $signature === '') {
            // Secure default: without a configured HASH key we cannot trust a webhook.
            return false;
        }

        $parsed = $this->parseWebhook($payload);
        $expected = hash_hmac('sha256', $parsed['invoice_id'] . '|' . $parsed['amount_cents'], $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(array $payload): array
    {
        $invoiceId = (string) ($payload['invoice_id']
            ?? $payload['invoiceId']
            ?? $payload['invoice_key']
            ?? $payload['referenceId']
            ?? '');

        // Fawaterak reports amounts in major units; accept cents directly if given.
        $amountCents = isset($payload['amount_cents'])
            ? (int) $payload['amount_cents']
            : (int) round(((float) ($payload['amount'] ?? $payload['paid_amount'] ?? 0)) * 100);

        $status = strtolower((string) ($payload['payment_status'] ?? $payload['status'] ?? ''));
        $paid = in_array($status, ['paid', 'success', 'successful', 'completed'], true);

        return ['invoice_id' => $invoiceId, 'amount_cents' => $amountCents, 'paid' => $paid];
    }

    /**
     * Create a Fawaterak invoice and return its hosted-payment URL. Best-effort
     * over cURL; on any failure we throw so the caller can surface "try again".
     *
     * @param  array<string,scalar|null>  $meta
     * @return array{invoice_id:string, iframe_url:string}
     */
    private function createRemoteInvoice(string $workspaceId, int $amountCents, string $currency, array $meta): array
    {
        $base = rtrim((string) ($this->config['base_url'] ?? 'https://app.fawaterk.com'), '/');
        $body = json_encode([
            'cartTotal' => number_format($amountCents / 100, 2, '.', ''),
            'currency' => $currency,
            'cartItems' => [[
                'name' => 'Wallet top-up',
                'price' => number_format($amountCents / 100, 2, '.', ''),
                'quantity' => 1,
            ]],
            'payLoad' => ['workspace_id' => $workspaceId, 'payment_id' => (string) ($meta['payment_id'] ?? '')],
        ], JSON_THROW_ON_ERROR);

        $response = $this->post($base . '/api/v2/createInvoiceLink', $body);
        $data = json_decode($response, true);
        $url = $data['data']['url'] ?? ($data['url'] ?? null);
        $invoiceId = (string) ($data['data']['invoiceId'] ?? $data['data']['invoiceKey'] ?? $data['invoiceId'] ?? '');

        if (! is_string($url) || $url === '' || $invoiceId === '') {
            throw new \RuntimeException('Fawaterak did not return a payment URL.');
        }

        return ['invoice_id' => $invoiceId, 'iframe_url' => $url];
    }

    private function post(string $url, string $body): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => (int) ($this->config['timeout'] ?? 15),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . (string) ($this->config['api_key'] ?? ''),
            ],
        ]);
        $out = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($out === false || $out === '') {
            throw new \RuntimeException('Fawaterak request failed: ' . $err);
        }

        return (string) $out;
    }
}
