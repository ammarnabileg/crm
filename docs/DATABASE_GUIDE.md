# DATABASE GUIDE — HaHireAI

> **Status:** Draft (Phase 1) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md`. **Detailed model:** `DATABASE_ARCHITECTURE.md` (Phase 3).

---

## 0. Purpose & Scope

This document is the **conventions & standards rulebook** for how HaHireAI models
data — *how we model*, not *what we model*. It is the persistence-layer companion
to the Constitution and the Domain Model. It governs every table in every module,
including those not yet designed.

It deliberately does **not** enumerate entities, columns, or relationships. The
authoritative, entity-by-entity schema lives in `DATABASE_ARCHITECTURE.md`
(Phase 3); the global-vs-tenant entity list lives in `ENTITY_CATALOG.md`; the
detailed index plan lives in `INDEXING_GUIDE.md` (Phase 3). This guide tells
those documents — and every migration — the rules they MUST obey.

**Interpretation keywords** (**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**,
**MAY**) follow RFC 2119, consistent with the Constitution. A **MUST/MUST NOT**
rule is binding; a violation is a defect. Where this guide and the Constitution
ever disagree, the **Constitution wins** (Constitution §0).

---

## 1. Database Philosophy

The HaHireAI database is engineered to the same standard as the product: a single
coherent, observable system — never a pile of ad-hoc tables. Every schema
decision MUST serve these principles.

| # | Principle | What it means in practice |
|---|---|---|
| 1 | **Normalized** | Model facts once. Default to a sound normalized design (≈3NF). Denormalize only with a written, justified, reviewed reason — never by reflex. |
| 2 | **Expandable** | A new module or feature MUST be addable by *adding* tables/columns, never by redesigning existing ones (Constitution §3.10, §9). |
| 3 | **Tenant-aware** | Workspace isolation is designed into the schema, not bolted on. Every workspace-scoped table carries `workspace_id` (see §6). |
| 4 | **Auditable** | Significant state is observable and reconstructable. Timestamps are mandatory; the Audit module records who-did-what (see `DOMAIN_MODEL.md`, Audit module in `MODULES.md`). |
| 5 | **Maintainable** | Predictable, uniform conventions across all tables so any engineer can read any schema without surprises. One way to do each thing. |
| 6 | **Scalable** | Designed for thousands of concurrent workspaces: bounded queries, deliberate indexing, no unbounded growth without a retention/archival plan. |

**No temporary hacks.** A schema MUST NOT contain "we'll fix it later" columns,
placeholder tables, stringly-typed catch-alls, or shadow copies of data. If a
shortcut is unavoidable, it is recorded as an ADR (`/docs/adr/`) with a removal
plan — it is never silently merged.

---

## 2. Engine, Charset & Collation

| Aspect | Standard | Notes |
|---|---|---|
| RDBMS | **MySQL 8+** | Per Constitution §5 mandatory stack. No other engine. |
| Storage engine | **InnoDB** | Required for transactions, row-level locking, and foreign keys. MyISAM is forbidden. |
| Character set | **`utf8mb4`** | Full Unicode incl. emoji and all scripts (AR/EN product is bilingual — `UI_GUIDELINES.md`). `utf8` (3-byte) is forbidden. |
| Collation | **`utf8mb4_0900_ai_ci`** | MySQL 8 default Unicode collation; accent- and case-insensitive. Used unless a column documents a specific reason to differ. |
| Time zone | **UTC** | All `DATETIME`/`TIMESTAMP` values stored in UTC; presentation converts to the workspace/user time zone. |
| Row format | `DYNAMIC` | InnoDB default; supports large `utf8mb4` and `JSON` columns. |

Charset and collation MUST be set at the database and table level so new tables
inherit them. Per-column overrides MUST be the rare, documented exception.

---

## 3. Primary Key Policy — ULID (binding)

**Every table MUST have a primary key named `id` of type `CHAR(26)` holding a
ULID.** This is the single, platform-wide identifier policy. It applies to
**every** table — global catalogs, tenant data, join tables, and lookup tables
alike.

```sql
-- ILLUSTRATIVE ONLY (not a migration)
id  CHAR(26)  NOT NULL  PRIMARY KEY      -- e.g. 01HZX9P6K3QF7N2V8B4C5D6E7F
```

### Why ULID

| Property | Benefit to HaHireAI |
|---|---|
| **Globally unique** | Safe to generate anywhere — app layer, multiple workers, future extracted services — with no central sequence and no collision coordination. |
| **Time-sortable** | The leading 48 bits are a millisecond timestamp, so IDs sort roughly by creation time. Good index locality (near-monotonic inserts) without exposing a count. |
| **Distributed-safe** | Generated in application code (Shared Kernel, `/shared`), so inserts need no `AUTO_INCREMENT` round-trip and a module can later be extracted to its own service without re-keying. |
| **Opaque** | Reveals no row count or business volume (unlike sequential integers), reducing enumeration and competitive-intelligence leakage. |
| **URL-friendly** | Crockford base32, case-insensitive, 26 chars, no special characters — safe in routes (`/jobs/<id>`), filenames, and logs. |

### Rules

- IDs **MUST** be generated by the application (Shared Kernel ULID helper, `/shared`),
  **not** by the database. Schemas MUST NOT use `AUTO_INCREMENT`.
- The PK column **MUST** be named `id` and typed **`CHAR(26)`** — never `INT`,
  `BIGINT`, `BINARY(16)`, `UUID`, or a renamed key.
- **One policy, no mixing.** A table MUST NOT use auto-increment integers for some
  rows/tables and ULIDs for others. There is exactly one identifier scheme in the
  whole platform.
- Natural/composite keys MAY back a **unique constraint** (e.g. one application
  per user+job+workspace — see §6 and `DOMAIN_MODEL.md` Invariant 4) but **MUST
  NOT replace** the surrogate `id` primary key.
- ULIDs are stored as `CHAR(26)` (canonical text). Binary-16 storage is **not**
  used in Phase 1; any future change to binary storage would require an ADR and a
  Constitution-consistent amendment.

---

## 4. Foreign Keys & Relationships

Relationships are explicit, typed, indexed, and enforced. A relationship that
exists in the Domain Model MUST exist as a real foreign key in the schema.

| Rule | Requirement |
|---|---|
| Naming | A foreign key column is `<entity>_id` (singular entity, snake_case): `workspace_id`, `job_id`, `application_id`, `user_id` (Constitution §7). |
| Type | A foreign key column **MUST** match its target `id` type exactly: **`CHAR(26)`**. |
| Constraint | A real `FOREIGN KEY` constraint **MUST** be declared wherever a relationship exists. Implicit/"logical-only" relationships are not acceptable for in-database references. |
| Index | Every foreign key column **MUST** be indexed (InnoDB requires it; we also rely on it for join/filter performance — §13). |
| Referential actions | `ON DELETE` and `ON UPDATE` behavior **MUST** be stated explicitly per relationship (see below). Relying on the default silently is a defect. |
| Nullability | A foreign key is `NOT NULL` when the relationship is mandatory (e.g. `workspace_id` on tenant tables — §6) and nullable only when the relationship is genuinely optional. |

**Choosing referential actions.** State intent explicitly:

| Action | Use when |
|---|---|
| `ON DELETE RESTRICT` | Default for most references. Deletion of a still-referenced parent is a programming error and should be blocked. |
| `ON DELETE CASCADE` | Only for true *owned children* within one aggregate (e.g. an application's timeline entries die with the application). Use sparingly and document it. |
| `ON DELETE SET NULL` | Only for genuinely optional links where orphaning is meaningful (column must be nullable). |
| `ON UPDATE` | Normally `RESTRICT`/`NO ACTION` — `id` values are immutable ULIDs and never change, so cascading updates are unnecessary. |

> **Cross-module note.** A foreign key MUST NOT cross a module boundary into
> another module's privately-owned tables; see §11. Within a single module's owned
> tables, FKs are expected and encouraged.

---

## 5. Naming Conventions

These restate and extend the Constitution §7 table for the persistence layer.
They are binding; deviations are defects.

| Element | Convention | Example |
|---|---|---|
| Table name | **snake_case, plural** | `workspaces`, `workspace_members`, `applications` |
| Join (pivot) table | both entities, snake_case, alphabetical | `role_permissions`, `application_tags` |
| Column name | snake_case | `display_name`, `created_at` |
| Primary key | `id` | `id` (always `CHAR(26)` ULID — §3) |
| Foreign key | `<entity>_id` | `workspace_id`, `job_id` |
| Boolean column | `is_` / `has_` prefix, `TINYINT(1)` NOT NULL with default | `is_active`, `has_offer` |
| Created timestamp | `created_at` | `DATETIME` (UTC), set on insert |
| Updated timestamp | `updated_at` | `DATETIME` (UTC), set on every update |
| Soft-delete marker | `deleted_at` | `DATETIME` NULL (NULL = not deleted — see §9) |
| Money | `*_amount` (+ `*_currency`) | store minor units as integer; never floats for money |
| Lookup/code value | descriptive `*_code` or `*_key` | `status_code`, `setting_key` |

Additional rules:

- Names MUST be descriptive and unabbreviated (`description`, not `descr`).
  Reserved SQL words MUST NOT be used as identifiers.
- Boolean columns MUST read true/false from the prefix; a boolean MUST NOT be
  modeled as a nullable enum or a string.
- Timestamp columns MUST use the exact names `created_at` / `updated_at` /
  `deleted_at`. Every table **MUST** carry `created_at` and `updated_at`.
- Table and column names are English (engineering standard, Constitution §12),
  even though product content is bilingual.

---

## 6. Tenant Isolation (binding — the most important rule)

Workspace isolation is the single most important security invariant of the
system (`WORKSPACE_MODEL.md` §3; Constitution §3.5, §10). The database design and
the data-access layer enforce it together.

### 6.1 The `workspace_id` column

- Every **workspace-scoped** table **MUST** have a `workspace_id` column,
  `CHAR(26)`, **`NOT NULL`**, with a foreign key to `workspaces.id` and an index.
- `workspace_id` SHOULD lead composite indexes on tenant tables (e.g.
  `(workspace_id, created_at)`, `(workspace_id, status_code)`) because virtually
  every tenant query filters by it first (§13; detail in `INDEXING_GUIDE.md`).
- Uniqueness inside a tenant is scoped to the workspace: unique constraints on
  tenant tables **MUST** include `workspace_id` (e.g. a unique slug is unique
  *per workspace*, and the one-application-per-user-per-job rule is
  `UNIQUE (workspace_id, job_id, user_id)` — `DOMAIN_MODEL.md` Invariant 4).

### 6.2 The repository-layer tenant guard (mandatory)

Isolation is enforced at the **Infrastructure / repository layer**, not left to
callers (`ARCHITECTURE.md` §8; `WORKSPACE_MODEL.md` §3).

- Every query against a workspace-scoped table **MUST** be filtered by the
  current `workspace_id`. This is **non-negotiable** (Constitution §5).
- The tenant guard MUST be applied centrally (e.g. in the base repository / query
  builder for tenant entities), so that **SELECT, UPDATE, and DELETE** are all
  scoped by default and a developer cannot *forget* to add the filter.
- A query that needs to bypass tenant scoping (System Owner / Platform Context
  operations) **MUST** be an explicit, audited, permission-gated exception —
  never the default path, and never available inside ordinary workspace use cases.
- Accepting a `workspace_id` from client input as the trust source is forbidden;
  the active workspace comes from the authenticated **Workspace Context**
  (`WORKSPACE_MODEL.md` §7).

```php
// ILLUSTRATIVE ONLY — pattern, not the implementation
// Tenant repositories scope every query by the active workspace.
$sql = 'SELECT * FROM applications WHERE workspace_id = :workspaceId AND id = :id';
// Prepared statements only (Constitution §6); workspace_id is always bound.
```

> The concrete tenant-guard mechanism (base repository contract, query decorator)
> is specified in `DATABASE_ARCHITECTURE.md` and `SECURITY_GUIDE.md` and built by
> the **Database** module (Phase 8). This guide mandates *that* it exists and
> *that* every tenant query passes through it.

### 6.3 Global vs Workspace-scoped tables

There are two — and only two — scoping classes for a table:

| Class | Has `workspace_id`? | Examples (conceptual) | Authority |
|---|---|---|---|
| **Global** | No | `users`, the permission catalog, `plans`, global AI provider definitions, system settings | Enumerated in `ENTITY_CATALOG.md` |
| **Workspace-scoped** | Yes (`NOT NULL`) | jobs, applications, candidate profiles, pipelines, interviews, offers, memberships, roles, files, notifications, workspace settings, workspace audit | Everything not on the global list |

- **`ENTITY_CATALOG.md` is the single authoritative source** for which entities
  are global and which are workspace-scoped. This guide MUST NOT be read as that
  list; the table above is illustrative.
- **Default to workspace-scoped.** A table is global **only** if it is explicitly
  listed as global in `ENTITY_CATALOG.md`. When in doubt, it is workspace-scoped.
- A few records are *both* contexts (e.g. settings and audit exist at global and
  workspace levels); these are modeled per `ENTITY_CATALOG.md`, with the
  workspace-level rows carrying `workspace_id`.

---

## 7. No MySQL `ENUM` (binding)

**MySQL `ENUM` (and `SET`) columns are forbidden.** Fixed sets of states are
modeled in the application and, where needed, backed by a lookup table.

**Why we ban `ENUM`:**

- **Migration pain.** Adding/removing/renaming a value requires an `ALTER TABLE`
  that rewrites column metadata and is brittle across environments.
- **Poor extensibility.** Our system is "built for scale and change"
  (Constitution §3.10); state sets evolve, and `ENUM` makes evolution a schema
  event instead of a code/data change.
- **Ordering & comparison traps.** `ENUM` compares by hidden numeric index, not
  by value — a frequent source of subtle bugs.
- **Weak portability & tooling.** `ENUM` semantics are MySQL-specific and awkward
  for diffs, ORước-free repositories, and analytics.

**Do this instead:**

| Situation | Approach |
|---|---|
| A fixed set the **code** branches on (e.g. `ApplicationStatus`, `JobStatus`) | Model as a **PHP enum** (Constitution §6, §7). Persist its case as a short `VARCHAR`/`*_code` column (e.g. `status_code VARCHAR(50)`), validated by the application. |
| A set that needs **labels, metadata, ordering, or per-workspace extension** (e.g. pipeline stages, custom tags) | Use a **lookup table** with its own ULID `id` and a FK from the owner row. Pipeline stages are workspace-scoped data, not enum values (`DOMAIN_MODEL.md` §4.2). |
| Free, user-defined sets | Always a table — never an enum. |

The column stores the enum **case value as text**; the application enum is the
source of truth for the allowed set. Constraints/validation live in code (and,
where a lookup table is used, in the FK).

---

## 8. JSON Columns

MySQL `JSON` columns are permitted for **genuinely schemaless, app-owned blobs**
and forbidden for anything relational or queried as structured data.

**JSON is allowed (SHOULD be considered) for:**

- Flexible **settings / preferences / configuration** bags where keys vary and
  are read as a whole (`WORKSPACE_MODEL.md` §4 settings, per-workspace AI config).
- Opaque **metadata** captured from external systems (e.g. provider payload
  snapshots, webhook bodies) that the application treats as a unit.
- Denormalized **read snapshots** that are explicitly documented as caches of a
  source of truth (and invalidated accordingly — Constitution §11).

**JSON is NOT allowed for:**

- Anything you **filter, join, sort, or aggregate** on in normal operation. If
  you query it, it MUST be a real column (and indexed — §13).
- Modeling a **relationship**. Foreign keys go in FK columns with constraints
  (§4) — never as IDs buried inside a JSON document.
- **Fixed enumerations** (use §7) or booleans (use a `is_`/`has_` column).
- A dumping ground that hides growing structure. Recurring keys that the app
  depends on MUST be promoted to columns.

Rules: JSON columns MUST be documented (expected shape), MUST be validated by the
application before write, and SHOULD have a sensible default (`'{}'`/`'[]'`).
If a value inside JSON becomes query-critical, it MUST be migrated to a column.

---

## 9. Soft Delete vs Archive vs Hard Delete

HaHireAI distinguishes three distinct lifecycle operations. Business data is, by
default, **never** physically destroyed (`WORKSPACE_MODEL.md` §6). The
authoritative policy — including retention windows and per-entity rules — is
`ARCHIVING_POLICY.md`; this section states the modeling convention.

| Operation | Meaning | Schema mechanism | Visible by default? |
|---|---|---|---|
| **Soft delete** | Record removed from normal use but retained and recoverable. | `deleted_at DATETIME NULL` (NULL = live). Repositories exclude `deleted_at IS NOT NULL` by default. | No |
| **Archive** | Record intentionally set aside (e.g. closed job, archived workspace) but still a valid, queryable record. | A status/state column (e.g. `status_code`, or an `archived_at` timestamp where modeled) — **not** the same as soft delete. | Per business rules (often hidden from primary lists, still retrievable) |
| **Hard delete** | Physical row removal. | Actual `DELETE`. | n/a |

Rules:

- Business/tenant entities **MUST** support **soft delete** (`deleted_at`) and
  MUST NOT be hard-deleted in normal flows.
- The default read path (and the tenant guard, §6) **MUST** exclude soft-deleted
  rows unless a query explicitly opts in to include them.
- **Hard delete** is reserved for: legally mandated erasure (e.g. data-subject
  requests), transient/ephemeral rows (caches, expired tokens), and true junk —
  always per `ARCHIVING_POLICY.md` and the relevant compliance/security rules.
- Soft delete, archive, and hard delete are **different operations** and MUST NOT
  be conflated in code or schema (e.g. archiving MUST NOT set `deleted_at`).

---

## 10. No Derived or Duplicated Data

**Reference data; do not copy it** (`DOMAIN_MODEL.md` Invariant 7).

- A fact lives in **exactly one** place (its owning table) and is reached by
  foreign key elsewhere. Candidate, user, and job data MUST NOT be copied into
  other rows when a reference suffices.
- **Derived values** (counts, totals, "current stage", latest status) are
  **computed** from their source, not stored as a second source of truth.
- Duplication is permitted **only** as an explicit, documented optimization
  (a cache or read snapshot — see §8) with a defined **invalidation** strategy
  (Constitution §11). Such a column MUST be clearly named and documented as a
  cache, never treated as authoritative.
- **Snapshots that capture point-in-time facts on purpose** (e.g. the salary on
  an *Offer* at the moment it was extended) are legitimate domain data, not
  forbidden duplication — they record history, not a mirror of a live value. The
  distinction MUST be intentional and documented.

If you find yourself updating "the same" value in two tables, the design is
wrong: normalize it or formalize one side as an invalidated cache.

---

## 11. No Cross-Module Table Access

Modules own their tables. Data ownership follows module ownership
(Constitution §4, §9; `ARCHITECTURE.md` §3–§4).

- A module **MUST NOT** read from or write to another module's tables directly —
  by query, join, or foreign key into the other module's private schema
  (Constitution §4, Golden Rule 5).
- A module reaches another module's data **only** through its published
  **Contracts** (synchronous) or by reacting to its **domain events**
  (asynchronous) — never the database (`ARCHITECTURE.md` §4).
- Each table therefore belongs to exactly one owning module; that module's
  repositories are the only code that touches it.
- **Shared, cross-cutting tables** (e.g. Files, Notifications, Search index,
  Audit) are owned by their respective **shared-service** modules
  (`MODULES.md` §4) and accessed through those services' contracts — not by other
  modules reaching in.
- Because `id` values are ULIDs generated in-app (§3), one module MAY *store a
  reference* to another module's record as a plain `CHAR(26)` identifier and
  resolve it through a contract — without a database-level foreign key crossing
  the boundary. In-database foreign keys are confined to a single module's own
  tables (§4).

This is what lets a module later be extracted to its own service behind the same
contract (`ARCHITECTURE.md` §9) without rewriting consumers.

---

## 12. Migrations Philosophy

Schema changes are delivered through a **bespoke, forward-only migration engine** —
part of the **Database** module (Phase 8) — not a framework tool (Constitution §5;
`MODULES.md` Database module).

| Rule | Requirement |
|---|---|
| **No framework** | The migration engine is purpose-built native PHP. No Laravel/Doctrine/Phinx (Constitution §5). |
| **Forward-only** | Migrations move the schema **forward**. There are no "down"/rollback migrations in production; a mistake is fixed by a new forward migration (Constitution §14). |
| **Versioned & ordered** | Each migration is uniquely identified and applied in a deterministic order; applied migrations are tracked in a migrations ledger table. |
| **Runs cleanly from zero** | The full migration set **MUST** build a correct, current schema starting from an empty database — this is the source of truth for the schema and is exercised by the **Installer** (Phase 8) and CI. |
| **Idempotent application** | Re-running migrations applies only what is pending; already-applied migrations are skipped. |
| **Reviewed & gated** | Every migration goes through review and CI like any code; destructive changes require extra scrutiny and an ADR where warranted (Constitution §12, §14). |
| **Module-owned** | Each module ships its own migrations under its `Database/` folder; a global runner orchestrates them in dependency order (`MODULES.md`; Constitution §8). |
| **Tenant-safe** | Migrations operate on the shared schema; they MUST preserve the isolation invariants of §6 (e.g. never drop `workspace_id`, never widen scope silently). |

The concrete engine design, ledger format, and CLI live in
`DATABASE_ARCHITECTURE.md` and the Phase 8 implementation; this section fixes the
philosophy those MUST follow.

---

## 13. Indexing & Performance Basics

Performance is a design property, governed by Constitution §11. Full,
table-by-table index plans live in `INDEXING_GUIDE.md` (Phase 3); this section
states the non-negotiable basics every schema MUST satisfy.

- **Pagination is mandatory.** Every list/collection query **MUST** be bounded
  (paginated). Unbounded `SELECT *` over a growing table is forbidden
  (Constitution §11). Keyset/seek pagination over the time-sortable ULID `id`
  (or `(workspace_id, id)`) is preferred for large sets.
- **No N+1 queries.** Related data is fetched in batches or via deliberate joins,
  not in per-row loops (Constitution §11). Repositories expose batch reads.
- **Index every foreign key.** Every `<entity>_id` column is indexed (§4).
- **Index every hot filter.** Columns used in `WHERE`, `JOIN`, `ORDER BY`, or
  uniqueness checks are indexed. On tenant tables, composite indexes **MUST**
  lead with `workspace_id` (§6) to match the tenant-guarded access pattern.
- **Justify indexes.** Indexes are added deliberately and documented
  (`INDEXING_GUIDE.md`); needless indexes that only slow writes are avoided
  (Constitution §11).
- **Right-size types.** Use the narrowest correct type; store money as integer
  minor units (§5); avoid oversized `VARCHAR`; use `CHAR(26)` for IDs (§3).
- **Caching & async.** Hot reads use the defined cache layer with explicit
  invalidation, and heavy/AI work runs asynchronously off the request path
  (Constitution §11; `ARCHITECTURE.md` §8). The database is not asked to do work
  the queue should do.

Performance budgets (server-rendered p95 < 300 ms, internal API p95 < 200 ms;
Constitution §11) assume these rules hold.

---

## 14. Conformance Checklist (per table)

A new or changed table is conformant only if **all** apply:

- [ ] `id CHAR(26)` ULID primary key, app-generated, no `AUTO_INCREMENT` (§3).
- [ ] InnoDB, `utf8mb4` / `utf8mb4_0900_ai_ci` (§2).
- [ ] snake_case **plural** table name; snake_case columns; `is_`/`has_` booleans (§5).
- [ ] `created_at` and `updated_at` present; `deleted_at` where soft delete applies (§5, §9).
- [ ] If workspace-scoped: `workspace_id CHAR(26) NOT NULL`, FK + index; tenant-scoped uniqueness includes `workspace_id` (§6).
- [ ] Scope (global vs workspace) matches `ENTITY_CATALOG.md` (§6).
- [ ] Every foreign key is `CHAR(26)`, indexed, with explicit `ON DELETE`/`ON UPDATE` (§4).
- [ ] No MySQL `ENUM`/`SET`; fixed sets are app enums or lookup tables (§7).
- [ ] JSON only for schemaless/settings/metadata, never for queried or related data (§8).
- [ ] No derived/duplicated authoritative data; caches documented & invalidated (§10).
- [ ] No foreign key or query crossing into another module's tables (§11).
- [ ] Delivered via a forward-only, versioned migration that builds from zero (§12).
- [ ] FKs and hot filters indexed; list access paginated (§13).

---

### Related Documents

`PROJECT_CONSTITUTION.md` · `DOMAIN_MODEL.md` · `WORKSPACE_MODEL.md` ·
`ARCHITECTURE.md` · `MODULES.md` · `ENTITY_CATALOG.md` ·
`DATABASE_ARCHITECTURE.md` (Phase 3) · `INDEXING_GUIDE.md` (Phase 3) ·
`ARCHIVING_POLICY.md` · `SECURITY_GUIDE.md` · `RELATIONSHIP_MATRIX.md` ·
`ER_DIAGRAM.md`
