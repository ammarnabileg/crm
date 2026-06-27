# DATABASE ARCHITECTURE — HaHireAI

> **Status:** Adopted (Phase 3) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `ENTITY_CATALOG.md` (entities & scope), `DATABASE_GUIDE.md` (conventions).

This is the headline **data-model design** for HaHireAI — *what the data model
is*, expressed as the architecture every table, migration, and repository obeys.
It is not the implementation: there is **no SQL, no migration, and no code** here.
The authoritative entity-and-scope list is `ENTITY_CATALOG.md`; the modeling
rulebook is `DATABASE_GUIDE.md`. Where this document and either of those ever
disagree, **they win and this document is corrected**. Where any of the three and
the `PROJECT_CONSTITUTION.md` disagree, the **Constitution wins**.

**Interpretation keywords** (**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**,
**MAY**) follow RFC 2119, consistent with the Constitution and `DATABASE_GUIDE.md`.
A **MUST/MUST NOT** rule is binding; a violation is a defect.

---

## 1. Philosophy Recap

The database is engineered to the same standard as the product: a single coherent,
observable system, never a pile of ad-hoc tables (`DATABASE_GUIDE.md` §1). Six
principles govern every schema decision; this document is the proof that the
*model* honors them.

| # | Principle | How this architecture delivers it |
|---|---|---|
| 1 | **Normalized** | Each fact lives in exactly one owning table and is reached by foreign key (§5, §13). Default ≈3NF; denormalization only as a documented, invalidated cache (§7, §9 of `DATABASE_GUIDE.md`). |
| 2 | **Expandable** | A new module adds tables/columns; it never redesigns existing ones (§13). Scope, keys, and conventions are uniform, so new entities slot in without touching the core. |
| 3 | **Tenant-aware** | Workspace isolation is designed into the keys: every workspace-scoped table carries `workspace_id NOT NULL`, enforced by a central repository tenant guard (§8). |
| 4 | **Auditable** | Mandatory `created_at`/`updated_at`, immutable history tables, and the `audit_logs` trail make significant state reconstructable (§10, §11). |
| 5 | **Maintainable** | One identifier scheme (ULID), one naming style, one timestamp convention — any engineer reads any table without surprises (§3, §4). |
| 6 | **Scalable** | Time-sortable ULIDs give good insert locality; every FK and hot filter is indexed; lists are paginated; growth has a retention plan (§6, §9). Built for thousands of concurrent workspaces. |

**No temporary hacks.** The model MUST NOT contain "fix it later" columns,
placeholder tables, stringly-typed catch-alls, or shadow copies of data. An
unavoidable shortcut is recorded as an ADR under `/docs/adr/` with a removal plan,
never silently merged (`DATABASE_GUIDE.md` §1).

---

## 2. Entity Relationships Overview

HaHireAI's data model is a set of cohesive domains connected through a small number
of deliberate links. The domains correspond to the `ENTITY_CATALOG.md` groups; the
**full** entity-relationship picture lives in `ER_DIAGRAM.md` and the exhaustive
pairwise list in `RELATIONSHIP_MATRIX.md`. This section narrates how the domains
connect — it does not duplicate those documents.

