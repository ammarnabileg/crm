# Behavior\Infrastructure\Migration

**Purpose.** The database schema for the Behavior bounded context: the PostgreSQL 16 migration that is
the design source of record, and a structurally equivalent, runnable SQLite schema used by the
integration tests.

**Responsibilities.**
- `001_create_behavior_tables.sql` — Postgres 16 DDL for `behavior_profiles`,
  `behavior_profile_revisions`, `behavior_change_proposals`, and `behavior_observations`. UUID primary
  keys (UUIDv7, minted in the app), `tenant_id`, audit columns (`created_at`/`updated_at`/`created_by`/
  `updated_by`), `deleted_at` soft-delete, optimistic `version`, JSONB trait/revision/evidence columns,
  foreign keys, and partial indexes (including `(tenant_id, role_id) WHERE deleted_at IS NULL`).
- `SqliteSchema::apply(PDO)` — creates the same tables, columns, checks, foreign keys, and partial
  indexes on an SQLite connection (JSON stored as TEXT), and enables foreign-key enforcement. Kept in
  step with the SQL file.

**Dependencies.** PHP `PDO` (SqliteSchema only). The SQL file has no runtime dependency.

**Public interfaces.** `SqliteSchema::apply(PDO $connection): void`. The `.sql` file is applied by the
platform's migration tooling (or by hand) against PostgreSQL.
