<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Service;

use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\TenantId;

/**
 * The policy port that decides whether a role may adopt elevated risk tolerance.
 *
 * Elevated risk is privileged: {@see \Nizam\Behavior\Domain\ValueObject\BehaviorTraits} only permits
 * it when a policy allowance is asserted. This port lets the domain consult tenant/role governance
 * for that allowance without knowing how the decision is made; a concrete implementation lives in
 * Infrastructure (or is configured per deployment).
 */
interface RiskTolerancePolicy
{
    /**
     * Whether the given role within the given tenant is permitted to adopt elevated risk tolerance.
     */
    public function allowsElevatedRisk(TenantId $tenantId, RoleId $roleId): bool;
}