```
            ┌──────────────────────── GLOBAL (no workspace_id) ───────────────┐
            │  users · permissions · plans · coupons · feature_flags          │
            │  ai_providers · ai_models · prompt_templates · system_settings  │
            │  system_audit_logs · metrics · health_checks · backups          │
            │  background_jobs · workspaces (tenant root, keyed by id)        │
            └────────────────────────────────────────────────────────────────┘
                  │ owner_user_id                         ▲ provider_id / plan_id
                  ▼                                       │ permission_id (catalog)
   IDENTITY ───────────────▶ WORKSPACE ◀───────────────── (all scoped FKs)
   users 1─* memberships *─1 workspaces 1─* { everything workspace-scoped }
   memberships *─* roles *─* permissions(catalog)
                  │
                  ▼  workspace_id (NOT NULL on every scoped table)
   RECRUITMENT ── jobs 1─* applications *─1 users
                  applications 1─* interviews · 1─0..1 offers · 1─* documents
                  applications *─1 candidate_profiles (per User+Workspace) ─1 users
                  pipelines 1─* pipeline_stages ; applications →current_stage_id
                  │
                  ▼  (capability requests, not table reads)
   AI ─────────── ai_sessions 1─* ai_messages ; ai_usage / ai_fallback_history
                  interview_sessions →ai_session_id ; workspace_ai_settings/keys
                  │
                  ▼
   COMMERCE ───── subscriptions *─1 plans ; invoices 1─* payments
                  workspace_feature_flags · usage_counters
```

**How the domains connect:**

- **Identity ↔ Workspace.** `users` (global) is the single human identity. The
  `memberships` table is the join that places a user inside a workspace, carrying
  status and — via `membership_roles` and `role_permissions` against the global
  `permissions` catalog — that user's effective authorization in that tenant
  (`DOMAIN_MODEL.md` §4.1). `workspaces` is the tenant root: it is global, keyed by
  `id`, and is the parent every workspace-scoped table points to.
- **Workspace ↔ Recruitment.** Every recruitment table (`jobs`, `applications`,
  `pipelines`, `interviews`, `offers`, `candidate_profiles`, …) hangs off
  `workspaces` through `workspace_id`. The aggregate spine is
  `jobs → applications → {interviews, offers, application_stage_history,
  application_documents}`, with `candidate_profiles` as the per-(User,Workspace)
  projection a user's notes, tags, and scorecards attach to (`DOMAIN_MODEL.md`
  §4.2, Invariant 3).
- **Recruitment ↔ AI.** Recruitment never embeds a provider; it requests AI
  *capabilities*. The link is data-level only: an `interview_sessions` row may
  reference an `ai_sessions` row (`ai_session_id`, nullable), and AI cost/usage is
  captured in `ai_usage` / `ai_fallback_history`. Cross-module reach is by contract
  or event, never a foreign key across the boundary (`DATABASE_GUIDE.md` §11).
- **Workspace ↔ AI configuration.** `workspace_ai_settings`, `workspace_ai_keys`,
  and `workspace_prompts` localize the global `ai_providers`/`ai_models`/
  `prompt_templates` catalogs per tenant.
- **Workspace ↔ Commerce.** `subscriptions` binds a workspace to a global `plans`
  row (one active per workspace); `invoices`, `payments`, `payment_methods`,
  `usage_counters`, and `workspace_feature_flags` are the per-tenant commercial
  records (`ENTITY_CATALOG.md` §9).
- **Platform Services** (`files`, `notifications`, `search_documents`,
  `audit_logs`, `workspace_settings`, `workspace_branding`) are shared, workspace-
  scoped services consumed by every domain through their owning modules' contracts
  (`MODULES.md` §4).

> Cross-domain references (e.g. an `interview_sessions.ai_session_id`) are stored
> as plain `CHAR(26)` identifiers resolved through a contract when they cross a
> **module** boundary; in-database foreign keys are confined to a single module's
> own tables (`DATABASE_GUIDE.md` §11).

---

## 3. Naming Rules

These mirror `DATABASE_GUIDE.md` §5 and Constitution §7. They are binding;
deviations are defects.

