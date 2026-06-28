<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

/**
 * Licensing: answers what a workspace is entitled to from its active plan —
 * feature flags and numeric limits (docs/BILLING_PLATFORM.md §6).
 *
 * Permissive when no subscription exists (pre-billing workspaces are
 * un-gated): features are not enforced and limits are unlimited until a
 * workspace actually subscribes. A limit of -1 always means unlimited.
 */
final class Entitlements
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /** Plan feature flags for the workspace, or [] when on no/feature-less plan. */
    public function features(string $workspaceId): array
    {
        $plan = $this->plan($workspaceId);

        return $plan !== null ? (array) ($plan['features'] ?? []) : [];
    }

    /**
     * Plan features for sidebar gating, or null to mean "do not gate" (no
     * subscription yet). This keeps pre-billing workspaces behaving as before.
     *
     * @return list<string>|null
     */
    public function gateFeatures(string $workspaceId): ?array
    {
        if ($this->subscriptions->find($workspaceId) === null) {
            return null;
        }

        return array_values(array_map('strval', $this->features($workspaceId)));
    }

    public function allows(string $workspaceId, string $feature): bool
    {
        // No subscription → un-gated (permissive). With a subscription → must hold the feature.
        if ($this->subscriptions->find($workspaceId) === null) {
            return true;
        }

        return in_array($feature, $this->features($workspaceId), true);
    }

    /** The numeric limit for a key (-1 = unlimited; unlimited when no subscription). */
    public function limit(string $workspaceId, string $key): int
    {
        $plan = $this->plan($workspaceId);
        if ($plan === null) {
            return -1;
        }

        $limits = (array) ($plan['limits'] ?? []);

        return array_key_exists($key, $limits) ? (int) $limits[$key] : -1;
    }

    /** True if adding one more (given $currentCount existing) stays within the limit. */
    public function within(string $workspaceId, string $key, int $currentCount): bool
    {
        $limit = $this->limit($workspaceId, $key);

        return $limit === -1 || $currentCount < $limit;
    }

    public function status(string $workspaceId): ?string
    {
        $sub = $this->subscriptions->find($workspaceId);

        return $sub !== null ? (string) $sub['status'] : null;
    }

    /** Whether the workspace may operate. Suspended/canceled subscriptions cannot. */
    public function isUsable(string $workspaceId): bool
    {
        $status = $this->status($workspaceId);

        return $status === null || ! in_array($status, ['suspended', 'canceled'], true);
    }

    /** @return array<string,mixed>|null the decoded active plan */
    private function plan(string $workspaceId): ?array
    {
        $sub = $this->subscriptions->findWithPlan($workspaceId);

        return $sub !== null && isset($sub['plan']) && is_array($sub['plan']) ? $sub['plan'] : null;
    }
}
