# ER DIAGRAM — HaHireAI

> **Status:** Adopted (Phase 3) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `ENTITY_CATALOG.md`. **Cardinalities:** `RELATIONSHIP_MATRIX.md`.

---

## 1. How to read this document

This is the **visual entity-relationship reference** for the HaHireAI schema. It
renders the entities defined in `ENTITY_CATALOG.md` as Mermaid `erDiagram` blocks,
grouped by domain so each sub-diagram stays legible. It is a *map*, not a source
of truth: where this file and `ENTITY_CATALOG.md` ever disagree, the **catalog
wins**, and precise cardinalities/optionality live in `RELATIONSHIP_MATRIX.md`.

**Reading the diagrams:**

- **Primary keys** — every table has a ULID `id CHAR(26)` (`DATABASE_GUIDE.md` §3).
  Marked `PK` in the diagrams. IDs are app-generated; no `AUTO_INCREMENT`.
- **Foreign keys** — `<entity>_id CHAR(26)`, marked `FK`. Only the key/identifying
  columns are shown per entity; full column lists live in `DATABASE_ARCHITECTURE.md`.
- **Tenancy** — a `workspace_id FK` column means the table is **Workspace-scoped**
  (tenant data, `NOT NULL`, tenant-guarded). Tables **without** `workspace_id` are
  **Global** (platform-wide). See `ENTITY_CATALOG.md` §2 and §4 below.
- **Crow's-foot cardinality** — `||--o{` = one-to-many (zero-or-more children);
  `||--o|` = one-to-(zero-or-)one; `||--||` = one-to-one mandatory. The `||` end is
  the "one"/parent side; the `o{` / `o|` end is the "many"/optional-child side.
- **Pivots** — join tables (e.g. `role_permissions`) appear as entities between
  their two parents, each parent `||--o{` the pivot.

The two anchors of the whole model are **`workspaces`** (the tenant hub — almost
everything hangs off it) and **`users`** (the identity hub — the single human
account). See §3 (the spine) for how they tie the domains together.

---

## 2. Domain sub-diagrams

Each block below covers one domain from `ENTITY_CATALOG.md`. Cross-domain links to
`workspaces` and `users` are summarized in §3 to avoid repeating them everywhere.

### 2.1 Identity & Access

Global: `users`, `user_sessions`, `password_resets`, `remember_tokens`,
`workspaces`, `permissions`. Workspace-scoped: everything else here.

```mermaid
erDiagram
    users {
        char26 id PK
        char26 avatar_file_id FK
        string email
        datetime deleted_at
    }
    user_sessions {
        char26 id PK
        char26 user_id FK
    }
    password_resets {
        char26 id PK
        char26 user_id FK
    }
    remember_tokens {
        char26 id PK
        char26 user_id FK
    }
    workspaces {
        char26 id PK
        char26 owner_user_id FK
        string status_code
        datetime deleted_at
    }
    workspace_settings {
        char26 id PK
        char26 workspace_id FK
    }
    workspace_branding {
        char26 id PK
        char26 workspace_id FK
    }
    memberships {
        char26 id PK
        char26 workspace_id FK
        char26 user_id FK
        string status_code
        datetime deleted_at
    }
    invitations {
        char26 id PK
        char26 workspace_id FK
        char26 invited_by FK
        string email
        string status_code
    }
    roles {
        char26 id PK
        char26 workspace_id FK
        string name
        datetime deleted_at
    }
    permissions {
        char26 id PK
        string key
        string category
    }
    role_permissions {
        char26 id PK
        char26 role_id FK
        char26 permission_id FK
    }
    membership_roles {
        char26 id PK
        char26 membership_id FK
        char26 role_id FK
    }
    membership_permissions {
        char26 id PK
        char26 membership_id FK
        char26 permission_id FK
    }

    users ||--o{ user_sessions : "has"
    users ||--o{ password_resets : "requests"
    users ||--o{ remember_tokens : "holds"
    users ||--o{ workspaces : "owns"
    users ||--o{ memberships : "joins via"
    users ||--o{ invitations : "invited by"

    workspaces ||--o| workspace_settings : "configured by"
    workspaces ||--o| workspace_branding : "branded by"
    workspaces ||--o{ memberships : "has"
    workspaces ||--o{ invitations : "issues"
    workspaces ||--o{ roles : "defines"

    roles ||--o{ role_permissions : "grants"
    permissions ||--o{ role_permissions : "granted via"
    memberships ||--o{ membership_roles : "assigned"
    roles ||--o{ membership_roles : "assigned to"
    memberships ||--o{ membership_permissions : "direct grant"
    permissions ||--o{ membership_permissions : "directly granted"
```

