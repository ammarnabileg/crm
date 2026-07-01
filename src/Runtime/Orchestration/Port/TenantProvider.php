<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Port;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Orchestration\ValueObject\ResolvedTenant;

/**
 * The port through which the {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} resolves the tenant
 * a request runs for.
 *
 * Tenant resolution is the first authorization gate: a request whose tenant is unknown or inactive is
 * rejected before any execution begins. The orchestration layer depends only on this interface; the
 * production adapter reads the tenant directory, and an in-memory, seedable adapter serves tests and
 * safe defaults.
 */
interface TenantProvider
{
    /**
     * Resolve the tenant with the given identity, or null when none exists.
     *
     * @param TenantId $tenantId The tenant to resolve.
     *
     * @return ResolvedTenant|null The resolved tenant, or null when unknown.
     */
    public function resolve(TenantId $tenantId): ?ResolvedTenant;
}
