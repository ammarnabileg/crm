<?php

declare(strict_types=1);

namespace App\Contracts\Billing;

/**
 * A hosted-checkout gateway. Splitting this out (implemented by StripeGateway in
 * production, faked in tests) lets BillingService decide online-vs-manual WITHOUT a
 * network call in tests. Implementations are OPTIONAL/inert without keys:
 * isConfigured() is false when no secret is set, and createCheckoutSession() returns
 * null on any failure so the caller always falls back to the in-app manual path.
 */
interface CheckoutGateway
{
    /** Whether an API secret is configured (online checkout may be offered). */
    public function isConfigured(): bool;

    /**
     * Create a hosted checkout session and return its redirect URL, or null when
     * unavailable (no key, transport missing, or the gateway rejected the request).
     *
     * @param array<string,mixed> $params
     */
    public function createCheckoutSession(array $params): ?string;
}
