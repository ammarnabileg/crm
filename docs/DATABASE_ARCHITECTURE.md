# DATABASE ARCHITECTURE — HaHireAI

> **Status:** Adopted (Phase 3) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `ENTITY_CATALOG.md` (entities & scope), `DATABASE_GUIDE.md` (conventions).

This is the headline **data-model design** for HaHireAI — *what the data model
is*, expressed as the architecture every table, migration, and repository obeys.
It is not the implementation: there is **no SQL, no migration, no code** here. The
authoritative entity-and-scope list is `ENTITY_CATALOG.md`; the modeling rulebook
is `DATABASE_GUIDE.md`. Where this document disagrees with either, **they win**;
where any of the three disagrees with `PROJECT_CONSTITUTION.md`, the **Constitution
wins**. **MUST / MUST NOT / SHOULD / MAY** follow RFC 2119; a MUST violation is a
defect.

---

## 1. Philosophy Recap

The database is engineered to the product's standard: one coherent, observable
system, never a pile of ad-hoc tables (`DATABASE_GUIDE.md` §1). Six principles
govern every decision; this document is the proof the *model* honors them.

| # | Principle | How this architecture delivers it |
|---|---|---|
| 1 | **Normalized** | Each fact lives in one owning table, reached by FK (§5). Default ≈3NF; denormalization only as a documented, invalidated cache. |
| 2 | **Expandable** | A new module *adds* tables/columns; it never redesigns existing ones (§13). Scope, keys, and conventions are uniform. |
| 3 | **Tenant-aware** | Isolation is in the keys: every workspace-scoped table carries `workspace_id NOT NULL`, enforced by a central tenant guard (§8). |
| 4 | **Auditable** | Mandatory timestamps, immutable history tables, and the `audit_logs` trail make state reconstructable (§10, §11). |
| 5 | **Maintainable** | One ID scheme (ULID), one naming style, one timestamp convention — any engineer reads any table without surprises (§3, §4). |
| 6 | **Scalable** | Time-sortable ULIDs give insert locality; every FK and hot filter is indexed; lists paginate; growth has a retention plan (§6, §9). |

**No temporary hacks** — no "fix it later" columns, placeholder tables, stringly-
typed catch-alls, or shadow copies. An unavoidable shortcut is an ADR under
`/docs/adr/` with a removal plan, never silently merged.

---

## 2. Entity Relationships Overview

HaHireAI's model is cohesive domains connected by a few deliberate links,
mirroring the `ENTITY_CATALOG.md` groups. The **full** ER picture is `ER_DIAGRAM.md`
and the exhaustive pairwise list is `RELATIONSHIP_MATRIX.md`; this section narrates
the connections without duplicating them.

```
   GLOBAL (no workspace_id): users · permissions · plans · ai_providers ·
     ai_models · prompt_templates · system_settings · system_audit_logs ·
     metrics · health_checks · backups · background_jobs · workspaces (tenant root)
        │ owner_user_id                              ▲ provider_id / plan_id / permission_id
        ▼                                            │
   IDENTITY      users 1─* memberships *─1 workspaces ; memberships *─* roles *─* permissions
        │  (workspace_id NOT NULL on every scoped table)
        ▼
   RECRUITMENT   jobs 1─* applications *─1 users ; applications 1─* interviews ·
                 1─0..1 offers · 1─* documents/history ; pipelines 1─* pipeline_stages ;
                 applications *─1 candidate_profiles (per User+Workspace) ─1 users
        ▼  (capability requests, not table reads)
   AI            ai_sessions 1─* ai_messages ; interview_sessions →ai_session_id ;
                 ai_usage / ai_fallback_history ; workspace_ai_settings / _keys / _prompts
        ▼
   COMMERCE      subscriptions *─1 plans ; invoices 1─* payments ;
                 workspace_feature_flags · usage_counters
```

- **Identity ↔ Workspace.** `users` (global) is the single human identity;
  `memberships` is the join placing a user in a workspace, carrying status and —
  via `membership_roles`, `role_permissions` against the global `permissions`
  catalog, and optional `membership_permissions` — that user's effective authority
  in that tenant (`DOMAIN_MODEL.md` §4.1). `workspaces` is the global tenant root,
  keyed by `id`; every scoped table points to it.
