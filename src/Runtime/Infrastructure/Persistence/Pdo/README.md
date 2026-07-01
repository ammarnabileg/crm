# Runtime — Infrastructure / Persistence / Pdo

## Purpose
Durable, tenant-scoped, soft-deleting PDO adapters for the execution persistence ports, working
identically on SQLite and PostgreSQL. The append-only history is authoritative; the `executions` row is
a queryable projection only.

## Responsibilities
- `PdoExecutionEventStore` — append each recorded event as an immutable, sequence-numbered row; stream
  them back in order, rebuilt via `ExecutionEventSerializer`. Never updates or deletes a row.
- `PdoExecutionRepository` — upsert the snapshot row for querying, but **rehydrate the aggregate from the
  event stream** (`Execution::replay`) so behaviour never drifts from a projection. Tenant-scoped reads,
  `deleted_at IS NULL`, plus a `softDelete()` helper.
- `PdoExecutionLockManager` — advisory lock row with a TTL; acquire inserts or steals an expired row,
  refresh/release verify ownership by token.
- `ExecutionEventSerializer` — the event↔JSON hydrator, reconstructing the rich VOs each event carries.
- `ExecutionRowMapper` — the aggregate→snapshot-row projector.

## Dependencies
- PDO; `Nizam\Platform\Support\{Uuid,Json}`; `Nizam\Platform\Exception\PlatformException`; the Runtime
  `Execution\Domain` events, ports, and value objects.

## Public interfaces
- `PdoExecutionRepository`, `PdoExecutionEventStore`, `PdoExecutionLockManager`,
  `ExecutionEventSerializer`, `ExecutionRowMapper`.
