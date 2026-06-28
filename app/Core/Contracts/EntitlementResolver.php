<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * Resolves a workspace's plan entitlements for layers that must stay decoupled
 * from the Billing module (e.g. the sidebar). Billing binds the real
 * implementation; Core binds a permissive null default so the platform works
 * with Billing disabled. See docs/BILLING_PLATFORM.md §6, ARCHITECTURE.md §4.
 */
interface EntitlementResolver
{
    /**
     * Feature flags to gate navigation by, or null to mean "do not gate"
     * (no subscription / billing disabled).
     *
     * @return list<string>|null
     */
    public function gateFeatures(string $workspaceId): ?array;

    /** Whether the workspace may operate (false when suspended/canceled). */
    public function isUsable(string $workspaceId): bool;
}
