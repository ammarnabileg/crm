<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Provider\InMemory;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Orchestration\Port\TenantProvider;
use Nizam\Runtime\Orchestration\ValueObject\ResolvedTenant;

/**
 * The Infrastructure-layer, real, seedable in-memory {@see TenantProvider} — the safe default the
 * {@see \Nizam\Runtime\Infrastructure\RuntimeServiceProvider} binds until the production tenant directory
 * adapter exists.
 *
 * It resolves tenants from an in-process map seeded through {@see self::seed()}; an unseeded tenant
 * resolves to null, letting the orchestrator reject it exactly as a missing production tenant would. It
 * enforces the same active/inactive contract the production directory does, so it is a working adapter,
 * not a stub.
 */
final class InMemoryTenantProvider implements TenantProvider
{
    /** @var array<string, ResolvedTenant> */
    private array $tenants = [];

    /**
     * Seed a resolvable tenant.
     *
     * @param TenantId $tenantId The tenant to make resolvable.
     * @param string   $name     A human-readable tenant name.
     * @param bool     $active   Whether the tenant is active.
     */
    public function seed(TenantId $tenantId, string $name = 'Tenant', bool $active = true): self
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
