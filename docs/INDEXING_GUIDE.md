# INDEXING GUIDE — HaHireAI

> **Status:** Adopted (Phase 3) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `ENTITY_CATALOG.md`, `DATABASE_GUIDE.md`.

---

## 0. Purpose & Scope

This is the **table-by-table index plan** for HaHireAI — the Phase 3 realization
of the indexing rules in `DATABASE_GUIDE.md` §13. On a *rule*, `DATABASE_GUIDE.md`
wins; on a *table name or scope*, `ENTITY_CATALOG.md` wins. This guide never
redefines either — it applies them. All table names below are the **exact** names
from `ENTITY_CATALOG.md`; all index DDL is **illustrative only** (the authoritative
schema is delivered by forward-only migrations, `DATABASE_GUIDE.md` §12).

---

## 1. Index Philosophy

Indexes are a design property, not an afterthought. Every index in HaHireAI exists
to serve a **real, named query pattern** drawn from `APPLICATION_FLOW.md` and the
state machines in `STATE_DIAGRAMS.md`. The governing principles:

| # | Principle | What it means in practice |
|---|---|---|
| 1 | **Every index is justified by a real query** | No index is added "just in case." Each entry in §3 names the query (filter / join / sort / uniqueness check) it serves. An index with no query is removed (`DATABASE_GUIDE.md` §13). |
| 2 | **Pagination is mandatory** | Every list/collection query MUST be bounded. Unbounded `SELECT *` over a growing table is forbidden (`DATABASE_GUIDE.md` §13). Keyset/seek pagination over the ULID `id` (or `(workspace_id, id)`) is preferred for large sets. |
| 3 | **No N+1** | Related rows are fetched in batches or via deliberate joins, never per-row loops. This is what makes the FK indexes in §3 load-bearing: batch reads (`... WHERE application_id IN (...)`) rely on the FK index. |
| 4 | **The ULID PK is the clustered key** | InnoDB clusters every table on its primary key. Since `id` is the ULID `CHAR(26)` PK (`DATABASE_GUIDE.md` §3), the table is physically ordered by `id`. |
| 5 | **ULID order ≈ insert order** | A ULID's leading 48 bits are a millisecond timestamp, so `id` is time-sortable: PK order ≈ insert order. New rows append near the "end" of the clustered index (good locality, minimal page splits), and `ORDER BY id` is effectively `ORDER BY created_at` without a second index or an exposed counter. |
| 6 | **Workspace-first** | On workspace-scoped tables, `workspace_id` leads composite indexes because the tenant guard filters by it on **every** query (§5; `DATABASE_GUIDE.md` §6). |
| 7 | **Writes cost too** | Every index slows `INSERT`/`UPDATE`/`DELETE` and consumes space. Indexes are added deliberately and removed when redundant (§6). The narrowest set that covers the real queries wins. |

**Consequence of principle 5:** because `id` already encodes creation time, a
plain `(workspace_id, id)` index doubles as a tenant-scoped "newest first" keyset
pagination index — prefer it. Add a separate `(workspace_id, created_at)` index
**only** where a query sorts/filters by `created_at` as a *distinct* column (e.g.
append-only log tables paged by time window) — see §6 on not duplicating the PK.

---

## 2. Index Types — and When to Use Each

Every table draws from the same small, uniform toolbox. One way to do each thing.

