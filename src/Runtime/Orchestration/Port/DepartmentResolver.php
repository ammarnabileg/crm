<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Port;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Orchestration\ValueObject\DepartmentAssignment;

/**
 * The port through which the {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} routes a request to
 * a department and the Manager plugin that leads it.
 *
 * Routing maps an intent — biased by any department hint the request carries — to a
 * {@see DepartmentAssignment} naming the department and the manager plugin reference. Resolution is
 * tenant-scoped so different tenants can wire intents to different departments. The orchestration layer
 * depends only on this interface; the production adapter reads the tenant's org structure, and an
 * in-memory, seedable adapter serves tests and safe defaults.
 */
interface DepartmentResolver
{
    /**
     * Resolve the department and manager for an intent within a tenant, or null when unroutable.
     *
     * @param TenantId    $tenantId      The owning tenant.
     * @param string      $intentRef     The requested capability/intent identifier.
     * @param string|null $departmentRef The department hint on the request, if any.
     *
     * @return DepartmentAssignment|null The resolved assignment, or null when the intent is unroutable.
     */
    public function resolve(TenantId $tenantId, string $intentRef, ?string $departmentRef): ?DepartmentAssignment;
}
