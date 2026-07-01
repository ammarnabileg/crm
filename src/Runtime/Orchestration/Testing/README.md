# Runtime\Orchestration\Testing

**Purpose.** Real, seedable, dependency-free implementations of the orchestration ports and the plugin
kind contracts, used by the Runtime's own test suite and as the safe defaults the Runtime ships until the
production infrastructure adapters exist. Everything here is a genuine, working implementation — not a
stub or mock: the in-memory adapters honour the same tenant-scoping, active/inactive, and append-only
contracts their production counterparts will, and the fake plugins publish valid manifests that really
implement their declared kind contract.

**Responsibilities.**
- Fake plugins: `FakeManagerPlugin` (a real `ManagerPlugin`), `FakeWorkerPlugin` (a real `WorkerPlugin`
  declaring a required permission so the coordinator's gate can be exercised).
- Deterministic collaboration adapters: `DeterministicManagerAgent` (plans a fixed number of tasks; decides
  by honouring the Runtime's confidence assessment) and `DeterministicWorkerInvoker` (echoes the task
  payload; per-task confidence/error overrides drive the evaluator and decision down chosen branches). No
  clock, no randomness.
- In-memory resolution adapters: `InMemoryTenantProvider`, `InMemoryUserProvider`,
  `InMemoryPermissionProvider`, `InMemoryDepartmentResolver`, `InMemoryManagerPluginResolver`,
  `InMemoryWorkerPluginResolver`, `InMemoryAutomationSelector` — each seedable and tenant-scoped.
- Execution wiring: `InMemoryExecutionStore` (one object implementing the repository, append-only event
  store, and event publisher), `InMemoryExecutionLockManager` (real single-process mutual exclusion), and
  `InMemoryExecutionEngineFactory` (assembles a genuine `ExecutionEngine` around those shared collaborators
  plus a validated stage pipeline, so two runs of the same execution id serialize and short-circuit on
  idempotency exactly as in production).

**Dependencies.** The orchestration `Port\*` and `ValueObject\*`; the Execution layer's engine, pipeline,
stages, validator, retry engine, domain ports, aggregate, and value objects; `Nizam\Platform\Plugin\*`
(contracts, manifest, permissions, versions); `Nizam\Kernel\Domain\{Clock,TenantId,UserId,DomainEvent}`;
`Nizam\Platform\Support\{SystemClock,Uuid}`; and PHP.

**Public interfaces.** All classes above — instantiated and seeded by the unit tests (and usable as safe
defaults when wiring the orchestrator without infrastructure).

**Note.** These are single-process, in-memory implementations intended for tests and safe defaults. They
are not the production persistence/locking adapters (PDO snapshot store, append-only history, DB advisory
lock), which arrive in a later infrastructure phase.