| Type | Definition | WHEN to use | Notes |
|---|---|---|---|
| **Primary (`id`)** | `PRIMARY KEY (id)` on the ULID `CHAR(26)`. | **Always** — exactly one per table (`DATABASE_GUIDE.md` §3). | This is the InnoDB clustered key (§1). Time-sortable, so it also serves `ORDER BY id` pagination. |
| **Unique** | `UNIQUE (...)` enforcing a business invariant in the database. | When a real-world fact must not duplicate: a global token (`public_token`), or a tenant-scoped pair (one application per `job_id,user_id`). | On tenant tables, uniqueness MUST include `workspace_id` *unless* the column is already globally unique by construction (e.g. a random `public_token`). A unique index also serves equality lookups, so it doubles as a read index. |
| **Composite** | A multi-column `INDEX (a, b, ...)` ordered most-selective-for-the-access-pattern first. | When queries filter/sort on several columns together — the dominant case on tenant tables: `(workspace_id, status_code)`, `(workspace_id, scheduled_at)`. | Honors the **leftmost-prefix** rule: `(workspace_id, status_code)` also serves a `workspace_id`-only filter, so a separate `(workspace_id)` index is redundant (§6). |
| **Foreign (FK)** | A single-column index on every `<entity>_id`. | **Always, on every foreign key** — InnoDB requires it and we rely on it for joins and batch reads (`DATABASE_GUIDE.md` §4, §13). | If a composite index already *leads* with that FK column, the standalone FK index is redundant — drop it (§6). The FK constraint can use the composite's leftmost prefix. |
| **Search (FULLTEXT)** | `FULLTEXT (...)` on natural-language text. | Only for free-text relevance search (the unified search projection). Never use `LIKE '%term%'` for this — it cannot use a B-tree index and scans. | InnoDB FULLTEXT, `utf8mb4`. Tenant scoping is applied as a `WHERE workspace_id = ?` predicate combined with `MATCH ... AGAINST` (§3, `search_documents`). |

**Decision order for a new column/query:** (1) PK? use `id`. (2) Must it be unique?
add a `UNIQUE` (with `workspace_id` if tenant-scoped). (3) FK? it gets an index,
unless already the leftmost column of a composite. (4) Hot filter/sort alongside
`workspace_id`? add a composite led by `workspace_id`. (5) Natural-language search?
`FULLTEXT`. (6) Otherwise — no index.

---

## 3. Per-Table Index Plan (high-traffic tables)

Conventions for this section:

- **Type** uses the §2 vocabulary: `PK`, `Unique`, `Composite`, `FK`, `FULLTEXT`.
- Status/state values are stored as a `*_code VARCHAR` column (`DATABASE_GUIDE.md`
  §7 — no MySQL `ENUM`); the index is on that column. States come from
  `STATE_DIAGRAMS.md`.
- `PK (id)` is present on **every** table and is listed once per table without
  restating the §1 rationale.
- "redundant via prefix" in a Justification means the leftmost-prefix of a listed
  composite already covers that lookup, so no separate index is defined.

### 3.1 Identity & Access

#### `workspaces` (Global — tenant root, keyed by `id`)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| workspaces | PRIMARY | `(id)` | PK | Row identity; clustered key. |
| workspaces | `uq_workspaces_slug` | `(slug)` | Unique | Resolve a workspace by its public slug (routing); slug is globally unique (this is the tenant root, not a tenant-scoped table). |
| workspaces | `idx_workspaces_owner` | `(owner_user_id)` | FK | "Workspaces owned by this user" on the user's account/home; FK to `users`. |
| workspaces | `idx_workspaces_deleted_at` | `(deleted_at)` | Composite | Default read path excludes soft-deleted rows (`DATABASE_GUIDE.md` §9). |

#### `memberships` (Workspace, soft)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| memberships | PRIMARY | `(id)` | PK | Row identity. |
| memberships | `uq_memberships_ws_user` | `(workspace_id, user_id)` | Unique | Invariant: one membership per (workspace, user) (`ENTITY_CATALOG.md` §3). Also serves "is this user a member of this workspace?" and the leftmost-prefix serves all `workspace_id` member listings. |
| memberships | `idx_memberships_user` | `(user_id)` | FK | Reverse lookup: "all workspaces this user belongs to" (workspace switcher). The unique index above leads with `workspace_id`, so `user_id` needs its own index. |
| memberships | `idx_memberships_ws_status` | `(workspace_id, status_code)` | Composite | Member roster filtered by status (Active / Invited / Suspended; `STATE_DIAGRAMS.md` §7). |

#### `roles` (Workspace, soft)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| roles | PRIMARY | `(id)` | PK | Row identity. |
| roles | `uq_roles_ws_name` | `(workspace_id, name)` | Unique | Role names are unique per workspace (no reserved roles; `ENTITY_CATALOG.md` §3). Leftmost-prefix serves "list roles in workspace." |