| Element | Convention | Example |
|---|---|---|
| Table name | **snake_case, plural** | `workspaces`, `applications`, `pipeline_stages` |
| Join (pivot) table | both entities, snake_case | `role_permissions`, `membership_roles`, `candidate_profile_tags` |
| Column name | snake_case, descriptive, unabbreviated | `display_name`, `current_stage_id` |
| Primary key | `id` | always `CHAR(26)` ULID (§4) |
| Foreign key | `<entity>_id` (singular target) | `workspace_id`, `job_id`, `application_id` |
| Boolean | `is_` / `has_` prefix, `TINYINT(1)` NOT NULL + default | `is_active`, `has_offer`, `use_platform_key` |
| Created timestamp | `created_at` | `DATETIME` (UTC), set on insert |
| Updated timestamp | `updated_at` | `DATETIME` (UTC), set on every update |
| Soft-delete marker | `deleted_at` | `DATETIME` NULL (NULL = live — §9) |
| Money | `*_amount` (+ `*_currency`) | integer **minor units**, never floats |
| Lookup/code value | `*_code` / `*_key` | `status_code`, `setting_key`, `connector_key` |

Additional binding rules:

- Reserved SQL words MUST NOT be used as identifiers; names are **English** even
  though product content is bilingual AR/EN (`DATABASE_GUIDE.md` §5).
- A boolean MUST read true/false from its prefix — never modeled as a nullable
  enum or a string.
- Every table MUST carry `created_at` and `updated_at`; immutable/append-only
  tables are the **only** exception and carry neither `updated_at` nor
  `deleted_at` (§11; `ENTITY_CATALOG.md` §1).
- Engine and encoding are fixed platform-wide: **InnoDB**, **`utf8mb4`**,
  **`utf8mb4_0900_ai_ci`**, **UTC**, row format `DYNAMIC` (`DATABASE_GUIDE.md` §2).

---

## 4. Primary Keys — ULID `CHAR(26)`

**Every table MUST have a primary key named `id`, type `CHAR(26)`, holding a
ULID** — global catalogs, tenant data, pivots, and lookups alike. This is the one
platform-wide identifier policy (`DATABASE_GUIDE.md` §3).

```
id  CHAR(26)  NOT NULL  PRIMARY KEY      -- e.g. 01HZX9P6K3QF7N2V8B4C5D6E7F
```

**Rules:**

- IDs **MUST** be generated by the application (Shared Kernel ULID helper,
  `/shared`), **not** the database. Schemas **MUST NOT** use `AUTO_INCREMENT`.
- The PK column **MUST** be named `id` and typed **`CHAR(26)`** — never `INT`,
  `BIGINT`, `BINARY(16)`, `UUID`, or a renamed key.
- **One policy, no mixing.** No table uses auto-increment integers for some rows
  and ULIDs for others. There is exactly one identifier scheme in the platform.
- A natural/composite key MAY back a **unique constraint** (§7) but **MUST NOT
  replace** the surrogate `id`.
- ULIDs are stored as canonical text `CHAR(26)` in Phase 1; any move to binary-16
  storage would require an ADR and a Constitution-consistent amendment.

**Rationale (why ULID).**

| Property | Benefit |
|---|---|
| **Globally unique** | Generated anywhere — app, workers, future extracted services — with no central sequence and no collision coordination. |
| **Time-sortable** | Leading 48 bits are a millisecond timestamp; near-monotonic inserts give good index locality without exposing a row count. |
| **Distributed-safe** | App-generated, so inserts need no `AUTO_INCREMENT` round-trip and a module can later be extracted without re-keying (`MODULES.md` §5). |
| **Opaque** | Reveals no business volume (unlike sequential integers), reducing enumeration and competitive-intelligence leakage. |
| **URL-friendly** | Crockford base32, 26 chars, case-insensitive, no special characters — safe in routes, filenames, and logs. |

---

## 5. Foreign Keys

Relationships are explicit, typed, indexed, and enforced. A relationship that
exists in the Domain Model **MUST** exist as a real `FOREIGN KEY` in the schema
(`DATABASE_GUIDE.md` §4); "logical-only" in-database references are not acceptable.

