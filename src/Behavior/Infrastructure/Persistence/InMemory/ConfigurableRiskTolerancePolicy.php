<?php

declare(strict_types=1);

namespace Nizam\Behavior\Infrastructure\Persistence\InMemory;

use Nizam\Behavior\Domain\RoleId;
use Nizam\Behavior\Domain\Service\RiskTolerancePolicy;
use Nizam\Kernel\Domain\TenantId;

/**
 * A configurable, in-memory {@see RiskTolerancePolicy} — allowances are granted explicitly.
 *
 * Elevated risk tolerance is privileged and must be authorized per tenant and role. This adapter
 * holds the set of (tenant, role) pairs that have been granted an allowance and answers
 * {@see self::allowsElevatedRisk()} against it. It defaults to denying elevation — the safe default —
 * so a role only ever adopts elevated risk once governance has explicitly permitted it via
 * {@see self::allow()}. A production deployment can swap this for an adapter backed by the platform's
 * policy/authorization service without any change to the domain, which depends only on the port.
 */
final class ConfigurableRiskTolerancePolicy implements RiskTolerancePolicy
{
    /**
     * @var array<string, true> Granted allowances keyed by "tenantId:roleId".
     */
    private array $allowances = [];

    /**
     * Grant a role within a tenant permission to adopt elevated risk tolerance.
     */
    public function allow(TenantId $tenantId, RoleId $roleId): void
    {
        $this->allowances[$this->key($tenantId, $roleId)] = true;
    }

    /**
     * Revoke a previously granted elevated-risk allowance.
     */
    public function revoke(TenantId $tenantId, RoleId $roleId): void
    {
        unset($this->allowances[$this->key($tenantId, $roleId)]);
    }

    /**
     * Whether the given role within the given tenant may adopt elevated risk tolerance.
     */
    public function allowsElevatedRisk(TenantId $tenantId, RoleId $roleId): bool
    {
        return isset($this->allowances[$this->key($tenantId, $roleId)]);
    }

    /**
     * The stable map key for a (tenant, role) pair.
     */
    private function key(TenantId $tenantId, RoleId $roleId): string
    {
        return $tenantId->toString() . ':' . $roleId->toString();
    }
}
