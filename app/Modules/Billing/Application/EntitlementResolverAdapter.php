<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Contracts\EntitlementResolver;

/**
 * Bridges the Core EntitlementResolver contract to the Billing module's
 * Entitlements service, so decoupled layers (e.g. the sidebar) consume plan
 * entitlements without depending on Billing internals (ARCHITECTURE.md §4).
 */
final class EntitlementResolverAdapter implements EntitlementResolver
{
    public function __construct(private readonly Entitlements $entitlements)
    {
    }

    public function gateFeatures(string $workspaceId): ?array
    {
        return $this->entitlements->gateFeatures($workspaceId);
    }

    public function isUsable(string $workspaceId): bool
    {
        return $this->entitlements->isUsable($workspaceId);
    }
}