| Rule | Requirement |
|---|---|
| Naming | `<entity>_id`, singular target, snake_case: `workspace_id`, `job_id`, `application_id`, `user_id`. |
| Type | **MUST** match the target `id` exactly: **`CHAR(26)`**. |
| Constraint | A real `FOREIGN KEY` **MUST** be declared wherever the relationship exists *within a module's own tables*. |
| Index | Every foreign-key column **MUST** be indexed (InnoDB requires it; we rely on it for joins/filters — §6). |
| Nullability | `NOT NULL` when the relationship is mandatory (e.g. `workspace_id` on tenant tables — §8); nullable only when genuinely optional (e.g. `interview_sessions.ai_session_id`, `pipelines.job_id` for templates, `error_events.workspace_id`). |
| Referential actions | `ON DELETE` and `ON UPDATE` **MUST** be stated explicitly per relationship. Relying on the silent default is a defect. |

**Referential-action policy (by relationship type).** Intent is declared, not
defaulted:

| Action | Use when | Examples in this model |
|---|---|---|
| `ON DELETE RESTRICT` | **Default** for most references. Deleting a still-referenced parent is a programming error and is blocked. | `applications.job_id`, `subscriptions.plan_id`, `ai_models.provider_id`, `memberships.user_id`. |
| `ON DELETE CASCADE` | Only for true **owned children** inside one aggregate, where the child has no meaning without its parent. Use sparingly and document it. | `job_questions → jobs`, `pipeline_stages → pipelines`, `ai_messages → ai_sessions`, `role_permissions → roles`, an application's `application_documents` / timeline entries. |
| `ON DELETE SET NULL` | Only for a genuinely **optional** link where orphaning is meaningful; the column MUST be nullable. | `files.folder_id` when a folder is removed; `interview_sessions.ai_session_id` if a session record is purged. |
| `ON UPDATE` | Normally **`RESTRICT`/`NO ACTION`** — `id` values are immutable ULIDs and never change, so cascading updates are unnecessary. | All relationships. |

> **Module boundary.** A foreign key **MUST NOT** cross into another module's
> privately-owned tables. Cross-module links are stored as plain `CHAR(26)`
> identifiers and resolved via contracts/events (`DATABASE_GUIDE.md` §11). Within
> one module's own tables, FKs are expected and encouraged. Soft-deleted parents
> (§9) are excluded by the read path, not by `ON DELETE` — `deleted_at` is not a
> physical delete.

---

## 6. Indexes (strategy)

Per-table index plans are the job of `INDEXING_GUIDE.md`; this section fixes the
**strategy** every table follows (`DATABASE_GUIDE.md` §13).

- **Every foreign key is indexed.** Required by InnoDB and relied on for join and
  filter performance (§5).
- **Tenant-leading composites.** On workspace-scoped tables, composite indexes
  **MUST** lead with `workspace_id`, because virtually every tenant query filters
  by it first (§8). Typical shapes: `(workspace_id, created_at)`,
  `(workspace_id, status_code)`, `(workspace_id, <fk>)`.
- **Unique constraints** encode invariants in the schema. The headline ones:

  | Table | Unique key | Enforces |
  |---|---|---|
  | `memberships` | `(workspace_id, user_id)` | one membership per user per workspace (`ENTITY_CATALOG.md` §3). |
  | `applications` | `(workspace_id, job_id, user_id)` | one application per user per job per workspace (`DOMAIN_MODEL.md` Invariant 4; `DATABASE_GUIDE.md` §6.1). Re-application creates history, not a duplicate. |
  | `candidate_profiles` | `(workspace_id, user_id)` | one profile view per user per workspace (Invariant 3). |
  | `candidate_profile_tags` | `(candidate_profile_id, tag_id)` | a tag is applied to a profile at most once. |
  | `role_permissions` | `(role_id, permission_id)` | no duplicate grant. |
  | `membership_roles` | `(membership_id, role_id)` | no duplicate role assignment. |
  | `permissions` | `(key)` | the catalog key is globally unique. |
  | `plans` | `(key)` | plan key globally unique. |

  Tenant-scoped uniqueness **MUST** include `workspace_id` (e.g. a per-workspace
  slug, a per-workspace `tags.name`). A unique constraint never replaces the
  surrogate `id` (§4).