- **Workspace ↔ Recruitment.** All recruitment tables hang off `workspaces` via
  `workspace_id`. The aggregate spine is `jobs → applications → {interviews,
  offers, application_stage_history, application_documents}`, with
  `candidate_profiles` as the per-(User,Workspace) projection notes, tags, and
  scorecards attach to (`DOMAIN_MODEL.md` §4.2, Invariant 3).
- **Recruitment ↔ AI.** Recruitment requests AI *capabilities*, never embedding a
  provider. The only data link is `interview_sessions.ai_session_id` (nullable →
  `ai_sessions`); usage/cost live in `ai_usage` and `ai_fallback_history`. Cross-
  module reach is by contract or event, not a FK across the boundary
  (`DATABASE_GUIDE.md` §11).
- **Workspace ↔ AI config & Commerce.** `workspace_ai_settings`/`_keys`/`_prompts`
  localize the global `ai_providers`/`ai_models`/`prompt_templates` catalogs;
  `subscriptions` binds a workspace to a global `plans` row (one active per
  workspace), with `invoices`, `payments`, `payment_methods`, `usage_counters`, and
  `workspace_feature_flags` as the per-tenant commercial records.
- **Platform Services.** `files`, `notifications`, `search_documents`,
  `audit_logs`, `workspace_settings`, `workspace_branding` are shared, workspace-
  scoped services consumed via their owning modules' contracts (`MODULES.md` §4).

---

## 3. Naming Rules

Mirrors `DATABASE_GUIDE.md` §5 and Constitution §7; binding.

| Element | Convention | Example |
|---|---|---|
| Table | **snake_case, plural** | `workspaces`, `applications`, `pipeline_stages` |
| Pivot table | both entities, snake_case | `role_permissions`, `membership_roles`, `candidate_profile_tags` |
| Column | snake_case, descriptive, unabbreviated | `display_name`, `current_stage_id` |
| Primary key | `id` | always `CHAR(26)` ULID (§4) |
| Foreign key | `<entity>_id` (singular target) | `workspace_id`, `job_id`, `application_id` |
| Boolean | `is_`/`has_` prefix, `TINYINT(1)` NOT NULL + default | `is_active`, `has_offer`, `use_platform_key` |
| Created / Updated | `created_at` / `updated_at` | `DATETIME` (UTC), insert / every update |
| Soft-delete marker | `deleted_at` | `DATETIME` NULL (NULL = live — §9) |
| Money | `*_amount` (+ `*_currency`) | integer **minor units**, never floats |
| Lookup/code | `*_code` / `*_key` | `status_code`, `setting_key`, `connector_key` |

- Reserved SQL words MUST NOT be identifiers; names are **English** though product
  content is bilingual AR/EN.
- A boolean reads true/false from its prefix — never a nullable enum or string.
- Every table carries `created_at` and `updated_at`; immutable/append-only tables
  are the **only** exception, carrying neither `updated_at` nor `deleted_at` (§11).
- Engine/encoding fixed platform-wide: **InnoDB**, **`utf8mb4`**,
  **`utf8mb4_0900_ai_ci`**, **UTC**, row format `DYNAMIC` (`DATABASE_GUIDE.md` §2).

---

## 4. Primary Keys — ULID `CHAR(26)`

**Every table MUST have a primary key named `id`, type `CHAR(26)`, holding a
ULID** — catalogs, tenant data, pivots, and lookups alike (`DATABASE_GUIDE.md` §3).

```
id  CHAR(26)  NOT NULL  PRIMARY KEY      -- e.g. 01HZX9P6K3QF7N2V8B4C5D6E7F
```

- IDs **MUST** be generated by the application (Shared Kernel ULID helper,
  `/shared`), not the database. Schemas **MUST NOT** use `AUTO_INCREMENT`.
- The PK **MUST** be named `id` and typed `CHAR(26)` — never `INT`, `BIGINT`,
  `BINARY(16)`, `UUID`, or a renamed key. **One scheme, no mixing.**
- A natural/composite key MAY back a **unique constraint** (§7) but **MUST NOT
  replace** the surrogate `id`.
- Stored as canonical text in Phase 1; any move to binary-16 needs an ADR.

**Rationale.** ULIDs are **globally unique** (generate anywhere, no central
sequence), **time-sortable** (48-bit ms prefix → near-monotonic inserts and index
locality without exposing a count), **distributed-safe** (app-generated, so a
module can later be extracted without re-keying), **opaque** (no business-volume
leakage, unlike sequential integers), and **URL-friendly** (Crockford base32, 26
chars, safe in routes, filenames, logs).

