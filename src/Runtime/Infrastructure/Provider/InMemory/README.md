# Runtime — Infrastructure / Provider / InMemory

## Purpose
Real, seedable in-memory implementations of the Orchestration provider ports. Each enforces the same
tenant-scoping and active/available contract its production counterpart will, so they are working
adapters (and safe defaults), not stubs.

## Responsibilities
- `InMemoryTenantProvider` / `InMemoryUserProvider` — resolve tenants/users, enforcing active + tenant scope.
- `InMemoryPermissionProvider` — resolve the granted `PermissionSet` the coordinator gates workers against.
- `InMemoryDepartmentResolver` — route an intent (with optional hint) to a department + manager reference.
- `InMemoryManagerPluginResolver` / `InMemoryWorkerPluginResolver` — resolve plugin-kind contracts, tenant-scoped.
- `InMemoryAutomationSelector` — deterministic goal→automation selection at the Runtime seam.

## Dependencies
- The Runtime `Orchestration\Port` interfaces and value objects; `Nizam\Kernel\Domain\*`;
  `Nizam\Platform\Plugin\{PermissionSet,PluginPermission,Contract\*}`.

## Public interfaces
- The seven `InMemory*` provider adapters listed above; each exposes a fluent `seed()/route()/register()/grantKeys()`.