- **Composite indexes for hot filters.** Columns used in `WHERE`, `JOIN`,
  `ORDER BY`, or uniqueness checks are indexed; needless indexes that only slow
  writes are avoided and every index is justified in `INDEXING_GUIDE.md`.
- **FULLTEXT for search projections.** Unified search is served by the
  `search_documents` projection (workspace-scoped, `entity_type` + `entity_id`),
  which carries the FULLTEXT index — search does not scan business tables directly
  (`ENTITY_CATALOG.md` §4; Search module, `MODULES.md` §4).
- **Keyset pagination.** Large lists page over the time-sortable `id`
  (or `(workspace_id, id)`) rather than large `OFFSET`s; unbounded `SELECT *` over
  a growing table is forbidden (`DATABASE_GUIDE.md` §13).

---

## 7. Constraints

The model expresses as much truth as possible in the schema and enforces the rest
in the domain layer (`DATABASE_GUIDE.md` §6, §7).

- **NOT NULL `workspace_id` on every workspace-scoped table.** This is the
  backbone of tenancy (§8). `workspaces` itself is the one exception: it is the
  tenant root, keyed by `id`, and carries `owner_user_id`, not `workspace_id`
  (`ENTITY_CATALOG.md` §2).
- **Unique keys** encode the invariants listed in §6. Every tenant-scoped unique
  key includes `workspace_id`.
- **No MySQL `ENUM`/`SET`.** Fixed state sets are forbidden as column types
  (`DATABASE_GUIDE.md` §7). Instead:
  - A set the **code branches on** (e.g. `JobStatus`, `ApplicationStatus`,
    `OfferStatus`, `MembershipStatus` per `STATE_DIAGRAMS.md` §2–§10) is a **PHP
    enum** persisted as a short `status_code VARCHAR` validated by the application.
  - A set needing **labels, ordering, or per-workspace extension** (e.g. pipeline
    stages) is a **lookup table** with its own ULID `id` and a FK from the owner —
    `pipeline_stages` is workspace data, not enum values (`DOMAIN_MODEL.md` §4.2).
- **Check-like rules live in the domain.** MySQL `CHECK` is not used as the
  enforcement surface; value ranges, state-transition legality, and conditional
  requirements are enforced in the owning module's Domain layer (the state
  machines in `STATE_DIAGRAMS.md`), with the schema enforcing structure (types,
  nullability, uniqueness, foreign keys).
- **JSON is constrained by policy, not type.** `JSON` columns are permitted only
  for genuinely schemaless, app-owned blobs (settings, captured external payloads,
  documented read snapshots) and forbidden for anything filtered, joined, sorted,
  related, or enumerated — those MUST be real columns (`DATABASE_GUIDE.md` §8).
  Recurring keys the app depends on MUST be promoted to columns.

---

## 8. Tenancy

Workspace isolation is the single most important security invariant of the system
(`WORKSPACE_MODEL.md` §3; Constitution §3.5, §10). The schema and the data-access
layer enforce it together.

**The `workspace_id` rule.**

- Every **workspace-scoped** table **MUST** have `workspace_id CHAR(26) NOT NULL`,
  a foreign key to `workspaces.id`, and an index; tenant-scoped uniqueness
  includes `workspace_id` (§6, §7).
- A workspace-scoped row is **invisible** to every other workspace. Uniqueness,
  ordering, and listing are all scoped to the tenant.

**The repository tenant guard (mandatory).**

- Isolation is enforced at the **Infrastructure / repository layer**, not left to
  callers (`DATABASE_GUIDE.md` §6.2; `ARCHITECTURE.md`).
- The guard is applied **centrally** (base repository / query builder for tenant
  entities) so that **SELECT, UPDATE, and DELETE** are scoped by the active
  `workspace_id` **by default** — a developer cannot forget the filter.
