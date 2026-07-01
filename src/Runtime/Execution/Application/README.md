# Runtime\Execution\Application

**Purpose.** The use-case layer that *executes* work through the AI Runtime. It drives the pure
`Runtime\Execution\Domain` — the event-sourced `Execution` aggregate and its `ExecutionStateMachine`
— through the spec-mandated pipeline (Validate → Plan → Assign → Run → Review → Finalize), enforcing
retries, timeouts, locking, idempotency, persistence, and event publication. It realizes ADR-0017
(the single execution path) and ADR-0018 (the execution state machine). It holds **no domain
invariants of its own**: every transition is guarded inside the aggregate; this layer only sequences
those transitions, commits them, and reports the outcome. All I/O sits behind the domain ports
(`ExecutionRepository`, `ExecutionEventStore`, `ExecutionLockManager`, `ExecutionEventPublisher`) and
two application ports (`ExecutionPlanner`, `WorkerDispatcher`), whose production adapters live in
`Runtime\Infrastructure` and `Runtime\Orchestration`.

**Responsibilities.**
- `ExecutionEngine` — the facade the Master Orchestrator calls. `execute(ExecutionRequest):
  ExecutionResult`: idempotent by `ExecutionId` (a terminal execution is never re-run), runs the
  pipeline inside an `ExecutionLockManager` lock (released in a `finally`), applies the `RetryEngine`
  via a bounded recover-and-resume loop, anchors a `TimeoutManager` to the run, and commits each
  attempt (append events → save snapshot → publish) before the next.
- `ExecutionPipeline` + `Stage/` — the ordered `PipelineStage`s (`ValidateStage`, `PlanStage`,
  `AssignStage`, `RunStage`, `ReviewStage`, `FinalizeStage`). Each advances the aggregate exactly one
  legal transition; any stage error short-circuits the execution to `Failed`.
- `RetryEngine` (+ `ValueObject/RetryDecision`) — decides retry-vs-fail from the `RetryPolicy` and
  applies jitter through an injectable, seedable randomizer so the domain stays deterministic.
- `TimeoutManager` — enforces the wall-clock and per-step budgets using forward-only elapsed time read
  from the injected `Clock`; throws `ExecutionTimedOut`.
- `RecoveryService` — rebuilds a failed execution from the event store and marks it `Recovered`
  (persist + publish). `ReplayService` — read-only rebuild into an `ExecutionReplayView`.
- `ExecutionValidator` — validates an `ExecutionRequest`, returning a `Result` (expected failures, not
  exceptions).
- `CostTracker` / `PerformanceTracker` — read-side aggregation of cost/performance across steps.
- `DefaultExecutionPlanner` / `DefaultWorkerDispatcher` — deterministic, dependency-free default
  adapters for the application ports so the layer runs and tests end-to-end without the orchestration
  layer present.
- `ValueObject/` — `ExecutionRequest`, `ExecutionResult`, `RetryDecision`.
- `Dto/` — `ExecutionView`, `ExecutionStepView`, `ExecutionTimelineView`, `ExecutionMetricsView`,
  `ExecutionReplayView` (scalars and plain arrays only; transport-safe).
- `Port/` — `ExecutionPlanner`, `WorkerDispatcher` (implemented by the orchestration layer's
  manager/coordinator in production).
- `Exception/ExecutionApplicationException` — typed orchestration failures (lock unavailable, not
  found, nothing to replay, not recoverable, pipeline stalled) with stable `EXEC.APPLICATION.*` codes.

**Dependencies.** `Nizam\Runtime\Execution\Domain\*` (aggregate, entities, value objects, enums,
ports, events, exceptions, state machine); `Nizam\Kernel\Domain\{Clock,TenantId,UserId,DomainEvent}`;
`Nizam\Platform\Support\{Assert,Result}`; and PHP itself. No framework, no persistence, no HTTP.

**Public interfaces (for the layers above).**
- Facade: `ExecutionEngine`.
- Services: `ExecutionPipeline`, `RetryEngine`, `TimeoutManager`, `RecoveryService`, `ReplayService`,
  `ExecutionValidator`, `CostTracker`, `PerformanceTracker`.
- Ports: `Port\ExecutionPlanner`, `Port\WorkerDispatcher` (+ default adapters).
- Value objects: `ValueObject\{ExecutionRequest,ExecutionResult,RetryDecision}`.
- Read models: `Dto\{ExecutionView,ExecutionStepView,ExecutionTimelineView,ExecutionMetricsView,ExecutionReplayView}`.
- Exception: `Exception\ExecutionApplicationException`.

**Key rules honored.**
- One entry point: work is only ever executed through `ExecutionEngine.execute()`.
- Every mutation flows through the aggregate's `ExecutionStateMachine` guards; the pipeline never
  invents an illegal transition and fails cleanly to `Failed` when a stage errors.
- Idempotent by `ExecutionId`: a terminal execution's stored outcome is returned, never re-run; the
  lock serializes concurrent runs of the same execution.
- Event-sourced: each attempt's recorded events are appended to the append-only store, and the
  execution is fully replayable/recoverable from that stream.
- Deterministic: given deterministic collaborators (planner, dispatcher, clock, randomizer) the run's
  states, timeline, cost, and performance are reproducible — no real threads; parallel worker fan-out
  is modelled as ordered in-process dispatch.