---

## 5. Foreign Keys

Relationships are explicit, typed, indexed, and enforced. A relationship in the
Domain Model **MUST** exist as a real `FOREIGN KEY` (`DATABASE_GUIDE.md` §4);
"logical-only" in-database references are not acceptable.

| Rule | Requirement |
|---|---|
| Naming | `<entity>_id`, singular target: `workspace_id`, `job_id`, `application_id`, `user_id`. |
| Type | **MUST** match the target `id` exactly: `CHAR(26)`. |
| Constraint | A real `FOREIGN KEY` is declared wherever the relationship exists *within a module's own tables*. |
| Index | Every FK column **MUST** be indexed (InnoDB requires it; we rely on it — §6). |
| Nullability | `NOT NULL` when mandatory (`workspace_id` — §8); nullable only when genuinely optional (`interview_sessions.ai_session_id`, `pipelines.job_id` for templates, `error_events.workspace_id`). |
| Referential actions | `ON DELETE` and `ON UPDATE` **MUST** be stated explicitly; relying on the silent default is a defect. |

**Referential-action policy by relationship type:**

| Action | Use when | Examples |
|---|---|---|
| `ON DELETE RESTRICT` | **Default.** Deleting a still-referenced parent is a programming error and is blocked. | `applications.job_id`, `subscriptions.plan_id`, `ai_models.provider_id`, `memberships.user_id`. |
| `ON DELETE CASCADE` | Only true **owned children** in one aggregate, meaningless without the parent. Sparingly, documented. | `job_questions → jobs`, `pipeline_stages → pipelines`, `ai_messages → ai_sessions`, `role_permissions → roles`, application timeline/`application_documents`. |
| `ON DELETE SET NULL` | A genuinely **optional** link where orphaning is meaningful; column must be nullable. | `files.folder_id` when a folder is removed. |
| `ON UPDATE` | Always **`RESTRICT`/`NO ACTION`** — `id` is an immutable ULID, so cascades are unnecessary. | All relationships. |

> **Module boundary.** A FK **MUST NOT** cross into another module's privately-
> owned tables; cross-module links are plain `CHAR(26)` identifiers resolved via
> contracts/events (`DATABASE_GUIDE.md` §11). Soft-deleted parents (§9) are
> excluded by the read path, not by `ON DELETE`.

---

## 6. Indexes (strategy)

Per-table plans belong to `INDEXING_GUIDE.md`; this fixes the strategy every table
follows (`DATABASE_GUIDE.md` §13).

- **Every foreign key is indexed** (§5).
- **Tenant-leading composites.** On workspace-scoped tables, composite indexes
  **MUST** lead with `workspace_id` (every tenant query filters by it first):
  `(workspace_id, created_at)`, `(workspace_id, status_code)`, `(workspace_id, <fk>)`.
- **Unique constraints encode invariants:**

  | Table | Unique key | Enforces |
  |---|---|---|
  | `memberships` | `(workspace_id, user_id)` | one membership per user per workspace. |
  | `applications` | `(workspace_id, job_id, user_id)` | one application per user per job per workspace (`DOMAIN_MODEL.md` Invariant 4); re-application is history, not a duplicate. |
  | `candidate_profiles` | `(workspace_id, user_id)` | one profile view per user per workspace (Invariant 3). |
  | `candidate_profile_tags` | `(candidate_profile_id, tag_id)` | a tag applied at most once. |
  | `role_permissions` / `membership_roles` | `(role_id, permission_id)` / `(membership_id, role_id)` | no duplicate grant/assignment. |
  | `permissions` / `plans` | `(key)` | catalog/plan key globally unique. |

  Tenant-scoped uniqueness (e.g. per-workspace slug, `tags.name`) **MUST** include
  `workspace_id`; a unique key never replaces `id` (§4).
- **Composite indexes for hot filters** — columns in `WHERE`/`JOIN`/`ORDER BY`/
  uniqueness checks; needless write-slowing indexes are avoided and each is
  justified in `INDEXING_GUIDE.md`.
- **FULLTEXT for search projections.** Unified search is served by
  `search_documents` (workspace-scoped, `entity_type` + `entity_id`), which carries
  the FULLTEXT index — search never scans business tables directly.
