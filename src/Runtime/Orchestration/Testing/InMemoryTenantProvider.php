<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Testing;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Orchestration\Port\TenantProvider;
use Nizam\Runtime\Orchestration\ValueObject\ResolvedTenant;

/**
 * A real, seedable in-memory {@see TenantProvider} for tests and safe defaults.
 *
 * It resolves tenants from an in-process map seeded through {@see self::seed()}; an unseeded tenant
 * resolves to null, letting the orchestrator reject it exactly as a missing production tenant would.
 * This is a working adapter, not a stub — it enforces the same active/inactive contract the production
 * directory does.
 */
final class InMemoryTenantProvider implements TenantProvider
{
    /** @var array<string, ResolvedTenant> */
    private array $tenants = [];

    /**
     * Seed a resolvable tenant.
     *
     * @param string $name   A human-readable tenant name.
     * @param bool   $active Whether the tenant is active.
     */
    public function seed(TenantId $tenantId, string $name = 'Test Tenant', bool $active = true): self
    {
        $this->tenants[$tenantId->toString()] = new ResolvedTenant($tenantId, $name, $active);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(TenantId $tenantId): ?ResolvedTenant
    {
        return $this->tenants[$tenantId->toString()] ?? null;
    }
}
