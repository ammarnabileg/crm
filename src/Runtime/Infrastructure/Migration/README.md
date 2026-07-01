# Runtime — Infrastructure / Migration

## Purpose
The database schema for the Runtime's execution persistence and event-sourced state store, realizing
ADR-0021 (which implements the ADR-0018 state machine).

## Responsibilities
- `001_create_runtime_tables.sql` — the Postgres 16 design source of record: `executions`,
  `execution_history` (append-only event store), `execution_locks` (advisory-row TTL lock),
  `execution_timeline`, `execution_logs`, `manager_decisions`, `worker_results`, `execution_metrics`,
  `evidence`. UUIDv7 PKs (app-minted), `tenant_id`, audit + `deleted_at` + `version` columns, JSONB
  documents, and the hot-path indexes `(tenant_id, state)` and `(execution_id, sequence_no)`.
- `SqliteSchema::apply(PDO)` — a structurally equivalent SQLite schema for in-memory integration tests
  (JSON as TEXT, foreign keys enabled). Keep it in step with the SQL file.

## Dependencies
- PDO (for `SqliteSchema`). The `.sql` file targets PostgreSQL 16.

## Public interfaces
- `SqliteSchema::apply(PDO $connection): void`.