- **Keyset pagination** over the time-sortable `id` (or `(workspace_id, id)`);
  unbounded `SELECT *` over a growing table is forbidden.

---

## 7. Constraints

The model expresses as much truth as possible in the schema and enforces the rest
in the domain layer (`DATABASE_GUIDE.md` §6, §7).

- **NOT NULL `workspace_id` on every workspace-scoped table** — the backbone of
  tenancy (§8). `workspaces` is the one exception: tenant root, keyed by `id`,
  carrying `owner_user_id` (`ENTITY_CATALOG.md` §2).
- **Unique keys** encode the invariants in §6; every tenant-scoped unique key
  includes `workspace_id`.
- **No MySQL `ENUM`/`SET`** (`DATABASE_GUIDE.md` §7). Instead:
  - A set the **code branches on** (`JobStatus`, `ApplicationStatus`, `OfferStatus`,
    `MembershipStatus`, per `STATE_DIAGRAMS.md` §2–§10) is a **PHP enum** persisted
    as `status_code VARCHAR`, validated in the app.
  - A set needing **labels, ordering, or per-workspace extension** is a **lookup
    table** with its own ULID `id` and a FK from the owner — `pipeline_stages` is
    workspace data, not enum values (`DOMAIN_MODEL.md` §4.2).
- **Check-like rules live in the domain.** MySQL `CHECK` is not the enforcement
  surface; value ranges and state-transition legality are enforced by the owning
  module's Domain layer (the `STATE_DIAGRAMS.md` machines). The schema enforces
  structure: types, nullability, uniqueness, foreign keys.
- **JSON is constrained by policy, not type** — permitted only for schemaless,
  app-owned blobs (settings, captured payloads, documented read snapshots),
  forbidden for anything filtered, joined, sorted, related, or enumerated; those
  MUST be real columns (`DATABASE_GUIDE.md` §8).

---

## 8. Tenancy

Workspace isolation is the single most important security invariant
(`WORKSPACE_MODEL.md` §3; Constitution §3.5, §10). Schema and data-access enforce it
together.

**The `workspace_id` rule.** Every workspace-scoped table **MUST** have
`workspace_id CHAR(26) NOT NULL`, a FK to `workspaces.id`, and an index; tenant-
scoped uniqueness includes `workspace_id` (§6, §7). A scoped row is **invisible** to
every other workspace.

**The repository tenant guard (mandatory)** — `DATABASE_GUIDE.md` §6.2:

- Enforced at the **Infrastructure / repository layer**, never left to callers.
- Applied **centrally** (base repository / query builder) so **SELECT, UPDATE, and
  DELETE** are scoped by the active `workspace_id` **by default** — a developer
  cannot forget the filter.
- The active workspace comes from the authenticated **Workspace Context**
  (`WORKSPACE_MODEL.md` §7); a client-supplied `workspace_id` is **never** trusted.
- Any tenant-scope bypass (System Owner / Platform Context) is an **explicit,
  audited, permission-gated** `system.*` exception, never the default path.
- Prepared statements only; `workspace_id` is always bound (Constitution §6).

**Global vs Workspace split (`ENTITY_CATALOG.md` §2).** Exactly two scoping classes.
The **global** tables (no `workspace_id`) are precisely: `users`, `user_sessions`,
`password_resets`, `remember_tokens`, `permissions`, `plans`, `coupons`,
`feature_flags`, `ai_providers`, `ai_models`, `prompt_templates` (global library),
`system_settings`, `system_audit_logs`, `error_events` (system), `metrics`,
`health_checks`, `backups`, `background_jobs`, and `workspaces`. **Everything else
is workspace-scoped; when in doubt, it is workspace-scoped.** A few records exist in
*both* contexts (settings and audit at global and workspace levels; `error_events`
and `alerts` with nullable `workspace_id`), modeled per the catalog with the
workspace-level rows carrying `workspace_id`. `access_tokens` may be user-,
workspace-, or system-scoped via nullable `user_id`/`workspace_id` and a `type`
discriminator (§8). `ENTITY_CATALOG.md` is the single authoritative source for
scope.

---

## 9. Soft-Delete Policy (summary)

Business data is, by default, **never physically destroyed**
(`WORKSPACE_MODEL.md` §6). Three distinct operations; authoritative per-entity rules
and retention windows live in `ARCHIVING_POLICY.md`. Modeling summary
(`DATABASE_GUIDE.md` §9):