A `membership` is unique per `(workspace_id, user_id)`. Authorization is composed
from `membership_roles` → `role_permissions` plus optional direct
`membership_permissions`. Roles are **data, not code** (`DOMAIN_MODEL.md` §6).

### 2.2 Recruitment

All workspace-scoped. `applications` is the candidacy root binding `user` + `job`
+ `workspace`; `candidate_profiles` is the per-`(user, workspace)` projection.

```mermaid
erDiagram
    jobs {
        char26 id PK
        char26 workspace_id FK
        char26 created_by FK
        string public_token
        string status_code
        datetime deleted_at
    }
    job_versions {
        char26 id PK
        char26 job_id FK
    }
    job_questions {
        char26 id PK
        char26 job_id FK
    }
    pipelines {
        char26 id PK
        char26 workspace_id FK
        char26 job_id FK
    }
    pipeline_stages {
        char26 id PK
        char26 pipeline_id FK
        int position
    }
    applications {
        char26 id PK
        char26 workspace_id FK
        char26 job_id FK
        char26 user_id FK
        char26 current_stage_id FK
        string status_code
        datetime deleted_at
    }
    application_stage_history {
        char26 id PK
        char26 application_id FK
        char26 moved_by FK
    }
    application_documents {
        char26 id PK
        char26 application_id FK
        char26 file_id FK
    }
    candidate_profiles {
        char26 id PK
        char26 workspace_id FK
        char26 user_id FK
    }
    candidate_notes {
        char26 id PK
        char26 candidate_profile_id FK
        char26 author_user_id FK
        datetime deleted_at
    }
    tags {
        char26 id PK
        char26 workspace_id FK
        string name
    }
    candidate_profile_tags {
        char26 id PK
        char26 candidate_profile_id FK
        char26 tag_id FK
    }
    interviews {
        char26 id PK
        char26 workspace_id FK
        char26 application_id FK
        string type
        string status_code
    }
    interview_sessions {
        char26 id PK
        char26 interview_id FK
        char26 ai_session_id FK
        char26 file_id FK
    }
    scorecards {
        char26 id PK
        char26 interview_id FK
        char26 evaluator_user_id FK
    }
    offers {
        char26 id PK
        char26 workspace_id FK
        char26 application_id FK
        char26 approved_by FK
        string status_code
        datetime deleted_at
    }
    employees {
        char26 id PK
        char26 workspace_id FK
        char26 user_id FK
        char26 application_id FK
        string status_code
        datetime deleted_at
    }
    talent_pool_entries {
        char26 id PK
        char26 workspace_id FK
        char26 candidate_profile_id FK
    }
    templates {
        char26 id PK
        char26 workspace_id FK
        string type
    }

    jobs ||--o{ job_versions : "snapshots"
    jobs ||--o{ job_questions : "asks"
    jobs ||--o{ pipelines : "uses"
    pipelines ||--o{ pipeline_stages : "ordered into"
    jobs ||--o{ applications : "receives"
    pipeline_stages ||--o{ applications : "current stage of"
    applications ||--o{ application_stage_history : "tracks"
    applications ||--o{ application_documents : "attaches"
    applications ||--o{ interviews : "schedules"
    applications ||--o| offers : "may extend"
    interviews ||--o{ interview_sessions : "runs"
    interviews ||--o{ scorecards : "evaluated by"
    candidate_profiles ||--o{ candidate_notes : "annotated by"
    candidate_profiles ||--o{ candidate_profile_tags : "tagged"
    tags ||--o{ candidate_profile_tags : "applied via"
    candidate_profiles ||--o{ talent_pool_entries : "saved as"
```

Cross-domain (drawn in §3): `users ||--o{ applications` (the candidate),
`candidate_profiles` per `(user, workspace)`, `application_documents.file_id` →
`files`, `interview_sessions.ai_session_id` → `ai_sessions`. `applications` is
unique per `(workspace_id, job_id, user_id)` — one candidacy per user+job
(`DOMAIN_MODEL.md` Invariant 4).

### 2.3 AI / Intelligence

Global catalog: `ai_providers`, `ai_models`, `prompt_templates`. Everything else
is workspace-scoped configuration and runtime.

