# Runtime\Orchestration\ValueObject

**Purpose.** The immutable, self-validating value objects the orchestration layer speaks in — the request
and response at the Runtime boundary, the request-scoped context, the manager's decision, the merged and
scored worker output, the unit of dispatched work, and the small resolution results the ports return.

**Responsibilities.**
- `OrchestrationRequest` — the Runtime's sole input: tenant, optional user, intent, payload, optional
  department hint. `create()` builds it from primitives.
- `OrchestrationResponse` — the Runtime's output: the execution id, final `ExecutionState`, the
  `ManagerDecision` (when reached), and the underlying `ExecutionResult` (cost/perf/timeline).
- `ExecutionContext` — request-scoped identity and authority threaded to every collaborator: execution id,
  tenant, user, granted `PermissionSet`, correlation id, deadline. Read-only; carries no behavior.
- `ManagerDecision` + `DecisionOutcome` — the manager's verdict bound to the merged result, assessment,
  summary and evidence; five outcomes (Approved / Rejected / RetryRequested / MoreWorkersRequested /
  Escalated) with named constructors.
- `MergedResult` — the consolidated view of many `WorkerResult`s (deduped evidence, combined lists, summed
  cost/time, mean confidence).
- `ConfidenceAssessment` — the Runtime's score + rationale + `needsRetry`/`needsMoreWorkers` hints.
- `WorkTask` — one dispatchable unit (capability, worker ref, payload, intent). `WorkerAssignment` — a
  resolved worker paired with its task (a live-plugin carrier, deliberately not a persisted VO).
- `DispatchMode` — Sequential / Parallel / Collaborative.
- `ResolvedTenant`, `ResolvedUser`, `DepartmentAssignment` — the results the resolution ports return.

**Dependencies.** `Nizam\Kernel\Domain\{ValueObject,TenantId,UserId}`; `Nizam\Platform\Plugin\{PermissionSet,
Contract\WorkerPlugin}`; `Nizam\Platform\Support\Assert`; the Execution layer's `ExecutionId`,
`ExecutionState`, `WorkerResult`, `EvidenceItem`, `ExecutionResult`, `ExecutionTimelineView`; and PHP.

**Public interfaces.** All classes above. Every persisted VO implements `Nizam\Kernel\Domain\ValueObject`
(value equality + a scalar-only `toArray()` where it crosses a boundary); `WorkerAssignment` is the sole
exception, being a request-scoped carrier of a live plugin instance.