#### `role_permissions` (Pivot — Workspace)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| role_permissions | PRIMARY | `(id)` | PK | Row identity (ULID PK on pivots too; `DATABASE_GUIDE.md` §3). |
| role_permissions | `uq_roleperm` | `(role_id, permission_id)` | Unique | A permission is attached to a role at most once; also the join index from `roles` and the leftmost-prefix serves "permissions of this role." |
| role_permissions | `idx_roleperm_permission` | `(permission_id)` | FK | Reverse join: "which roles grant this permission" (permission impact analysis). |

#### `jobs` (Workspace, soft, versioned)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| jobs | PRIMARY | `(id)` | PK | Row identity; `ORDER BY id` = newest-first listing. |
| jobs | `uq_jobs_public_token` | `(public_token)` | Unique | Public job page served **without login** by token (`APPLICATION_FLOW.md` §3); globally unique random token, so not workspace-scoped. |
| jobs | `idx_jobs_ws_status` | `(workspace_id, status_code)` | Composite | Primary jobs board query: jobs in a workspace filtered by state (Draft/Published/Paused/Closed/Archived; `STATE_DIAGRAMS.md` §2). Leftmost-prefix serves all `workspace_id` listings. |
| jobs | `idx_jobs_created_by` | `(created_by)` | FK | "Jobs I created"; FK to `users`. No standalone `(workspace_id)` index — `idx_jobs_ws_status` covers it via leftmost-prefix (§6). |

#### `applications` (Workspace, soft) — the spine

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| applications | PRIMARY | `(id)` | PK | Row identity; newest-first via `id`. |
| applications | `uq_applications_job_user` | `(job_id, user_id)` | Unique | Invariant: one application per (Job, User) — re-application is history, not a duplicate (`APPLICATION_FLOW.md` §10.1). Both belong to one workspace, so the pair is tenant-safe; also the join index from `jobs`. |
| applications | `idx_applications_ws_status` | `(workspace_id, status_code)` | Composite | Application lists filtered by lifecycle status (Applied … Hired/Rejected/Withdrawn; `STATE_DIAGRAMS.md` §3). Leftmost-prefix serves all `workspace_id` lists. |
| applications | `idx_applications_ws_stage` | `(workspace_id, current_stage_id)` | Composite | Pipeline/Kanban board: applications grouped by current pipeline stage within a workspace (`APPLICATION_FLOW.md` §5). |
| applications | `idx_applications_user` | `(user_id)` | FK | "All of this user's candidacies" feeding the per-(User,Workspace) Candidate Profile view (`APPLICATION_FLOW.md` §6); FK to `users`. |

> `current_stage_id` is also a FK; its query always carries `workspace_id`, so
> `idx_applications_ws_stage` serves it and a standalone `(current_stage_id)` index
> is redundant (§5, §6).

#### `application_stage_history` (Workspace, immutable)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| application_stage_history | PRIMARY | `(id)` | PK | Row identity; append-only, so `id` order = chronological order. |
| application_stage_history | `idx_ash_application` | `(application_id)` | FK | The Activity Timeline: every stage move for one application, read in `id` (time) order (`APPLICATION_FLOW.md` §5). FK to `applications`. |

#### `candidate_profiles` (Workspace)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| candidate_profiles | PRIMARY | `(id)` | PK | Row identity. |
| candidate_profiles | `uq_candprofile_ws_user` | `(workspace_id, user_id)` | Unique | The profile is a per-(User, Workspace) **view** — exactly one per pair (`ENTITY_CATALOG.md` §5; `APPLICATION_FLOW.md` §6). Resolves "this user's profile in this workspace" and leftmost-prefix lists a workspace's candidates. |

#### `candidate_notes` (Workspace, soft)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| candidate_notes | PRIMARY | `(id)` | PK | Row identity; `id` order = chronological. |
| candidate_notes | `idx_candnotes_profile` | `(candidate_profile_id)` | FK | All notes on a candidate profile (timeline). FK to `candidate_profiles`. |
| candidate_notes | `idx_candnotes_author` | `(author_user_id)` | FK | "Notes I wrote" / author attribution; FK to `users`. |

#### `tags` (Workspace)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| tags | PRIMARY | `(id)` | PK | Row identity. |
| tags | `uq_tags_ws_name` | `(workspace_id, name)` | Unique | Tag names are unique per workspace; leftmost-prefix serves the workspace's tag picker list. (Pivot `candidate_profile_tags` → §4.) |

