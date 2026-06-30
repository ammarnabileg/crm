<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Contracts\EntitlementResolver;

/**
 * Bridges the Core EntitlementResolver contract to the Billing module's
 * Entitlements service, so decoupled layers (e.g. the sidebar, the workspace
 * shell) consume plan entitlements without depending on Billing internals
 * (ARCHITECTURE.md §4).
 */
final class EntitlementResolverAdapter implements EntitlementResolver
{
    public function __construct(
        private readonly Entitlements $entitlements,
        private readonly WorkspacePlanService $plans,
    ) {
    }

    public function gateFeatures(string $workspaceId): ?array
    {
        // A composed wallet plan defines exactly which features are enabled.
        if ($this->plans->hasPlan($workspaceId)) {
            return $this->plans->activeFeatureKeys($workspaceId);
        }

        // Otherwise fall back to the legacy subscription behaviour (or permissive).
        return $this->entitlements->gateFeatures($workspaceId);
    }

    public function isUsable(string $workspaceId): bool
    {
        return $this->entitlements->isUsable($workspaceId);
    }

    public function isLocked(string $workspaceId): bool
    {
        return $this->plans->isLocked($workspaceId);
    }
}
