# Runtime — Infrastructure / Persistence / InMemory

## Purpose
Real, single-process implementations of the execution persistence ports. Non-durable but fully faithful
to the port contracts (tenant scoping, append-only ordering, TTL-based lock expiry), so they serve as
the safe default and as the substrate for unit tests without a database.

## Responsibilities
- `InMemoryExecutionRepository` — upsert/read execution snapshots, tenant-scoped, most-recent-first.
- `InMemoryExecutionEventStore` — append events in order and stream them back for replay/recovery.
- `InMemoryExecutionLockManager` — serialize work per execution with owner-token and TTL semantics.

## Dependencies
- The Runtime `Execution\Domain` ports/VOs; `Nizam\Kernel\Domain\Clock`; `Nizam\Platform\Support\Uuid`.

## Public interfaces
- `InMemoryExecutionRepository`, `InMemoryExecutionEventStore`, `InMemoryExecutionLockManager`.