#### `interviews` (Workspace)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| interviews | PRIMARY | `(id)` | PK | Row identity. |
| interviews | `idx_interviews_application` | `(application_id)` | FK | All interviews attached to one application (`APPLICATION_FLOW.md` §7). FK to `applications`. |
| interviews | `idx_interviews_ws_scheduled` | `(workspace_id, scheduled_at)` | Composite | Calendar/agenda: a workspace's interviews ordered by scheduled time, paged by date window. Leftmost-prefix serves workspace-wide interview lists. |

#### `offers` (Workspace, soft)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| offers | PRIMARY | `(id)` | PK | Row identity. |
| offers | `idx_offers_application` | `(application_id)` | FK | The offer(s) for an application — at most one active (`APPLICATION_FLOW.md` §9). FK to `applications`. |
| offers | `idx_offers_ws_status` | `(workspace_id, status_code)` | Composite | Offer pipeline filtered by status (Draft/Approved/Sent/Accepted…; `STATE_DIAGRAMS.md` §4). |

### 3.2 Intelligence (AI)

#### `ai_usage` (Workspace, immutable)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| ai_usage | PRIMARY | `(id)` | PK | Row identity; append-only. |
| ai_usage | `idx_aiusage_ws_created` | `(workspace_id, created_at)` | Composite | Usage/cost reporting over a time window per workspace (billing, dashboards). Here `created_at` is a **distinct sort/filter column** (date-range aggregation), so it earns its own composite beyond the PK (§1). Leftmost-prefix serves total-usage-per-workspace. |
| ai_usage | `idx_aiusage_session` | `(ai_session_id)` | FK | Roll usage up to a single AI session; FK to `ai_sessions`. |

#### `ai_sessions` (Workspace)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| ai_sessions | PRIMARY | `(id)` | PK | Row identity. |
| ai_sessions | `idx_aisessions_ws_status` | `(workspace_id, status_code)` | Composite | A workspace's AI sessions filtered by status (active/completed/failed). Leftmost-prefix serves workspace session lists. |
| ai_sessions | `idx_aisessions_provider` | `(provider_id)` | FK | Sessions by provider (fallback/health analysis); FK to `ai_providers`. (Child `ai_messages` → §4.) |

### 3.3 Platform Services

#### `audit_logs` (Workspace, immutable)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| audit_logs | PRIMARY | `(id)` | PK | Row identity; append-only = chronological by `id`. |
| audit_logs | `idx_audit_ws_created` | `(workspace_id, created_at)` | Composite | The audit feed: a workspace's events over a time window (`DATABASE_GUIDE.md` §9, Audit). `created_at` is a real range-filter column here. Leftmost-prefix serves the full workspace feed. |
| audit_logs | `idx_audit_entity` | `(entity_type, entity_id)` | Composite | "History of *this* record" — all audit entries for one entity (e.g. one application). Queried together, so a two-column composite, not two single indexes. |
| audit_logs | `idx_audit_actor` | `(actor_user_id)` | FK | "What did this user do"; FK to `users`. |

#### `notifications` (Workspace)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| notifications | PRIMARY | `(id)` | PK | Row identity; newest-first via `id`. |
| notifications | `idx_notif_ws_user_read` | `(workspace_id, user_id, read_at)` | Composite | The notification bell: a user's notifications in a workspace, unread first (`read_at IS NULL`). Leftmost-prefixes serve `(workspace_id)` and `(workspace_id, user_id)` lookups too. |

#### `files` (Workspace, soft)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| files | PRIMARY | `(id)` | PK | Row identity. |
| files | `idx_files_ws_owner` | `(workspace_id, owner_user_id)` | Composite | "Files owned by this user in this workspace" (the CV/resume referenced by applications; `APPLICATION_FLOW.md` §4). Leftmost-prefix serves a workspace's file listing. |
| files | `idx_files_folder` | `(folder_id)` | FK | Files within a folder (file browser); FK to `folders`, nullable. |