```mermaid
erDiagram
    ai_providers {
        char26 id PK
        string key
    }
    ai_models {
        char26 id PK
        char26 provider_id FK
    }
    prompt_templates {
        char26 id PK
        string key
    }
    workspace_ai_settings {
        char26 id PK
        char26 workspace_id FK
        char26 provider_id FK
        char26 model_id FK
    }
    workspace_ai_keys {
        char26 id PK
        char26 workspace_id FK
        char26 provider_id FK
        bool use_platform_key
    }
    workspace_prompts {
        char26 id PK
        char26 workspace_id FK
        string key
    }
    ai_sessions {
        char26 id PK
        char26 workspace_id FK
        char26 provider_id FK
        char26 model_id FK
        string capability
        string status
    }
    ai_messages {
        char26 id PK
        char26 ai_session_id FK
        string role
    }
    ai_usage {
        char26 id PK
        char26 workspace_id FK
        char26 ai_session_id FK
    }
    ai_fallback_history {
        char26 id PK
        char26 ai_session_id FK
    }
    reports {
        char26 id PK
        char26 workspace_id FK
    }
    saved_views {
        char26 id PK
        char26 workspace_id FK
    }

    ai_providers ||--o{ ai_models : "offers"
    ai_providers ||--o{ workspace_ai_settings : "configured in"
    ai_models ||--o{ workspace_ai_settings : "selected in"
    ai_providers ||--o{ workspace_ai_keys : "keyed for"
    ai_providers ||--o{ ai_sessions : "serves"
    ai_models ||--o{ ai_sessions : "runs on"
    ai_sessions ||--o{ ai_messages : "exchanges"
    ai_sessions ||--o| ai_usage : "metered by"
    ai_sessions ||--o{ ai_fallback_history : "fell back"
```

Cross-domain (drawn in §3): every workspace-scoped table above hangs off
`workspaces`; `interview_sessions.ai_session_id` → `ai_sessions` ties AI runtime
to recruitment. `ai_usage` and `ai_fallback_history` are immutable (append-only).

### 2.4 Process / Workflow

Workspace-scoped except `background_jobs` (global infra queue).

```mermaid
erDiagram
    workflows {
        char26 id PK
        char26 workspace_id FK
        string status
    }
    workflow_executions {
        char26 id PK
        char26 workflow_id FK
        char26 workspace_id FK
        string status
        int retry_count
    }
    workflow_execution_steps {
        char26 id PK
        char26 execution_id FK
        string status
    }
    workflow_approvals {
        char26 id PK
        char26 execution_id FK
        char26 approver_user_id FK
        string status
    }
    scheduled_tasks {
        char26 id PK
        char26 workspace_id FK
        char26 workflow_id FK
        datetime next_run_at
    }
    background_jobs {
        char26 id PK
        string queue
        string status
    }

    workflows ||--o{ workflow_executions : "runs"
    workflows ||--o{ scheduled_tasks : "scheduled by"
    workflow_executions ||--o{ workflow_execution_steps : "steps"
    workflow_executions ||--o{ workflow_approvals : "awaits"
```

Cross-domain (drawn in §3): `workflows`, `workflow_executions`, `scheduled_tasks`
→ `workspaces`; `workflow_approvals.approver_user_id` → `users`. `background_jobs`
is a **global** runtime queue with no `workspace_id`.

### 2.5 Integration

Mostly workspace-scoped; `access_tokens` may be workspace **or** global
(PAT/workspace/system tokens — `ENTITY_CATALOG.md` §8).

```mermaid
erDiagram
    api_keys {
        char26 id PK
        char26 workspace_id FK
        string hashed_key
        string status
    }
    access_tokens {
        char26 id PK
        char26 user_id FK
        char26 workspace_id FK
        string type
        string hashed_token
    }
    webhooks {
        char26 id PK
        char26 workspace_id FK
        string url
        string status
    }
    webhook_deliveries {
        char26 id PK
        char26 webhook_id FK
        int response_code
    }
    integrations {
        char26 id PK
        char26 workspace_id FK
        string connector_key
    }
    oauth_connections {
        char26 id PK
        char26 workspace_id FK
        string provider
    }

    webhooks ||--o{ webhook_deliveries : "delivers"
```

Cross-domain (drawn in §3): `api_keys`, `webhooks`, `integrations`,
`oauth_connections` → `workspaces`; `access_tokens` optionally → `users` and/or
`workspaces` (nullable both, by token type). `webhook_deliveries` is immutable.

### 2.6 Commerce

Global catalog: `plans`, `coupons`, `feature_flags`. Workspace-scoped: the rest.

