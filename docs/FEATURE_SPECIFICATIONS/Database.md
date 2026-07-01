# FEATURE SPEC — Database

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Database · **Layer:** Foundation · **Implemented in:** Phase 8
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Database module is the **bespoke persistence foundation** of HaHireAI. It
provides connection management, a programmatic schema builder, a purpose-built
forward-only **migration engine**, a transaction manager, a base repository, and
the mandatory **tenant guard** that enforces workspace isolation at the
data-access layer. Per Constitution §5 it is **native PHP — no ORM** (no
Laravel/Doctrine/Phinx). It supplies the persistence capability that every
data-owning module builds on, while owning none of those modules' business
tables.

## 2. Scope

**In scope**
- Connection Manager: pooled PDO/MySQL connections, prepared-statement execution, UTC session, `utf8mb4` (`DATABASE_GUIDE.md` §2).
- Schema Builder: programmatic create/alter/drop of tables, columns, indexes, foreign keys.
- Migration Engine (bespoke): `create / alter / drop / rollback / history / version`, a migrations ledger, ordered & idempotent application, build-from-zero.
- Transaction Manager: begin/commit/rollback, nested/savepoint support, transactional use-case boundaries.
- Base Repository: shared CRUD primitives, keyset pagination, batch reads (no N+1), prepared statements only.
- Tenant Guard: central `workspace_id` filtering for SELECT/UPDATE/DELETE on workspace-scoped tables, with an explicit, audited bypass for Platform Context.

**Out of scope**
- Any ORM, active-record, or entity-mapping layer (forbidden by Constitution §5).
- Business entities, business repositories, or business migrations — each owned by its own module under `Database/`.
- The global migration *orchestration order* across modules sits with the runner invoked by the Installer/CLI; this module provides the *engine* it drives.
- Caching, search indexing, queueing — separate shared services.
- The authoritative entity/column/index design — that is `DATABASE_ARCHITECTURE.md` and `INDEXING_GUIDE.md` (Phase 3).

## 3. Inputs

- Database connection settings (host, port, name, credentials) from the Environment/Configuration loaders (Core Kernel).
- Migration definitions shipped by each module under its `Database/` folder.
- Schema-builder instructions issued by migrations.
- The active **Workspace Context** (`workspace_id`) supplied by the application boundary for tenant-scoped queries (never taken from client input — `DATABASE_GUIDE.md` §6.2).
- Repository read/write requests from modules' Infrastructure layers.

## 4. Outputs

- Established, health-checkable database connections.
- Applied schema changes and an up-to-date **migrations ledger** (version/history).
- Query results (hydrated rows / value objects) returned to Infrastructure repositories.
- Transaction outcomes (commit/rollback) around use cases.
- A guarantee that every tenant-scoped query is filtered by `workspace_id`.
- A connection/migration health probe exposed to the Core Kernel **Health Checker**.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Core Kernel** (Foundation) — container, configuration, environment, logger, events, health-probe registration.
- **Shared Kernel** (`/shared`) — ULID generation (`CHAR(26)` PKs, app-generated, no `AUTO_INCREMENT`), `Result`, `Clock`.
- Per `MODULES.md` §5, **all data-owning modules depend on Database**; Database itself depends only on Core Kernel and the Shared Kernel. No cycles.

## 6. Permissions (keys this module declares; resource.action grammar)

The Database module is **infrastructure** and performs no end-user authorization
(deny-by-default checks live at each business module's Application boundary,
`PERMISSION_MODEL.md` §5). It exposes only operational, Platform-Context keys for
migration and schema operations run by System Owners:

- `system.migrations.run` — apply pending migrations (Platform Context / Installer).
- `system.migrations.view` — view migration history/version/ledger state.
- `system.database.manage` — perform guarded maintenance/schema operations.

> The tenant-guard bypass for cross-workspace reads is itself permission-gated and
> audited; it MUST NOT be reachable from ordinary workspace use cases
> (`DATABASE_GUIDE.md` §6.2).

## 7. Events (Published / Subscribed)

**Published**
- `database.migration.applied` — a migration was successfully applied (carries version/identifier).
- `database.migration.rolledback` — a migration was rolled back (development/pre-release context; production is forward-only, `DATABASE_GUIDE.md` §12).
- `database.connection.failed` — a connection attempt failed (for Observability/health).

**Subscribed**
- None. The Database module provides persistence; it does not react to other modules' domain events.

## 8. Data Owned (conceptual entities only — defer detail to DATABASE_ARCHITECTURE.md, Phase 3)

This module owns **only its own infrastructure tables**, never business data:

- **Migration Ledger Entry** — record of each applied migration (identifier, applied-at, batch/version, checksum) supporting `history` and `version`.

All other tables are owned by their respective modules (Constitution §4, §9;
`DATABASE_GUIDE.md` §11). This module provides the **base repository** and
**tenant guard** those modules use, but does not read or write their tables.

## 9. Acceptance Criteria (checklist of testable outcomes)

- [ ] All database access uses **prepared statements**; string-concatenated SQL is impossible through the provided APIs (Constitution §6, §10).
- [ ] Primary keys are `CHAR(26)` ULIDs generated in the application; the schema builder rejects/avoids `AUTO_INCREMENT` (`DATABASE_GUIDE.md` §3).
- [ ] The migration engine supports **create, alter, drop, rollback, history, and version**, records applied migrations in a ledger, and applies pending migrations **idempotently** in deterministic order.
- [ ] The full migration set **builds a correct, current schema from an empty database** (exercised by the Installer and CI — `DATABASE_GUIDE.md` §12).
- [ ] Production migrations are **forward-only**; rollback exists for development/pre-release only and never as a production recovery path.
- [ ] The Transaction Manager provides begin/commit/rollback (with savepoints), and a failed use case rolls back atomically.
- [ ] The **tenant guard** automatically scopes SELECT/UPDATE/DELETE on workspace-scoped tables by the active `workspace_id`, so a developer **cannot forget** the filter (`DATABASE_GUIDE.md` §6.2).
- [ ] A tenant-scoped query **never** returns rows from another workspace; any cross-workspace access requires an explicit, permission-gated, audited bypass.
- [ ] `workspace_id` is taken from the authenticated Workspace Context, **never** trusted from client input.
- [ ] The base repository enforces **bounded/paginated** reads (keyset over ULID preferred) and provides **batch reads** to avoid N+1 (Constitution §11).
- [ ] Soft-deleted rows (`deleted_at IS NOT NULL`) are excluded by default unless a query explicitly opts in (`DATABASE_GUIDE.md` §9).
- [ ] Connections use InnoDB/`utf8mb4`/`utf8mb4_0900_ai_ci` and a **UTC** session time zone (`DATABASE_GUIDE.md` §2).
- [ ] A migration/connection health probe is registered with the Core Kernel Health Checker.
- [ ] The module contains no ORM and owns no business tables.

### Related Documents
`DATABASE_GUIDE.md` · `DATABASE_ARCHITECTURE.md` (Phase 3) · `INDEXING_GUIDE.md` (Phase 3) ·
`ENTITY_CATALOG.md` · `ARCHITECTURE.md` · `MODULES.md` · `SECURITY_GUIDE.md` ·
`Core_Kernel.md` · `Installer.md`