#### `search_documents` (Workspace)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| search_documents | PRIMARY | `(id)` | PK | Row identity. |
| search_documents | `idx_searchdocs_ws_entity` | `(workspace_id, entity_type, entity_id)` | Composite | Upsert/locate the projection row for a given source entity; also tenant-scopes the search. |
| search_documents | `ft_searchdocs_content` | `(content)` | FULLTEXT | Unified relevance search: `MATCH(content) AGAINST(? IN BOOLEAN MODE)` combined with a `WHERE workspace_id = ?` predicate for tenant isolation. Never `LIKE '%…%'` (§2). |

> FULLTEXT cannot lead a composite with `workspace_id`; tenant isolation combines
> the `MATCH` with an indexed `workspace_id` equality predicate (via
> `idx_searchdocs_ws_entity`'s leftmost prefix). The repository tenant guard
> (`DATABASE_GUIDE.md` §6.2) MUST still apply.

### 3.4 Integration

#### `webhooks` (Workspace) & `webhook_deliveries` (Workspace, immutable)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| webhooks | PRIMARY | `(id)` | PK | Row identity. |
| webhooks | `idx_webhooks_ws_status` | `(workspace_id, status_code)` | Composite | Active webhook endpoints for a workspace (dispatch + management). Leftmost-prefix serves the workspace's endpoint list. |
| webhook_deliveries | PRIMARY | `(id)` | PK | Row identity; append-only. |
| webhook_deliveries | `idx_whdel_webhook` | `(webhook_id)` | FK | Delivery history/retries for one endpoint, in `id` (time) order; FK to `webhooks`. |
| webhook_deliveries | `idx_whdel_status` | `(status_code)` | Composite | Retry sweep: find pending/failed deliveries to re-dispatch (a worker query across endpoints). |

### 3.5 Commerce

#### `subscriptions` (Workspace)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| subscriptions | PRIMARY | `(id)` | PK | Row identity. |
| subscriptions | `idx_subs_ws_status` | `(workspace_id, status_code)` | Composite | Resolve a workspace's current subscription state (Trialing/Active/PastDue/Suspended…; `STATE_DIAGRAMS.md` §9); gate access. One active per workspace. Leftmost-prefix serves "subscription(s) for workspace." |
| subscriptions | `idx_subs_plan` | `(plan_id)` | FK | "Workspaces on this plan" (plan analytics/migration); FK to `plans`. |

#### `invoices` (Workspace)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| invoices | PRIMARY | `(id)` | PK | Row identity; newest-first via `id`. |
| invoices | `uq_invoices_number` | `(number)` | Unique | Invoice numbers are unique (legal/accounting requirement); direct lookup by number. |
| invoices | `idx_invoices_ws_status` | `(workspace_id, status_code)` | Composite | Billing history filtered by status (paid/open/void) per workspace. Leftmost-prefix serves the workspace's invoice list. |

#### `usage_counters` (Workspace)

| Table | Index | Columns | Type | Justification (query it serves) |
|---|---|---|---|---|
| usage_counters | PRIMARY | `(id)` | PK | Row identity. |
| usage_counters | `uq_usagectr_ws_metric_period` | `(workspace_id, metric, period)` | Unique | One counter row per (workspace, metric, period); upsert-and-increment for quota enforcement. Also the read path for "current usage of metric X this period." |

---

## 4. Remaining Tables — Category-Level Coverage

Tables not enumerated in §3 follow these **uniform category patterns** — the same
rules applied, not exceptions. (Scopes per `ENTITY_CATALOG.md`.) Every table also
has `PRIMARY KEY (id)` (§1); soft-delete tables add a `deleted_at` index only if
deleted-row volume materially hurts the default-exclusion read path.

- **Pivot / join tables** (`membership_roles`, `membership_permissions`,
  `candidate_profile_tags`): `UNIQUE` on the ordered FK pair (forward join +
  invariant) + a single-column index on the **second** FK (reverse join). The
  first FK needs none — the unique covers it via leftmost-prefix (§6).
- **Owned child / detail tables** (`job_versions`, `job_questions`,
  `pipeline_stages`, `application_documents`, `interview_sessions`, `scorecards`,
  `ai_messages`, `ai_fallback_history`, `workflow_execution_steps`,
  `workflow_approvals`, `payments`): FK index on the parent (`<parent>_id`), read
  in `id` (time) order. Add `(<parent>_id, position)` only where rows are ordered
  (e.g. `pipeline_stages.position`).
- **Workspace-scoped status/list tables** (`employees`, `talent_pool_entries`,
  `templates`, `workflows`, `workflow_executions`, `scheduled_tasks`,
  `integrations`, `oauth_connections`, `api_keys`, `payment_methods`,
  `reports`/`saved_views`, `workspace_prompts`, `pipelines`): composite led by
  `workspace_id` — `(workspace_id, status_code)` or `(workspace_id, <hot_filter>)`
  — plus FK indexes for any `<entity>_id` not covered by a composite prefix.
  E.g. `scheduled_tasks (workspace_id, next_run_at)` (due-task sweep);
  `workflow_executions (workspace_id, status_code)` (run monitor).
- **Workspace singleton/config tables** (`workspace_settings`,
  `workspace_branding`, `workspace_ai_settings`, `workspace_ai_keys`,
  `workspace_feature_flags`): `UNIQUE (workspace_id)` for one row per workspace, or
  `UNIQUE (workspace_id, key)` for keyed settings; that unique is the read path.
- **Global auth/token tables** (`users`, `user_sessions`, `password_resets`,
  `remember_tokens`, `access_tokens`): `UNIQUE` on the lookup key (`users.email`;
  the `hashed_token`/`hashed_key` columns — hot, hit on every authenticated
  request) + FK `(user_id)` for the owner's session/token list.
- **Global catalog/lookup tables** (`permissions`, `plans`, `coupons`,
  `feature_flags`, `ai_providers`, `ai_models`, `prompt_templates`,
  `system_settings`): `UNIQUE` on the business key (`permissions.key`, `plans.key`,
  `coupons.code`, `feature_flags.key`). Small, mostly-read — resist more than the
  key index. `ai_models` adds FK `(provider_id)`.
- **Global/append-only operations tables** (`system_audit_logs`, `metrics`,
  `health_checks`, `backups`, `background_jobs`, `error_events`, `alerts`): index
  by the time/selection column actually queried — `background_jobs
  (status_code, available_at)` (queue claim); `error_events (workspace_id,
  created_at)` + `(severity_code, status_code)` (triage); `metrics (name,
  recorded_at)`; `health_checks (component, checked_at)`.

If a table here grows a query not covered by its pattern, add the specific index
and **promote it into §3** with its justification.

---

## 5. Tenancy & Indexes (binding)

This restates, for indexing, the most important rule of `DATABASE_GUIDE.md` §6.

- On **every workspace-scoped table**, `workspace_id` **MUST** be the **leading
  column** of every composite index. The repository tenant guard
  (`DATABASE_GUIDE.md` §6.2) adds `WHERE workspace_id = ?` to **every** SELECT,
  UPDATE, and DELETE — an index not led by `workspace_id` cannot serve that path.
- **Tenant-scoped uniqueness includes `workspace_id`** (e.g. `(workspace_id, name)`
  for `tags`/`roles`, `(workspace_id, user_id)` for
  `memberships`/`candidate_profiles`). The lone exceptions are columns **globally
  unique by construction** — a random `jobs.public_token`, a hashed token, a global
  invoice `number` — which can never collide across tenants.
- **Reverse-direction indexes are exempt** when their purpose is a cross-workspace
  lookup keyed by a global id — e.g. `memberships (user_id)` ("which workspaces
  does this user belong to") deliberately spans tenants and is not a tenant query.
- Because `workspace_id` leads, its leftmost prefix already serves the common
  "everything in this workspace" filter — so a **standalone `(workspace_id)` index
  is almost always redundant** and MUST NOT exist beside a `(workspace_id, …)`
  composite (§6).

---

## 6. Anti-Patterns (do not do these)

| Anti-pattern | Why it is wrong | Do instead |
|---|---|---|
| **Redundant / overlapping indexes** | `(workspace_id)` alongside `(workspace_id, status_code)` is dead weight — the composite's leftmost prefix already serves the single-column filter. It only slows writes and wastes space. | Keep the widest composite; drop indexes whose columns are a leftmost prefix of another (§2, §5). |
| **Standalone FK index duplicating a composite** | A separate `(current_stage_id)` index when `(workspace_id, current_stage_id)` exists and every query carries `workspace_id`. | Let the composite serve the FK constraint and the query; only add a standalone FK index when the FK is queried *without* `workspace_id`. |
| **Low-cardinality leading column** | Leading a composite with `status_code` (a handful of values) makes the index unselective and forces near-full scans within each value. | Lead with the high-cardinality, always-present `workspace_id` (§5); put the low-cardinality status *after* it. |
| **Indexing a boolean alone** | A lone `is_active` / `read_at`-as-flag index has ~2 values — rarely worth it. | Fold it into a composite as a trailing column where it co-filters (e.g. `(workspace_id, user_id, read_at)` for `notifications`). |
| **Indexing everything** | An index on every column "to be safe" multiplies write cost, bloats storage, and confuses the optimizer. | Add an index only when a real query needs it (§1). Start minimal; add on evidence. |
| **`LIKE '%term%'` for search** | A leading wildcard cannot use a B-tree index → full scan, and gets worse as the table grows. | Use the `FULLTEXT` projection on `search_documents` (§3). |
| **Unbounded list query** | `SELECT * FROM applications WHERE workspace_id = ?` with no limit grows without bound and defeats any index's benefit (`DATABASE_GUIDE.md` §13). | Always paginate; prefer keyset on the time-sortable `id` (§1). |
| **Redundant `created_at` index** | Adding `(workspace_id, created_at)` purely for "newest first" when `(workspace_id, id)` (or PK `id`) already orders by time. | Reuse the ULID order (§1); add a `created_at` composite only when `created_at` is a distinct range filter (as in `ai_usage`, `audit_logs`). |
| **JSON-buried filter** | Indexing or filtering on a value inside a JSON column that the app actually queries. | Promote it to a real column and index that (`DATABASE_GUIDE.md` §8). |

---

## 7. Self-Review Checklist (per table / per migration)

A table's index set is conformant only if **all** apply:

- [ ] `PRIMARY KEY (id)` on the ULID `CHAR(26)` — exactly one, no `AUTO_INCREMENT`
      (§1; `DATABASE_GUIDE.md` §3).
- [ ] **Every** foreign key column is indexed — by a standalone FK index *or* by
      being the leftmost column of a composite (§2; `DATABASE_GUIDE.md` §4).
- [ ] On workspace-scoped tables, `workspace_id` **leads** every composite index
      (§5; `DATABASE_GUIDE.md` §6).
- [ ] Tenant-scoped uniqueness includes `workspace_id`, except columns that are
      globally unique by construction (tokens, hashes, invoice numbers) (§5).
- [ ] Every index maps to a **named real query** (filter / join / sort /
      uniqueness) from `APPLICATION_FLOW.md` / `STATE_DIAGRAMS.md` (§1). No index
      lacks a justification.
- [ ] No index is a **leftmost prefix** of another (no redundant/overlapping
      indexes); no standalone `(workspace_id)` index beside a `(workspace_id, …)`
      composite (§6).
- [ ] No composite **leads with a low-cardinality** column; booleans are not
      indexed alone (§6).
- [ ] No `created_at` index that merely duplicates the time order already given by
      the ULID PK; `created_at` composites exist only for real range filters (§1, §6).
- [ ] Natural-language search uses `FULLTEXT` on the search projection, never
      `LIKE '%…%'` (§2, §6).
- [ ] Every list query the indexes serve is **paginated** (§1;
      `DATABASE_GUIDE.md` §13).
- [ ] Index names are descriptive and consistent (`idx_*`, `uq_*`, `ft_*`).
- [ ] Status/state columns are `*_code VARCHAR` (no MySQL `ENUM`); the index is on
      that column (`DATABASE_GUIDE.md` §7).

---

### Related Documents

`ENTITY_CATALOG.md` · `DATABASE_GUIDE.md` · `DATABASE_ARCHITECTURE.md` ·
`STATE_DIAGRAMS.md` · `APPLICATION_FLOW.md` · `RELATIONSHIP_MATRIX.md` ·
`ER_DIAGRAM.md` · `ARCHIVING_POLICY.md` · `SECURITY_GUIDE.md`
