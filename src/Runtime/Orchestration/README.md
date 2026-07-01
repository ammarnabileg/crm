# Runtime\Orchestration

**Purpose.** The single entry point into the AI Runtime. Realizes ADR-0017 (one Master Orchestrator):
nothing executes work except by handing an `OrchestrationRequest` to `MasterOrchestrator::handle()`. The
layer routes a request to a department and its Manager plugin, has the manager plan the work, dispatches
the Worker plugins through the one coordinator, merges and scores their results, and returns the
manager's decision — recording every step on the execution's timeline via the Execution engine's event
flow. Workers never talk to users, to automation engines, or to each other: every interaction crosses
the Runtime seam.

**Responsibilities.**
- `MasterOrchestrator` — the sole `handle(OrchestrationRequest): OrchestrationResponse`. In one
  deterministic pass it validates the request; resolves and authorizes tenant, user, and granted
  permissions; routes the intent to a department + Manager plugin; builds the request-scoped
  `ExecutionContext`; asks the manager (via `ManagerAgent`) to plan `WorkTask`s; resolves each task's
  worker plugin; dispatches them through the `WorkerCoordinator`; drives the whole run through the
  `ExecutionEngine` (built per request by the `ExecutionEngineFactory`, which folds cost/perf/timeline and
  persists the event-sourced aggregate); merges results with `ResultMerger`; scores them with
  `ConfidenceEvaluator`; and asks the manager to `decide()`. It also exposes `selectAutomation()` as the
  Runtime seam for worker-declared automation goals.
- `WorkerCoordinator` — the only place a worker is invoked, in the three spec-mandated modes: **Sequential**
  (ordered, one at a time), **Parallel** (deterministic in-process fan-out — no real threads; results
  collected in submission order), and **Collaborative** (`dispatchCollaborative()` — after the initial
  batch a bounded re-entry callback may supply the manager's follow-up assignments; additional workers are
  satisfied by *re-entering the coordinator*, never by worker-to-worker calls, which are structurally
  impossible because a worker only ever receives its task and the read-only context). Enforces the
  permission gate before every dispatch.
- `ResultMerger` — folds many `WorkerResult`s into one `MergedResult` (dedupe evidence, combine
  recommendations/warnings/errors, sum cost/time, mean confidence). Pure and deterministic.
- `ConfidenceEvaluator` — scores a `MergedResult` into a `ConfidenceAssessment` (score + rationale +
  `needsRetry`/`needsMoreWorkers`). Pure and deterministic; scores and hints, never decides.

**Sub-namespaces.** `ValueObject/` (requests, responses, context, decision, merged/assessment, tasks,
enums, resolution VOs); `Port/` (the resolution and collaboration seams — providers, resolvers, the
manager/worker agents, the engine factory); `Exception/` (`OrchestrationException`, `RUNTIME.ORCHESTRATION.*`
codes); `Service/` (request-scoped bridge adapters between the orchestrator and the Execution engine);
`Testing/` (real, seedable in-memory port adapters and fake Manager/Worker plugins used by the Runtime's
own tests and as safe defaults).

**Dependencies.** `Nizam\Runtime\Execution\*` (engine, request/result, aggregate, steps, worker result,
timeline view); `Nizam\Platform\Plugin\Contract\{ManagerPlugin,WorkerPlugin}` and
`Nizam\Platform\Plugin\{PermissionSet,PermissionSet…}`; `Nizam\Kernel\Domain\{Clock,TenantId,UserId}`;
`Nizam\Platform\Support\Assert`; and PHP. No framework, no HTTP, no persistence of its own — all I/O sits
behind the ports (persistence/locking/event publication live behind `ExecutionEngineFactory`).

**Public interfaces (for the layers above and infrastructure).**
- Entry point: `MasterOrchestrator`.
- Services: `WorkerCoordinator`, `ResultMerger`, `ConfidenceEvaluator`.
- Value objects: `ValueObject\{OrchestrationRequest,OrchestrationResponse,ExecutionContext,ManagerDecision,
  DecisionOutcome,MergedResult,ConfidenceAssessment,WorkTask,WorkerAssignment,DispatchMode,ResolvedTenant,
  ResolvedUser,DepartmentAssignment}`.
- Ports (adapters are infrastructure/future phases; in-memory adapters ship in `Testing/`):
  `Port\{TenantProvider,UserProvider,PermissionProvider,DepartmentResolver,ManagerPluginResolver,
  WorkerPluginResolver,AutomationSelector,ManagerAgent,WorkerInvoker,ExecutionEngineFactory}`.
- Exception: `Exception\OrchestrationException`.

**Key rules honored.**
- One entry point (ADR-0017): work executes only through `MasterOrchestrator::handle()`.
- Workers are isolated: invoked only via the coordinator, never reaching users, automations, or peers;
  collaboration is manager-driven re-entry, enforced in the coordinator API.
- Authorization at the seam: the granted `PermissionSet` is checked before every worker dispatch.
- Tenant-scoped: every resolution port is keyed by tenant.
- Deterministic: given deterministic collaborators the whole `handle()` — states, timeline, decision — is
  reproducible; parallelism is ordered in-process fan-out, not real threads (see the phase audit).
