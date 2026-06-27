<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * OPTIONAL Stripe adapter — INERT without keys (ABSOLUTE RULE 2). No secret is
 * ever stored in the database or logged; keys come only from config/env. Every
 * online capability sits behind isConfigured(), and webhook verification degrades
 * to "accepted but unverified" when no signing secret is set, so the billing
 * module is fully usable with zero Stripe configuration.
 *
 * No network call is made unless a real checkout session is requested AND a key is
 * present; tests never trigger that path.
 */
final class StripeGateway
{
    /** Stripe API secret (sk_...). Empty string when unconfigured. */
    private string $secret;

    /** Webhook signing secret (whsec_...). Empty string when unconfigured. */
    private string $webhookSecret;

    public function __construct(?string $secret = null, ?string $webhookSecret = null)
    {
        $this->secret = $secret ?? (string) config('services.stripe.secret', env('STRIPE_SECRET', ''));
        $this->webhookSecret = $webhookSecret ?? (string) config('services.stripe.webhook_secret', env('STRIPE_WEBHOOK_SECRET', ''));
    }

    /**
     * Whether an API secret is present. Online checkout is only ever offered when
     * this is true; otherwise the in-app manual path is the only flow.
     */
    public function isConfigured(): bool
    {
        return $this->secret !== '';
    }

    /**
     * Whether a webhook signing secret is configured. When false the webhook is
     * inert-but-safe: events are accepted and logged unverified (see the controller).
     */
    public function webhookSigningConfigured(): bool
    {
        return $this->webhookSecret !== '';
    }

    /**
     * Verify a Stripe webhook signature (HMAC-SHA256 over "t.payload", per Stripe's
     * `Stripe-Signature` scheme: "t=<ts>,v1=<sig>,..."). Returns false when no
     * signing secret is configured (the caller treats that as accept-but-unverified)
     * or when the signature is absent/invalid. Never throws and makes no network call.
     */
    public function verifyWebhook(string $payload, string $sigHeader): bool
    {
        if ($this->webhookSecret === '' || $sigHeader === '') {
            return false;
        }

        $parts = $this->parseSignatureHeader($sigHeader);
        $timestamp = $parts['t'] ?? '';
        $signatures = $parts['v1'] ?? [];
        if ($timestamp === '' || $signatures === []) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $this->webhookSecret);

        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse "t=...,v1=...,v1=..." into a timestamp and the list of v1 signatures.
     *
     * @return array{t?:string,v1?:string[]}
     */
    private function parseSignatureHeader(string $header): array
    {
        $result = [];
        foreach (explode(',', $header) as $segment) {
            $pair = explode('=', trim($segment), 2);
            if (count($pair) !== 2) {
                continue;
            }
            [$key, $value] = $pair;
            if ($key === 't') {
                $result['t'] = $value;
            } elseif ($key === 'v1') {
                $result['v1'][] = $value;
            }
        }

        return $result;
    }

    /**
     * Create a hosted checkout session and return its redirect URL, or null when
     * online checkout is unavailable (no key, or cURL absent/failed). This is the
     * ONLY method that may touch the network, and only when isConfigured() — callers
     * always have the in-app manual path to fall back on, so a null here is benign.
     */
    public function createCheckoutSession(array $params): ?string
    {
        if (! $this->isConfigured() || ! function_exists('curl_init')) {
            return null;
        }

        try {
            $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->secret],
                CURLOPT_POSTFIELDS     => http_build_query($params),
                CURLOPT_TIMEOUT        => 15,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $status < 200 || $status >= 300) {
                return null;
            }

            $decoded = json_decode((string) $body, true);

            return is_array($decoded) && isset($decoded['url']) ? (string) $decoded['url'] : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
