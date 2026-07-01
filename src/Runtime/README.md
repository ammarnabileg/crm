# Runtime

**Purpose.** The AI Runtime is the single path through which the platform executes work. A request
enters the Master Orchestrator, which validates it, resolves tenant/user/permissions/department,
loads the Manager plugin, dispatches Worker plugins, coordinates them, merges and scores their
results, and returns the Manager's decision. Every run is a state-machine-driven, event-sourced,
auditable, cost/perf-tracked, retryable, recoverable, replayable **execution**. Realizes ADR-0017
(single Master Orchestrator) and ADR-0018 (execution state machine).

**Responsibilities (this phase — Execution Domain).**
- Model one run of work as the pure, event-sourced `Execution\Domain\Execution` aggregate, driven
  strictly by the `ExecutionStateMachine` over the thirteen `ExecutionState`s of ADR-0018.
- Define the structured `WorkerResult` contract (and `EvidenceItem`) that workers return to the
  Runtime — never to users, to each other, or to automation engines directly.
- Track cost and performance as steps complete; enforce retry and timeout policies; carry the
  human-readable `ExecutionTimeline`.
- Declare the `Port/` interfaces (repository, event store, lock manager, event publisher) that later
  Infrastructure phases implement.

**Dependencies.** PHP 8.4 and `Nizam\Kernel\*` (Identifier, Entity, AggregateRoot, DomainEvent,
Clock, TenantId, UserId), plus the pure `Nizam\Platform\Support\Assert` guard. No framework, no I/O.

**Public interfaces (for the layers above).** `Execution\Domain\Execution` (aggregate),
`ExecutionStateMachine`, `ExecutionState`, `WorkerResult`/`EvidenceItem`, the policy value objects,
and the `Execution\Domain\Port\*` interfaces.

**Sub-packages.**
- `Execution/Domain/` — the pure execution domain (this phase). See its README.
