# Runtime\Execution\Domain\Event

**Purpose.** The thirteen domain events that constitute an execution's append-only history (ADR-0018).
Each is immutable, implements the Kernel `DomainEvent` contract (`occurredAt()`, `eventName()`,
`aggregateId()`), and carries the execution id plus the payload needed to reconstruct state on
replay. They are the substrate of the event store, recovery, replay, and audit.

**Responsibilities.**
- `ExecutionStarted` (carries metadata + retry/timeout policies so replay is faithful),
  `ExecutionPlanned`, `WorkTaskAssigned` (step descriptors), `ExecutionStepStarted`,
  `ExecutionStepCompleted` (full `WorkerResult`), `ExecutionRetried` (attempt + reason),
  `ExecutionSentToReview`, `ExecutionApproved`, `ExecutionRejected`, `ExecutionCompleted`,
  `ExecutionFailed`, `ExecutionRecovered`, `ExecutionCancelled`.
- Stable, dotted event names under the `runtime.*` namespace (e.g. `runtime.execution_completed`).

**Dependencies.** `Nizam\Kernel\Domain\DomainEvent`, the domain identifiers and value objects, PHP
`DateTimeImmutable`. No I/O.

**Public interfaces.** Each event's constructor, its payload accessors, and the three `DomainEvent`
methods. The application layer pulls these via `Execution::pullDomainEvents()` and publishes them
through the `ExecutionEventPublisher` port after the unit of work commits.