| Operation | Meaning | Mechanism | Default visibility |
|---|---|---|---|
| **Soft delete** | Removed from use, retained and recoverable. | `deleted_at DATETIME NULL` (NULL = live); repositories exclude `deleted_at IS NOT NULL` by default. | Hidden |
| **Archive** | Intentionally set aside but still a valid, queryable record (a closed `jobs` row, an archived workspace). | A **state column** (`status_code`) or distinct `archived_at` — **not** `deleted_at`. | Per business rules |
| **Hard delete** | Physical row removal. | Actual delete. | n/a |

- Tables marked **(soft)** in `ENTITY_CATALOG.md` (e.g. `users`, `workspaces`,
  `memberships`, `roles`, `files`, `jobs`, `applications`, `candidate_notes`,
  `offers`, `employees`) carry `deleted_at` and **MUST NOT** be hard-deleted in
  normal flows.
- The default read path **and** the tenant guard (§8) exclude soft-deleted rows
  unless a query explicitly opts in.
- Soft delete, archive, and hard delete are **different operations** — archiving
  **MUST NOT** set `deleted_at`.
- Immutable tables (§11) are never soft-deleted. **Hard delete** is reserved for
  legally mandated erasure, transient rows (`password_resets`, `remember_tokens`,
  caches), and true junk, per `ARCHIVING_POLICY.md`.

---

## 10. Audit Policy (summary)

Significant state is observable and reconstructable. Full policy: `AUDIT_POLICY.md`;
the audited-event catalog is the Phase 4 `AUDIT_EVENTS.md`. Modeling summary:

- **Two immutable trails** (`ENTITY_CATALOG.md` §4): `audit_logs` — **workspace-
  scoped** (`workspace_id`, `actor_user_id`, `action`, `entity_type`/`entity_id`,
  `ip`, `changes` JSON), recording who-did-what inside a tenant; and
  `system_audit_logs` — **global**, for platform actions by System Owners
  (`system.*`), including every tenant-scope-bypass exception (§8).
- Audit rows are **append-only**: no `updated_at`, no `deleted_at`; never edited or
  soft-deleted (§11).
- The Audit module owns these tables; other modules emit events and **never write
  another module's audit rows directly** (`MODULES.md` §4).
- `changes` JSON holds before/after deltas as an opaque, app-validated blob; fields
  used to filter (actor, action, entity, time) are real, indexed columns, not JSON
  lookups (§7).

---

## 11. History / Versioning Policy (summary)

Some entities keep history; some tables are append-only by nature. Full policy:
`VERSIONING_POLICY.md`. Modeling summary:

- **Versioned entities** keep prior states via the `*_versions` snapshot pattern:
  `jobs` (root) is versioned by `job_versions` (immutable), each snapshot
  referencing `job_id` (`ENTITY_CATALOG.md` §5). Tables flagged **versioned** in the
  catalog — `jobs`, `templates`, `prompt_templates`, `workspace_prompts`,
  `workflows` — keep history per `VERSIONING_POLICY.md`.
- **Immutable / append-only** tables carry **neither `updated_at` nor `deleted_at`**
  and are never mutated after insert. Per the catalog these are: `job_versions`,
  `application_stage_history`, `audit_logs`, `system_audit_logs`, `ai_usage`,
  `ai_fallback_history`, `workflow_execution_steps`, `webhook_deliveries`,
  `payments`, `metrics`, `health_checks`.
- **History is recorded, not mirrored.** Movement history
  (`application_stage_history`: from/to stage, `moved_by`) and point-in-time
  snapshots (the salary on an `offers` row when extended) are legitimate domain
  facts recording history — **not** a live copy of a current value
  (`DATABASE_GUIDE.md` §10).
- **"Current" is derived.** An application's current stage is reached via
  `applications.current_stage_id` (a reference) while the journey lives in
  `application_stage_history`; the history table is never where live state is read.

---

## 12. Migrations

Schema is delivered through a **bespoke, forward-only migration engine** — part of
the **Database** module (Phase 8), not a framework tool (`DATABASE_GUIDE.md` §12).
The concrete engine, ledger, and CLI are Phase 8 implementation; this fixes what the
*model* requires.