- The active workspace comes from the authenticated **Workspace Context**
  (`WORKSPACE_MODEL.md` §7); a `workspace_id` from client input is **never** the
  trust source.
- Any query that must bypass tenant scope (System Owner / Platform Context) is an
  **explicit, audited, permission-gated** exception (`system.*`), never the default
  path and never reachable from ordinary workspace use cases.
- All access uses prepared statements; `workspace_id` is always bound
  (Constitution §6).

**Global vs Workspace split (cite `ENTITY_CATALOG.md` §2).** There are exactly two
scoping classes. The **global** tables (no `workspace_id`) are precisely:

> `users`, `user_sessions`, `password_resets`, `remember_tokens`, `permissions`,
> `plans`, `coupons`, `feature_flags`, `ai_providers`, `ai_models`,
> `prompt_templates` (global library), `system_settings`, `system_audit_logs`,
> `error_events` (system), `metrics`, `health_checks`, `backups`,
> `background_jobs`, and `workspaces` (the tenant root itself).

**Everything else is workspace-scoped; when in doubt, a table is workspace-scoped.**
A few records exist in *both* contexts (settings and audit at global and workspace
levels; `error_events` and `alerts` with nullable `workspace_id`); these are
modeled per `ENTITY_CATALOG.md`, with the workspace-level rows carrying
`workspace_id`. `access_tokens` may be user-, workspace-, or system-scoped via
nullable `user_id`/`workspace_id` and a `type` discriminator (`ENTITY_CATALOG.md`
§8). `ENTITY_CATALOG.md` is the single authoritative source for scope; this
document does not restate it as a list beyond the above.

---

## 9. Soft-Delete Policy (summary)

Business data is, by default, **never physically destroyed**
(`WORKSPACE_MODEL.md` §6). HaHireAI distinguishes three distinct operations; the
authoritative per-entity rules and retention windows live in `ARCHIVING_POLICY.md`.
This is the modeling summary (`DATABASE_GUIDE.md` §9).

| Operation | Meaning | Mechanism | Default visibility |
|---|---|---|---|
| **Soft delete** | Removed from normal use, retained and recoverable. | `deleted_at DATETIME NULL` (NULL = live). Repositories exclude `deleted_at IS NOT NULL` by default. | Hidden |
| **Archive** | Intentionally set aside but still a valid, queryable record (e.g. a closed `jobs` row, an archived workspace). | A **state column** (`status_code`) or distinct `archived_at` — **not** `deleted_at`. | Per business rules (often hidden from primary lists, still retrievable) |
| **Hard delete** | Physical row removal. | Actual delete. | n/a |

Rules:

- Tables marked **(soft)** in `ENTITY_CATALOG.md` (e.g. `users`, `workspaces`,
  `memberships`, `roles`, `folders`, `files`, `jobs`, `applications`,
  `candidate_notes`, `offers`, `employees`) carry `deleted_at` and **MUST NOT** be
  hard-deleted in normal flows.
- The default read path **and** the tenant guard (§8) exclude soft-deleted rows
  unless a query explicitly opts in.
- **Soft delete, archive, and hard delete are different operations** and MUST NOT
  be conflated — archiving MUST NOT set `deleted_at`.
- **Immutable/append-only** tables (§11) are never soft-deleted; their retention is
  governed by `ARCHIVING_POLICY.md`.
- **Hard delete** is reserved for legally mandated erasure (data-subject requests),
  transient rows (caches, expired tokens — e.g. `password_resets`,
  `remember_tokens`), and true junk, always per `ARCHIVING_POLICY.md`.

---

## 10. Audit Policy (summary)

Significant state is observable and reconstructable (`DATABASE_GUIDE.md` §1
Principle 4). The full policy is `AUDIT_POLICY.md`, and the catalog of audited
events is the Phase 4 `AUDIT_EVENTS.md`. This is the modeling summary.

