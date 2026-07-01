# Runtime\Execution\Domain\Port

**Purpose.** The outbound ports the execution domain declares and the outside world implements
(hexagonal architecture). The domain depends only on these interfaces; concrete in-memory and PDO
adapters live in later Infrastructure phases. This keeps the domain pure and testable behind
in-memory fakes.

**Responsibilities.**
- `ExecutionRepository` — persist and retrieve the current `Execution` snapshot; tenant-scoped
  (`save`, `ofId`, `ofTenant`); saves are idempotent upserts keyed by `ExecutionId`.
- `ExecutionEventStore` — the append-only store of an execution's domain events (`append`,
  `stream`); the substrate for `Execution::replay()`, recovery, and audit. Never updates or deletes.
- `ExecutionLockManager` — mutual exclusion that serializes work on a single execution
  (`acquire` → `ExecutionLock|null`, `refresh`, `release`), keyed by execution id.
- `ExecutionEventPublisher` — announces recorded events to the platform's event dispatcher after the
  unit of work commits.

**Dependencies.** `Nizam\Kernel\Domain\{DomainEvent, TenantId}`, the domain aggregate/identifiers and
the `ExecutionLock` value object. The interfaces themselves perform no I/O.

**Public interfaces.** The four interfaces above; adapters implement them, application services depend
on them.
