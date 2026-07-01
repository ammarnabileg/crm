# Runtime — Infrastructure / Provider

## Purpose
Concrete adapters for the Orchestration provider ports — the seams through which the
`MasterOrchestrator` resolves tenant, user, permissions, department→manager routing, manager/worker
plugins, and automation selection.

## Responsibilities
- `InMemory/` — real, seedable in-memory adapters for every provider port, bound by
  `RuntimeServiceProvider` as safe defaults until the production directory/registry adapters land.

## Dependencies
- The Runtime `Orchestration\Port` interfaces and value objects; `Nizam\Kernel\Domain\*`;
  `Nizam\Platform\Plugin\*`.

## Public interfaces
- `InMemory\{InMemoryTenantProvider, InMemoryUserProvider, InMemoryPermissionProvider,
  InMemoryDepartmentResolver, InMemoryManagerPluginResolver, InMemoryWorkerPluginResolver,
  InMemoryAutomationSelector}`.