- **Two audit trails, both immutable** (`ENTITY_CATALOG.md` §4):
  - `audit_logs` — **workspace-scoped**: `workspace_id`, `actor_user_id`, `action`,
    `entity_type`/`entity_id`, `ip`, `changes` (JSON). Records who-did-what inside
    a tenant.
  - `system_audit_logs` — **global**: platform-level actions by System Owners
    (`system.*`), including every tenant-scope-bypass exception (§8).
- Audit rows are **append-only**: no `updated_at`, no `deleted_at`; they are never
  edited or soft-deleted (§11).
- The Audit module owns these tables; other modules emit events and **never write
  another module's audit rows directly** (`MODULES.md` §4; `DATABASE_GUIDE.md`
  §11).
- The `changes` JSON captures before/after deltas as an opaque, app-validated blob
  (§7); fields queried for filtering (actor, action, entity, time) are real,
  indexed columns, not JSON lookups.

---

## 11. History / Versioning Policy (summary)

Some entities must keep history; some tables are append-only by nature. The full
policy is `VERSIONING_POLICY.md`. This is the modeling summary.

- **Versioned entities** keep prior states. The `*_versions` pattern stores an
  **immutable snapshot per publish/edit**: `jobs` (root) is versioned via
  `job_versions` (immutable), each snapshot referencing `job_id`
  (`ENTITY_CATALOG.md` §5). Tables flagged **versioned** in the catalog —
  `jobs`, `templates`, `prompt_templates`, `workspace_prompts`, `workflows` — keep
  history per `VERSIONING_POLICY.md` (in a sibling versions table and/or an
  internal `version` column where the catalog notes one).
- **Immutable / append-only** tables carry **neither `updated_at` nor
  `deleted_at`** and are never mutated after insert (`ENTITY_CATALOG.md` §1). These
  are, per the catalog: `job_versions`, `application_stage_history`, `audit_logs`,
  `system_audit_logs`, `ai_usage`, `ai_fallback_history`,
  `workflow_execution_steps`, `webhook_deliveries`, `payments`, `metrics`,
  `health_checks`.
- **History is recorded, not mirrored.** Movement history
  (`application_stage_history`: from/to stage, `moved_by`) and point-in-time
  snapshots (e.g. the salary on an `offers` row when extended) are legitimate
  domain facts — they record history, **not** a live copy of a current value, and
  the distinction is intentional and documented (`DATABASE_GUIDE.md` §10).
- **"Current" is derived, not stored as a second source of truth.** An
  application's current stage is reachable via `applications.current_stage_id`
  (a reference), while the *journey* lives in `application_stage_history`; the
  history table is never the place the live state is read from.

---

## 12. Migrations

Schema is delivered through a **bespoke, forward-only migration engine** — part of
the **Database** module (Phase 8) — not a framework tool (`DATABASE_GUIDE.md` §12;
`MODULES.md` §2). The concrete engine, ledger format, and CLI are Phase 8
implementation; this fixes what the *model* requires of them.

| Rule | Requirement |
|---|---|
| **No framework** | Purpose-built native PHP; no Laravel/Doctrine/Phinx (Constitution §5). |
| **Forward-only** | Migrations move the schema forward; there are **no down/rollback** migrations in production — a mistake is fixed by a new forward migration (Constitution §14). |
| **Versioned & ordered** | Each migration is uniquely identified and applied in deterministic order; applied migrations are tracked in a **migrations ledger** table. |
| **Runs cleanly from zero** | The full set **MUST** build a correct, current schema from an **empty database** — this is the schema's source of truth, exercised by the Installer and CI. |
| **Idempotent** | Re-running applies only what is pending; applied migrations are skipped. |
| **Module-owned** | Each module ships its own migrations under its `Database/` folder; a global runner orchestrates them in dependency order (`MODULES.md` §5). |
| **Tenant-safe** | Migrations operate on the shared schema and **MUST** preserve the §8 isolation invariants (never drop `workspace_id`, never silently widen scope). |
| **Reviewed & gated** | Every migration is reviewed and CI-gated like code; destructive changes get extra scrutiny and an ADR where warranted. |

