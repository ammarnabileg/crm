# Runtime\Orchestration\Port

**Purpose.** The seams the `MasterOrchestrator` and `WorkerCoordinator` depend on so the orchestration
layer stays free of I/O and infrastructure. Two families: **resolution ports** (turn identities and
references into resolved records/plugins) and **collaboration ports** (turn a resolved plugin into actual
work, and build the execution engine). Production adapters live in the infrastructure layer (future
phases); real, seedable in-memory adapters ship in `../Testing/` for tests and safe defaults.

**Responsibilities (resolution).**
- `TenantProvider` — resolve a `TenantId` to a `ResolvedTenant` (first authorization gate).
- `UserProvider` — resolve a `(TenantId, UserId)` to a `ResolvedUser` (tenant-scoped).
- `PermissionProvider` — resolve the granted `PermissionSet` for a `(tenant, user, intent)`.
- `DepartmentResolver` — route an intent (+ optional hint) to a `DepartmentAssignment` (department +
  manager reference).
- `ManagerPluginResolver` / `WorkerPluginResolver` — load the concrete `ManagerPlugin` / `WorkerPlugin`
  (Plugin-Platform kind contracts) for a reference, tenant-scoped.
- `AutomationSelector` — select an automation reference for a worker-declared goal (the automation seam).

**Responsibilities (collaboration).**
- `ManagerAgent` — drive a resolved `ManagerPlugin`'s two runtime behaviours: `plan()` a request into
  `WorkTask`s and `decide()` on the merged, scored output. (The SDK contract carries only identity; how a
  manager plans/decides is a runtime concern the platform owns.)
- `WorkerInvoker` — run a resolved `WorkerPlugin` against a `WorkTask` in a context, returning a
  `WorkerResult`. A worker only ever receives its task and the read-only context here — never a peer or the
  coordinator — which is what makes worker-to-worker calls impossible.
- `ExecutionEngineFactory` — `build(ExecutionPlanner, WorkerDispatcher): ExecutionEngine`, so the
  orchestrator gets a per-request engine wired to the manager's steps and the coordinated dispatch without
  itself touching persistence/locking/events.

**Dependencies.** `Nizam\Kernel\Domain\{TenantId,UserId}`; `Nizam\Platform\Plugin\{PermissionSet,
Contract\ManagerPlugin,Contract\WorkerPlugin}`; the layer's own value objects; the Execution layer's
`ExecutionEngine`, `ExecutionPlanner`, `WorkerDispatcher`, and `WorkerResult`. Interfaces only — no I/O.

**Public interfaces.** The ten interfaces above. These are the stable seams the infrastructure layer
implements and the orchestrator is constructed against.
