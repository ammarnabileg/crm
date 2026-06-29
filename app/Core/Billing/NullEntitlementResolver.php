<?php

declare(strict_types=1);

namespace HaHireAI\Core\Billing;

use HaHireAI\Core\Contracts\EntitlementResolver;

/**
 * The default resolver: gates nothing and treats every workspace as usable.
 * Active when the Billing module is disabled or a workspace has not subscribed.
 */
final class NullEntitlementResolver implements EntitlementResolver
{
    public function gateFeatures(string $workspaceId): ?array
    {
        return null;
    }

    public function isUsable(string $workspaceId): bool
    {
        return true;
    }
}
