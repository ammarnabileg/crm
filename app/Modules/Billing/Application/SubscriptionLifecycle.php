<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

/**
 * Advances time-based subscription transitions. Driven by a scheduled tick
 * (ops/cron); `$nowTs` is injectable so it is fully testable. Transitions
 * (docs/BILLING_PLATFORM.md §3):
 *   trialing  →(trial ends)→ active | past_due
 *   active    →(period ends, cancel_at_period_end)→ canceled
 *   active    →(period ends)→ active(renewed) | past_due
 *   past_due  →(grace ends)→ suspended
 */
final class SubscriptionLifecycle
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PlanService $plans,
        private readonly BillingService $billing,
    ) {
    }

    /**
     * Process every subscription whose timer has elapsed.
     *
     * @return array{processed: int, transitions: list<array{workspace_id: string, from: string, to: string}>}
     */
    public function tick(?int $nowTs = null): array
    {
        $nowTs ??= time();
        $nowSql = gmdate('Y-m-d H:i:s', $nowTs);
        $transitions = [];

        foreach ($this->subscriptions->dueForLifecycle($nowSql) as $sub) {
            $from = (string) $sub['status'];
            $to = $this->advance($sub, $nowTs);

            if ($to !== $from) {
                $transitions[] = ['workspace_id' => (string) $sub['workspace_id'], 'from' => $from, 'to' => $to];
            }
        }

        return ['processed' => count($transitions), 'transitions' => $transitions];
    }

    /**
     * @param  array<string,mixed>  $sub
     * @return string the resulting status
     */
    private function advance(array $sub, int $nowTs): string
    {
        $workspaceId = (string) $sub['workspace_id'];
        $subscriptionId = (string) $sub['id'];
        $status = (string) $sub['status'];

        // Past-due grace elapsed → suspend.
        if ($status === 'past_due') {
            $this->subscriptions->update($subscriptionId, ['status' => 'suspended']);

            return 'suspended';
        }

        // Active period ended with a pending cancellation → cancel.
        if ($status === 'active' && (int) ($sub['cancel_at_period_end'] ?? 0) === 1) {
            $this->subscriptions->update($subscriptionId, [
                'status' => 'canceled',
                'canceled_at' => gmdate('Y-m-d H:i:s', $nowTs),
            ]);

            return 'canceled';
        }

        // Trial ended, or active period ended → (re)activate & charge.
        $plan = $this->plans->find((string) $sub['plan_id']);
        if ($plan === null) {
            return $status;
        }

        return $this->billing->activatePaidPeriod($workspaceId, $subscriptionId, $plan, $nowTs)
            ? 'active'
            : 'past_due';
    }
}
