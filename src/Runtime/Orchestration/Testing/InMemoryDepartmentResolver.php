<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Testing;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Orchestration\Port\DepartmentResolver;
use Nizam\Runtime\Orchestration\ValueObject\DepartmentAssignment;

/**
 * A real, seedable in-memory {@see DepartmentResolver} for tests and safe defaults.
 *
 * It routes an intent to a {@see DepartmentAssignment} from an in-process map seeded through
 * {@see self::route()}; an unrouted intent resolves to null, letting the orchestrator reject it exactly
 * as an unroutable production intent would. Routing is tenant-scoped and honours a department hint on the
 * request: a (tenant, intent, hint) route takes precedence over the plain (tenant, intent) route, so a
 * caller can bias routing without changing the intent. This is a working adapter, not a stub.
 */
final class InMemoryDepartmentResolver implements DepartmentResolver
{
    /** @var array<string, DepartmentAssignment> */
    private array $routes = [];

    /**
     * Seed a route from an intent (optionally qualified by a department hint) to an assignment.
     *
     * @param string      $intentRef     The intent to route.
     * @param string      $departmentRef The department the work lands in.
     * @param string      $managerRef    The manager plugin leading the department.
     * @param string|null $hint          The department hint this route matches, or null for the default route.
     */
    public function route(
        TenantId $tenantId,
        string $intentRef,
        string $departmentRef,
        string $managerRef,
        ?string $hint = null,
    ): self {
        $this->routes[$this->key($tenantId, $intentRef, $hint)] = new DepartmentAssignment($departmentRef, $managerRef);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(TenantId $tenantId, string $intentRef, ?string $departmentRef): ?DepartmentAssignment
    {
        if ($departmentRef !== null) {
            $hinted = $this->routes[$this->key($tenantId, $intentRef, $departmentRef)] ?? null;
            if ($hinted !== null) {
                return $hinted;
            }
        }

        return $this->routes[$this->key($tenantId, $intentRef, null)] ?? null;
    }

    /**
     * The composite map key scoping a route to its tenant, intent, and optional hint.
     */
    private function key(TenantId $tenantId, string $intentRef, ?string $hint): string
    {
        return $tenantId->toString() . "\0" . $intentRef . "\0" . ($hint ?? '');
    }
}
