<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Modules\Billing\Contracts\PaymentGateway;
use HaHireAI\Modules\Billing\Application\Exceptions\BillingException;

/**
 * Orchestrates the subscription lifecycle: starting trials, charging through the
 * PaymentGateway, issuing invoices, changing plans, and cancelling. All money
 * movement goes through the gateway contract, never a provider SDK
 * (docs/BILLING_PLATFORM.md §3–§5).
 */
final class BillingService
{
    /** Days a past-due subscription stays usable before suspension. */
    public const GRACE_DAYS = 7;

    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PlanService $plans,
        private readonly InvoiceService $invoices,
        private readonly PaymentGateway $gateway,
    ) {
    }

    /**
     * Subscribe a workspace to a plan. Starts a trial when the plan offers one
     * and the workspace has never subscribed; otherwise activates immediately
     * (charging paid plans). Returns the subscription id.
     */
    public function subscribe(string $workspaceId, string $planId, ?int $nowTs = null): string
    {
        $nowTs ??= time();
        $plan = $this->requirePlan($planId);
        $existing = $this->subscriptions->find($workspaceId);

        if ((int) $plan['trial_days'] > 0 && $existing === null) {
            return $this->subscriptions->place($workspaceId, (string) $plan['id'], 'trialing', [
                'trial_ends_at' => $this->ts($nowTs, '+' . (int) $plan['trial_days'] . ' days'),
                'current_period_start' => $this->sql($nowTs),
                'current_period_end' => null,
                'grace_ends_at' => null,
                'cancel_at_period_end' => 0,
            ]);
        }

        $subscriptionId = $this->subscriptions->place($workspaceId, (string) $plan['id'], 'active');
        $this->activatePaidPeriod($workspaceId, $subscriptionId, $plan, $nowTs);

        return $subscriptionId;
    }

    /**
     * Charge (if needed) and open a fresh billing period. On a failed charge the
     * subscription becomes past_due with a grace deadline. Returns success.
     *
     * @param  array<string,mixed>  $plan
     */
    public function activatePaidPeriod(string $workspaceId, string $subscriptionId, array $plan, ?int $nowTs = null): bool
    {
        $nowTs ??= time();
        $periodStart = $this->sql($nowTs);
        $periodEnd = $this->periodEnd((string) $plan['interval'], $nowTs);
        $price = (int) $plan['price_cents'];
        $currency = (string) $plan['currency'];

        if ($price === 0) {
            $this->subscriptions->update($subscriptionId, [
                'status' => 'active',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'grace_ends_at' => null,
            ]);

            return true;
        }

        $result = $this->gateway->charge($price, $currency, 'Subscription — ' . (string) $plan['name'], [
            'workspace_id' => $workspaceId,
            'plan' => (string) $plan['code'],
        ]);

        if (! $result->success) {
            $this->subscriptions->update($subscriptionId, [
                'status' => 'past_due',
                'grace_ends_at' => $this->ts($nowTs, '+' . self::GRACE_DAYS . ' days'),
            ]);

            return false;
        }

        $invoiceId = $this->invoices->issue(
            $workspaceId,
            $subscriptionId,
            $price,
            $currency,
            $periodStart,
            $periodEnd,
            [['label' => (string) $plan['name'] . ' (' . (string) $plan['interval'] . ')', 'amount_cents' => $price]],
        );
        $this->invoices->markPaid($invoiceId, $result->reference);

        $this->subscriptions->update($subscriptionId, [
            'status' => 'active',
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'grace_ends_at' => null,
            'provider' => $this->gateway->key(),
            'provider_ref' => $result->reference,
        ]);

        return true;
    }

    /** Switch plans. A paid switch on an active subscription opens a new period. */
    public function changePlan(string $workspaceId, string $planId, ?int $nowTs = null): void
    {
        $nowTs ??= time();
        $plan = $this->requirePlan($planId);
        $sub = $this->subscriptions->find($workspaceId);
        if ($sub === null) {
            $this->subscribe($workspaceId, $planId, $nowTs);

            return;
        }

        $subscriptionId = (string) $sub['id'];
        $this->subscriptions->update($subscriptionId, ['plan_id' => (string) $plan['id'], 'cancel_at_period_end' => 0]);

        // Trials keep running on the new plan; otherwise (re)activate the period.
        if ((string) $sub['status'] !== 'trialing') {
            $this->activatePaidPeriod($workspaceId, $subscriptionId, $plan, $nowTs);
        }
    }

    /** Cancel — at period end by default, or immediately. */
    public function cancel(string $workspaceId, ?int $nowTs = null, bool $atPeriodEnd = true): void
    {
        $nowTs ??= time();
        $sub = $this->subscriptions->find($workspaceId);
        if ($sub === null) {
            throw new BillingException('No subscription to cancel.');
        }

        if ($atPeriodEnd && (string) $sub['status'] === 'active' && $sub['current_period_end'] !== null) {
            $this->subscriptions->update((string) $sub['id'], ['cancel_at_period_end' => 1]);

            return;
        }

        $this->subscriptions->update((string) $sub['id'], [
            'status' => 'canceled',
            'canceled_at' => $this->sql($nowTs),
            'cancel_at_period_end' => 0,
        ]);
    }

    /** @return array<string,mixed> */
    private function requirePlan(string $planId): array
    {
        $plan = $this->plans->find($planId) ?? $this->plans->findByCode($planId);
        if ($plan === null) {
            throw new BillingException("Unknown plan [{$planId}].");
        }

        return $plan;
    }

    private function periodEnd(string $interval, int $nowTs): ?string
    {
        return match ($interval) {
            'year' => $this->ts($nowTs, '+1 year'),
            'none' => null,
            default => $this->ts($nowTs, '+1 month'),
        };
    }

    private function ts(int $nowTs, string $modify): string
    {
        return gmdate('Y-m-d H:i:s', strtotime($modify, $nowTs) ?: $nowTs);
    }

    private function sql(int $nowTs): string
    {
        return gmdate('Y-m-d H:i:s', $nowTs);
    }
}
