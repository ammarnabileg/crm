# Runtime\Execution\Domain

**Purpose.** The pure, I/O-free heart of the execution engine. It models one run of work as an
event-sourced `Execution` aggregate whose every state change is gated by the `ExecutionStateMachine`
of ADR-0018 and recorded as a domain event, so the aggregate can be rebuilt bit-for-bit from its
event stream (`Execution::replay()`) for recovery, replay, and audit. This layer contains no
persistence and depends only on PHP and `Nizam\Kernel` (plus the pure `Nizam\Platform\Support\Assert`
guard).

**Responsibilities.**
- Define the typed identifiers `ExecutionId`, `ExecutionStepId`, `SessionId` (extending the Kernel
  `Identifier`).
- Own the `ExecutionState` enum — exactly the thirteen states of ADR-0018 — and the stateless
  `ExecutionStateMachine` that is the single source of truth for legal transitions
  (`allowedTransitions()`, `canTransition()`, `assert()`), with `Completed` and `Cancelled` terminal.
- Own the `Execution` aggregate and its invariants: `start → plan → assign → run/beginStep/
  completeStep/awaitStep → toReview → approve/reject → complete`, plus `retry` (retry-policy
  enforced, `RetryExhausted` at the limit), `fail → recover`, and `cancel`. Each mutator asserts the
  transition, records the matching event, and applies it — the sole way state changes, guaranteeing
  faithful replay. The execution-level `Running`/`Waiting` states are derived from the step events
  (`ExecutionStepStarted`/`ExecutionStepCompleted`) so they too survive event-sourced reconstitution.
- Track cost and performance forward as steps complete (`CostSnapshot`, `PerformanceSnapshot`), and
  carry the human-readable `ExecutionTimeline` of `TimelineEntry`s surfaced to the Execution Monitor.
- Define the structured `WorkerResult` contract (with `EvidenceItem`), self-validating with
  confidence bound to [0, 1] — the only channel by which a worker's output reaches the Runtime.
- Carry the `ExecutionMetadata`, `RetryPolicy`, `TimeoutPolicy`, and `ExecutionLock` value objects.
- Record `Event/` domain events (the thirteen mandated by ADR-0018) for the application layer to
  publish after the unit of work commits.
- Raise a typed `Exception/` hierarchy (codes under `EXEC.*`) for every violation.
- Declare the `Port/` interfaces the outside world implements (repository, event store, lock
  manager, event publisher).

**Dependencies.** `Nizam\Kernel\Domain\{Identifier, Entity, AggregateRoot, DomainEvent,
RecordsDomainEvents, TenantId, UserId, Clock, ValueObject}`; `Nizam\Platform\Support\Assert` for
precondition guards. No framework, no persistence, no time source other than the injected `Clock`.

**Public interfaces (for the layers above).**
- Aggregate: `Execution` (`start`, `replay`, and the lifecycle mutators).
- Entity: `ExecutionStep`; enums: `ExecutionState`, `ExecutionStepState`.
- State machine: `ExecutionStateMachine`.
- Value objects (`ValueObject/`): `WorkerResult`, `EvidenceItem`, `ExecutionMetadata`,
  `ExecutionTimeline`, `TimelineEntry`, `CostSnapshot`, `PerformanceSnapshot`, `RetryPolicy`,
  `BackoffStrategy`, `TimeoutPolicy`, `ExecutionLock`.
- Events (`Event/`): the thirteen `Execution*` / `WorkTaskAssigned` domain events.
- Exceptions (`Exception/`): `ExecutionException` and its subtypes (`EXEC.*`).
- Ports (`Port/`): `ExecutionRepository`, `ExecutionEventStore`, `ExecutionLockManager`,
  `ExecutionEventPublisher`.

**Key invariants.**
- Every state change is legal under `ExecutionStateMachine` and is recorded as an event; no state is
  mutated except by applying an event, so `replay(events)` reproduces identical state (including
  timeline, cost, performance, and version).
- Terminal states (`Completed`, `Cancelled`) admit no further transitions.
- The retry budget from `RetryPolicy` is enforced; exceeding it raises `RetryExhausted`.
- `WorkerResult` confidence is always within [0, 1]; times and costs are non-negative.
- Referencing a step the execution does not own raises `UnknownStepException`.
- All time comes from the injected `Clock`; the domain performs no I/O.
