<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\CheckoutGateway;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * In-app subscription management (docs Billing). The manual/in-app path always
 * works with zero external keys: subscribing creates or updates the workspace's
 * single subscriptions row directly, no gateway round-trip. Online checkout is an
 * OPTIONAL enhancement layered on top (see StripeGateway) and never required.
 *
 * Pure domain: this service holds no secrets. The checkout gateway is injected
 * (StripeGateway in production, a fake in tests) and is INERT without keys, so
 * checkoutUrl() returns null and the caller uses the manual path. Everything is
 * scoped to the active workspace via the tenant-scoped Subscription model; Plan
 * is the shared (non-tenant) catalogue.
 */
final class BillingService
{
    public function __construct(private readonly CheckoutGateway $gateway = new StripeGateway())
    {
    }

    /**
     * This workspace's current subscription: the latest non-deleted row. The
     * Subscription model is tenant-scoped, so the query is already constrained to
     * the active workspace; we only add the soft-delete filter (the model does not
     * declare softDeletes, so deleted_at is filtered here explicitly).
     */
    public function currentSubscription(): ?Subscription
    {
        $row = Subscription::query()
            ->whereNull('deleted_at')
            ->orderBy('id', 'desc')
            ->first();

        return $row !== null ? Subscription::hydrate($row) : null;
    }

    /**
     * Public, active plans for the pricing/upgrade screen, cheapest first.
     *
     * @return Plan[]
     */
    public function availablePlans(): array
    {
        return array_map(
            [Plan::class, 'hydrate'],
            Plan::query()
                ->where('is_active', '=', 1)
                ->where('is_public', '=', 1)
                ->whereNull('deleted_at')
                ->orderBy('sort_order', 'asc')
                ->orderBy('price', 'asc')
                ->get()
        );
    }

    /**
     * This workspace's invoices, newest first, each with a resolved status key for
     * display (joined from invoice_statuses). Returns plain rows (not models) so
     * the view can read the joined status_key directly.
     *
     * @return array<int,array<string,mixed>>
     */
    public function invoices(int $limit = 20): array
    {
        return app('db')->table('invoices')
            ->leftJoin('invoice_statuses', 'invoice_statuses.id', '=', 'invoices.invoice_status_id')
            ->where('invoices.workspace_id', '=', (int) tenant()->id())
            ->whereNull('invoices.deleted_at')
            ->orderBy('invoices.id', 'desc')
            ->limit(max(1, $limit))
            ->select(
                'invoices.id',
                'invoices.number',
                'invoices.total_amount',
                'invoices.amount_due',
                'invoices.issued_at',
                'invoices.invoice_status_id',
                'invoice_statuses.key as status_key',
                'invoice_statuses.label as status_label'
            )
            ->get();
    }

    /**
     * Start or change this workspace's subscription to $planId WITHOUT any external
     * gateway — the manual/in-app path that always works. The plan must be active
     * AND public. Trialing when the plan defines trial days and has a price; an
     * immediate active subscription otherwise (including free plans).
     */
    public function subscribe(int $planId): Subscription
    {
        $plan = Plan::find($planId);
        abort_unless(
            $plan !== null
                && (bool) $plan->getAttribute('is_active')
                && (bool) $plan->getAttribute('is_public'),
            404,
            'Plan is not available.'
        );

        $price = (float) $plan->getAttribute('price');
        $trialDays = (int) $plan->getAttribute('trial_days');
        $onTrial = $price > 0 && $trialDays > 0;

        $statusKey = $onTrial ? 'trialing' : 'active';
        $statusId = (int) status_id('subscription_statuses', $statusKey);

        $attributes = [
            'plan_id'                => (int) $plan->getKey(),
            'subscription_status_id' => $statusId,
            'amount'                 => $price,
            'currency'               => (string) ($plan->getAttribute('currency') ?? 'SAR'),
            'starts_at'              => now(),
            'trial_ends_at'          => $onTrial ? date('Y-m-d H:i:s', strtotime('+' . $trialDays . ' days')) : null,
            'ends_at'                => null,
            'canceled_at'            => null,
        ];

        // One subscription row per workspace: update the existing one in place
        // (a plan switch), otherwise create it. Tenant scope is enforced by the model.
        $current = $this->currentSubscription();
        if ($current !== null) {
            $current->update($attributes);

            return $current;
        }

        return Subscription::create($attributes);
    }

    /**
     * Hosted-checkout URL for a PAID plan when a gateway is configured, else null.
     * Returns null (→ caller uses the manual path) when: no gateway is configured,
     * the plan is missing/unavailable, the plan is free (no payment needed), or the
     * gateway could not create a session. Never throws — degrade, don't break.
     *
     * NOTE: the session params below are a sensible starting point; the exact
     * Stripe price/mode mapping depends on the merchant's product setup and only
     * takes effect once a real key is configured (it is inert/untested offline).
     */
    public function checkoutUrl(int $planId, string $successUrl, string $cancelUrl): ?string
    {
        if (! $this->gateway->isConfigured()) {
            return null;
        }

        $plan = Plan::find($planId);
        if ($plan === null
            || ! (bool) $plan->getAttribute('is_active')
            || ! (bool) $plan->getAttribute('is_public')) {
            return null;
        }

        $price = (float) $plan->getAttribute('price');
        if ($price <= 0) {
            return null; // free plans never need a checkout round-trip
        }

        return $this->gateway->createCheckoutSession([
            'mode'                => 'payment',
            'success_url'         => $successUrl,
            'cancel_url'          => $cancelUrl,
            'client_reference_id' => (string) tenant()->id(),
            'metadata[plan_id]'   => (string) $plan->getKey(),
            'line_items[0][quantity]'                          => '1',
            'line_items[0][price_data][currency]'              => strtolower((string) ($plan->getAttribute('currency') ?? 'sar')),
            'line_items[0][price_data][unit_amount]'           => (string) (int) round($price * 100),
            'line_items[0][price_data][product_data][name]'    => (string) $plan->getAttribute('name'),
        ]);
    }

    /**
     * Whether an online payment gateway is configured. Used only to decide whether
     * to OFFER online checkout — the manual path works regardless. Must never throw:
     * a missing settings tenant or absent keys simply means "not configured".
     */
    public function gatewayConfigured(): bool
    {
        try {
            $gateway = (string) settings()->get('billing.gateway', '');
            if ($gateway !== '' && $gateway !== 'manual') {
                return true;
            }
        } catch (\Throwable) {
            // No tenant/settings available — fall through to the key check.
        }

        return (new StripeGateway())->isConfigured();
    }
}