```mermaid
erDiagram
    plans {
        char26 id PK
        string key
        int price_amount
    }
    coupons {
        char26 id PK
        string code
    }
    feature_flags {
        char26 id PK
        string key
    }
    subscriptions {
        char26 id PK
        char26 workspace_id FK
        char26 plan_id FK
        string status_code
    }
    invoices {
        char26 id PK
        char26 workspace_id FK
        char26 pdf_file_id FK
        string status
    }
    payments {
        char26 id PK
        char26 workspace_id FK
        char26 invoice_id FK
        string status
    }
    payment_methods {
        char26 id PK
        char26 workspace_id FK
        string brand
    }
    workspace_feature_flags {
        char26 id PK
        char26 workspace_id FK
        string flag_key
        bool enabled
    }
    usage_counters {
        char26 id PK
        char26 workspace_id FK
        string metric
    }

    plans ||--o{ subscriptions : "subscribed to"
    invoices ||--o{ payments : "paid by"
```

Cross-domain (drawn in §3): `subscriptions`, `invoices`, `payments`,
`payment_methods`, `workspace_feature_flags`, `usage_counters` → `workspaces`.
One **active** `subscription` per workspace (`STATE_DIAGRAMS.md` §9). `payments`
is immutable. `invoices.pdf_file_id` → `files`.

### 2.7 Platform Services

Shared cross-cutting tables. Workspace-scoped except `system_settings` and
`system_audit_logs` (global).

```mermaid
erDiagram
    folders {
        char26 id PK
        char26 workspace_id FK
        char26 parent_id FK
        datetime deleted_at
    }
    files {
        char26 id PK
        char26 workspace_id FK
        char26 owner_user_id FK
        char26 folder_id FK
        datetime deleted_at
    }
    notifications {
        char26 id PK
        char26 workspace_id FK
        char26 user_id FK
        datetime read_at
    }
    search_documents {
        char26 id PK
        char26 workspace_id FK
        string entity_type
        char26 entity_id
    }
    audit_logs {
        char26 id PK
        char26 workspace_id FK
        char26 actor_user_id FK
        string action
    }
    system_settings {
        char26 id PK
        string setting_key
    }
    system_audit_logs {
        char26 id PK
        string action
    }

    folders ||--o{ folders : "nests"
    folders ||--o{ files : "contains"
```

Cross-domain (drawn in §3): `folders`, `files`, `notifications`,
`search_documents`, `audit_logs` → `workspaces`; `files.owner_user_id`,
`notifications.user_id`, `audit_logs.actor_user_id` → `users`. `files` is the
shared blob store referenced by `application_documents`, `interview_sessions`,
`invoices`, and `users.avatar_file_id`. `audit_logs` and `system_audit_logs` are
immutable.

### 2.8 Operations (Observability)

Mostly global; `error_events` and `alerts` carry a **nullable** `workspace_id`
(global + workspace).

```mermaid
erDiagram
    error_events {
        char26 id PK
        char26 workspace_id FK
        string severity
        string status
    }
    metrics {
        char26 id PK
        string name
        datetime recorded_at
    }
    health_checks {
        char26 id PK
        string component
        datetime checked_at
    }
    backups {
        char26 id PK
        string type
        string status
    }
    alerts {
        char26 id PK
        char26 workspace_id FK
        string type
        string status
    }
```

`metrics`, `health_checks` are immutable. `error_events` and `alerts` are dual-scope:
`workspace_id` is nullable, so a row is either platform-level (NULL) or scoped to a
tenant. `metrics`, `health_checks`, `backups` are purely global infra.

---

## 3. The spine — `workspaces` (tenant hub) and `users` (identity hub)

Two entities tie the whole schema together. **`workspaces`** is the tenant hub:
every workspace-scoped table carries `workspace_id` → `workspaces.id`. **`users`**
is the identity hub: the single human account that participates in workspaces
through memberships, applications, employment, and ownership of artifacts.

The diagram below shows only the *representative* links from each hub (one per
domain) so the spine stays readable; the full set is each domain's `workspace_id`
FK from §2 plus every `user_id` FK noted there.

