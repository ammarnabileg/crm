# Plugin\Infrastructure\Migration

**Purpose.** The database schema for the Plugin Platform: the PostgreSQL 16 migration that is the
design source of record, and a structurally equivalent, runnable SQLite schema used by the integration
tests.

**Responsibilities.**
- `001_create_plugin_tables.sql` — Postgres 16 DDL for `plugin_registry`, `plugin_versions`,
  `plugin_dependencies`, and `plugin_health`. UUIDv7 primary keys (minted in the app), a **nullable**
  `tenant_id` (a plugin may be tenant-scoped or global), audit columns
  (`created_at`/`updated_at`/`created_by`/`updated_by`), `deleted_at` soft-delete, optimistic `version`,
  a JSONB `manifest` column, foreign keys, and partial indexes — `name` unique among live rows, plus
  `(kind)`, `(state)` and `(tenant_id)` lookups filtered by `deleted_at IS NULL`.
- `SqliteSchema::apply(PDO)` — creates the same tables, columns, checks, foreign keys and partial
  indexes on an SQLite connection (JSON stored as TEXT), and enables foreign-key enforcement. Kept in
  step with the SQL file.

**Dependencies.** PHP `PDO` (SqliteSchema only). The SQL file has no runtime dependency.

**Public interfaces.** `SqliteSchema::apply(PDO $connection): void`. The `.sql` file is applied by the
platform's migration tooling (or by hand) against PostgreSQL.
