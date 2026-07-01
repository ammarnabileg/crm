# Runtime — Infrastructure / Persistence

## Purpose
Concrete adapters for the execution persistence ports: the snapshot repository, the append-only event
store, and the mutual-exclusion lock manager. Two families sit side by side — `InMemory` (single
process, non-durable) and `Pdo` (durable, SQLite + PostgreSQL) — so the same wiring runs with or
without a database.

## Responsibilities
- `InMemory/` — real single-process adapters used as the default and by unit tests.
- `Pdo/` — durable, tenant-scoped, soft-deleting adapters; the repository rehydrates aggregates from the
  append-only history (never from a lossy snapshot), the event store is strictly append-only, and the
  lock manager is an advisory row with a TTL.

## Dependencies
- The Runtime `Execution\Domain` ports and value objects; `Nizam\Platform\Support\{Uuid,Json}`; PDO.

## Public interfaces
- `InMemory\{InMemoryExecutionRepository, InMemoryExecutionEventStore, InMemoryExecutionLockManager}`.
- `Pdo\{PdoExecutionRepository, PdoExecutionEventStore, PdoExecutionLockManager, ExecutionEventSerializer, ExecutionRowMapper}`.