```mermaid
erDiagram
    users {
        char26 id PK
        string email
    }
    workspaces {
        char26 id PK
        char26 owner_user_id FK
    }
    memberships {
        char26 id PK
        char26 workspace_id FK
        char26 user_id FK
    }
    applications {
        char26 id PK
        char26 workspace_id FK
        char26 job_id FK
        char26 user_id FK
    }
    candidate_profiles {
        char26 id PK
        char26 workspace_id FK
        char26 user_id FK
    }
    jobs {
        char26 id PK
        char26 workspace_id FK
        char26 created_by FK
    }
    employees {
        char26 id PK
        char26 workspace_id FK
        char26 user_id FK
    }
    files {
        char26 id PK
        char26 workspace_id FK
        char26 owner_user_id FK
    }
    ai_sessions {
        char26 id PK
        char26 workspace_id FK
    }
    subscriptions {
        char26 id PK
        char26 workspace_id FK
        char26 plan_id FK
    }
    workflows {
        char26 id PK
        char26 workspace_id FK
    }
    audit_logs {
        char26 id PK
        char26 workspace_id FK
        char26 actor_user_id FK
    }

    users ||--o{ workspaces : "owns"
    users ||--o{ memberships : "member via"
    users ||--o{ applications : "applies as candidate"
    users ||--o{ candidate_profiles : "projected as"
    users ||--o{ jobs : "creates"
    users ||--o{ employees : "employed as"
    users ||--o{ files : "owns"
    users ||--o{ audit_logs : "acts in"

    workspaces ||--o{ memberships : "scopes"
    workspaces ||--o{ applications : "scopes"
    workspaces ||--o{ candidate_profiles : "scopes"
    workspaces ||--o{ jobs : "scopes"
    workspaces ||--o{ employees : "scopes"
    workspaces ||--o{ files : "scopes"
    workspaces ||--o{ ai_sessions : "scopes"
    workspaces ||--o{ subscriptions : "scopes"
    workspaces ||--o{ workflows : "scopes"
    workspaces ||--o{ audit_logs : "scopes"
```

**One human, many contexts.** The same `user` can be a workspace owner, a member
elsewhere, a candidate via an `application`, and an `employee` post-hire — all
projections of one identity, never duplicate accounts (`DOMAIN_MODEL.md` §7,
Invariant 1). `candidate_profiles` is the per-`(user, workspace)` view, unique on
`(workspace_id, user_id)`; there is **no global candidate table**.

---

## 4. Isolation & global anchors

**Tenant isolation (`workspace_id`).** Every entity in §2.1–§2.7 that shows a
`workspace_id FK` is **Workspace-scoped**: the column is `CHAR(26) NOT NULL`, has a
real FK to `workspaces.id`, is indexed, and leads composite indexes. Every tenant
query is filtered by the active workspace through the repository tenant guard —
non-negotiable (`DATABASE_GUIDE.md` §6). Tenant-scoped uniqueness always includes
`workspace_id` (e.g. `applications` unique on `(workspace_id, job_id, user_id)`;
`memberships` and `candidate_profiles` unique on `(workspace_id, user_id)`).

**The one tenant-root.** `workspaces` is the single row-per-tenant table — keyed by
`id`, **not** `workspace_id`. It is itself **Global** but is the parent every
workspace-scoped FK points to.

**Global anchors (no `workspace_id`).** Per `ENTITY_CATALOG.md` §2, the global
tables are:

- **Identity:** `users`, `user_sessions`, `password_resets`, `remember_tokens`,
  `workspaces` (tenant root).
- **Authorization & commerce catalogs:** `permissions`, `plans`, `coupons`,
  `feature_flags`.
- **AI catalog:** `ai_providers`, `ai_models`, `prompt_templates` (global library).
- **Platform/operations:** `system_settings`, `system_audit_logs`, `metrics`,
  `health_checks`, `backups`, `background_jobs`, plus `error_events` and `alerts`
  (which are **dual-scope** — `workspace_id` nullable).

The four primary catalogs other tenant data references are **`workspaces`**,
**`users`**, **`plans`**, **`permissions`**, plus the **`ai_providers`** registry —
the stable global anchors the rest of the model hangs off. When in doubt, a table
is workspace-scoped (`DATABASE_GUIDE.md` §6.3).

**Immutable / append-only** (neither `updated_at` nor `deleted_at`): `job_versions`,
`application_stage_history`, `ai_usage`, `ai_fallback_history`,
`workflow_execution_steps`, `payments`, `webhook_deliveries`, `audit_logs`,
`system_audit_logs`, `metrics`, `health_checks`. **Soft-delete** tables carry
`deleted_at` (e.g. `users`, `workspaces`, `memberships`, `roles`, `jobs`,
`applications`, `candidate_notes`, `offers`, `employees`, `folders`, `files`).
See `ARCHIVING_POLICY.md` and `VERSIONING_POLICY.md`.

---

### Related Documents

`ENTITY_CATALOG.md` · `DOMAIN_MODEL.md` · `DATABASE_GUIDE.md` ·
`DATABASE_ARCHITECTURE.md` · `RELATIONSHIP_MATRIX.md` · `INDEXING_GUIDE.md` ·
`STATE_DIAGRAMS.md` · `ARCHIVING_POLICY.md` · `VERSIONING_POLICY.md`