**Seed of core data.** A from-zero build seeds only the minimum the platform needs
to operate, all under the ULID/no-AUTO_INCREMENT rules:

- the **permissions catalog** (`permissions` — the static registry of capability
  keys; `PERMISSION_MODEL.md`), so authorization works on first boot;
- baseline **global catalogs/settings** as required (e.g. `system_settings`,
  initial `ai_providers`/`ai_models` definitions, `plans` are configured, never
  hard-coded in code — `ENTITY_CATALOG.md` §6, §9);
- the **first System Owner** — created interactively by the **Installer**
  (zero-touch browser install, Phase 8; `MODULES.md`), as a `User` granted
  `system.*` permissions. There is **no reserved role and no seeded workspace
  role**; a workspace owner defines roles themselves (`WORKSPACE_MODEL.md` §6).

Seeds MUST be idempotent and part of the from-zero guarantee: install on an empty
database yields a working platform with exactly one System Owner and a complete
permission catalog.

---

## 13. Self-Review Checklist (Phase 3 gate)

The model is conformant only if **all** hold (consolidating `DATABASE_GUIDE.md`
§14 and `ENTITY_CATALOG.md` §11):

- [ ] **No derived/duplicated authoritative data.** Counts, totals, "current
      status", and "latest stage" are computed from their source; any cache is
      named as such and has a documented invalidation strategy (§7, §11).
- [ ] **No circular references.** The relationship graph has no ownership cycle;
      reactions that would create one use events, not FKs (`MODULES.md` §5).
- [ ] **Every relationship is logical and real.** Each Domain-Model relationship
      is a real, indexed FK within its module, with explicit `ON DELETE`/
      `ON UPDATE`; cross-module links are `CHAR(26)` references via contracts (§5).
- [ ] **A module can be added without redesign.** New entities slot in by *adding*
      tables/columns under the same conventions; no existing table is reshaped
      (§1 Principle 2).
- [ ] Every workspace-scoped table carries `workspace_id CHAR(26) NOT NULL`, FK +
      index; tenant-scoped uniqueness includes `workspace_id` (§6, §8).
- [ ] Every table has an `id CHAR(26)` ULID PK, app-generated, no
      `AUTO_INCREMENT`; one identifier scheme platform-wide (§4).
- [ ] Scope (global vs workspace) for every table matches `ENTITY_CATALOG.md` §2
      exactly (§8).
- [ ] `candidate_profiles` is per-(User,Workspace); there is **no** global
      candidate table (Invariant 3).
- [ ] No table depends on a fixed role name (`DOMAIN_MODEL.md` Invariant 5).
- [ ] No MySQL `ENUM`/`SET`; fixed sets are PHP enums (`status_code`) or lookup
      tables (§7).
- [ ] Immutable/append-only tables are identified and carry neither `updated_at`
      nor `deleted_at`; soft-delete vs archive vs hard-delete is classified per
      `ARCHIVING_POLICY.md` (§9, §11).
- [ ] The full migration set builds the schema cleanly from zero and seeds the
      permission catalog plus the first System Owner (§12).

---

### Related Documents

`ENTITY_CATALOG.md` · `DATABASE_GUIDE.md` · `DOMAIN_MODEL.md` ·
`WORKSPACE_MODEL.md` · `STATE_DIAGRAMS.md` · `MODULES.md` ·
`ER_DIAGRAM.md` · `RELATIONSHIP_MATRIX.md` · `INDEXING_GUIDE.md` ·
`AUDIT_POLICY.md` · `ARCHIVING_POLICY.md` · `VERSIONING_POLICY.md` ·
`AUDIT_EVENTS.md` (Phase 4) · `PROJECT_CONSTITUTION.md` · `SECURITY_GUIDE.md`
