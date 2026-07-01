# Runtime\Execution

**Purpose.** The execution engine of the AI Runtime: it models, drives, and persists a single run of
work (an *execution*) as a state-machine-driven, event-sourced lifecycle. This package is the heart
that the Master Orchestrator turns to actually do work.

**Responsibilities.**
- `Domain/` — the pure execution domain: the `Execution` aggregate, its state machine, entities,
  value objects, domain events, exceptions, and ports. Zero I/O; depends only on PHP + Kernel.
- (Future phases) `Application/` — the `ExecutionEngine`, pipeline, retry/timeout managers, recovery
  and replay services; `Infrastructure/` — in-memory and PDO adapters for the domain ports.

**Dependencies.** `Nizam\Kernel\*`, `Nizam\Platform\Support\Assert`. No framework.

**Public interfaces.** Everything re-exported from `Domain/` (see its README): the `Execution`
aggregate, `ExecutionStateMachine`, `ExecutionState`, `WorkerResult`, and the `Domain\Port\*`
interfaces.