| Rule | Requirement |
|---|---|
| **No framework** | Purpose-built native PHP; no Laravel/Doctrine/Phinx (Constitution §5). |
| **Forward-only** | No down/rollback in production — a mistake is fixed by a new forward migration (Constitution §14). |
| **Versioned & ordered** | Uniquely identified, applied in deterministic order; tracked in a **migrations ledger** table. |
| **Runs cleanly from zero** | The full set **MUST** build a correct schema from an **empty database** — the schema's source of truth, exercised by the Installer and CI. |
| **Idempotent** | Re-running applies only what is pending. |
| **Module-owned** | Each module ships migrations under its `Database/` folder; a global runner orchestrates them in dependency order (`MODULES.md` §5). |
| **Tenant-safe** | Preserve the §8 invariants — never drop `workspace_id`, never silently widen scope. |
| **Reviewed & gated** | Reviewed and CI-gated like code; destructive changes get extra scrutiny and an ADR where warranted. |

**Seed of core data** — minimal, idempotent, under the ULID/no-`AUTO_INCREMENT`
rules: the **permissions catalog** (`permissions`; `PERMISSION_MODEL.md`) so
authorization works on first boot; baseline global catalogs/settings as required
(`system_settings`, initial `ai_providers`/`ai_models`; `plans` are configured, never
hard-coded — `ENTITY_CATALOG.md` §6, §9); and the **first System Owner**, created
interactively by the **Installer** (zero-touch browser install, Phase 8) as a `User`
granted `system.*`. There is **no reserved role and no seeded workspace role** — a
workspace owner defines roles themselves (`WORKSPACE_MODEL.md` §6). Install on an
empty database yields a working platform with exactly one System Owner and a
complete permission catalog.

---

## 13. Self-Review Checklist (Phase 3 gate)

The model is conformant only if **all** hold (consolidating `DATABASE_GUIDE.md` §14
and `ENTITY_CATALOG.md` §11):

- [ ] **No derived/duplicated authoritative data** — counts, totals, "current
      status", and "latest stage" are computed; any cache is named as such with a
      documented invalidation strategy (§7, §11).
- [ ] **No circular references** — the ownership graph has no cycle; reactions that
      would create one use events, not FKs (`MODULES.md` §5).
- [ ] **Every relationship is logical and real** — each Domain-Model relationship is
      a real, indexed FK within its module, with explicit `ON DELETE`/`ON UPDATE`;
      cross-module links are `CHAR(26)` references via contracts (§5).
- [ ] **A module can be added without redesign** — new entities slot in by *adding*
      tables/columns under the same conventions; no existing table is reshaped (§1).
- [ ] Every workspace-scoped table carries `workspace_id CHAR(26) NOT NULL`, FK +
      index; tenant-scoped uniqueness includes `workspace_id` (§6, §8).
- [ ] Every table has an `id CHAR(26)` ULID PK, app-generated, no `AUTO_INCREMENT`;
      one identifier scheme platform-wide (§4).
- [ ] Scope (global vs workspace) for every table matches `ENTITY_CATALOG.md` §2 (§8).
- [ ] `candidate_profiles` is per-(User,Workspace); there is **no** global candidate
      table (Invariant 3).
- [ ] No table depends on a fixed role name (`DOMAIN_MODEL.md` Invariant 5).
- [ ] No MySQL `ENUM`/`SET`; fixed sets are PHP enums (`status_code`) or lookup
      tables (§7).
- [ ] Immutable/append-only tables identified and carry neither `updated_at` nor
      `deleted_at`; soft-delete vs archive vs hard-delete classified per
      `ARCHIVING_POLICY.md` (§9, §11).
- [ ] The full migration set builds from zero and seeds the permission catalog plus
      the first System Owner (§12).

---

### Related Documents

`ENTITY_CATALOG.md` · `DATABASE_GUIDE.md` · `DOMAIN_MODEL.md` ·
`WORKSPACE_MODEL.md` · `STATE_DIAGRAMS.md` · `MODULES.md` · `ER_DIAGRAM.md` ·
`RELATIONSHIP_MATRIX.md` · `INDEXING_GUIDE.md` · `AUDIT_POLICY.md` ·
`ARCHIVING_POLICY.md` · `VERSIONING_POLICY.md` · `AUDIT_EVENTS.md` (Phase 4) ·
`PROJECT_CONSTITUTION.md` · `SECURITY_GUIDE.md`
