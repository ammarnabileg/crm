# RELATIONSHIP MATRIX — HaHireAI

> **Status:** Adopted (Phase 3) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `ENTITY_CATALOG.md`, `ER_DIAGRAM.md`.

---

## 1. Purpose & how to read this matrix

This document enumerates **every relationship** between the entities listed in
`ENTITY_CATALOG.md`, using the **exact table names** defined there. It is the
relationship-level companion to the catalog: the catalog says *what exists and
its scope*; this matrix says *how each table connects to the others*. Column
types, indexes, and `ON DELETE`/`ON UPDATE` clauses are specified in
`DATABASE_GUIDE.md` §4 and `INDEXING_GUIDE.md`; the conceptual intent governs in
`DOMAIN_MODEL.md`. Where any of these disagree, `DOMAIN_MODEL.md` intent wins and
the others are corrected.

### 1.1 Relationship types

- **1:1** — at most one row on each side (e.g. `applications` ↔ `offers` is
  1:0..1). Modeled as an FK on the dependent table, made unique.
- **1:N** — one parent row owns many child rows (e.g. one `jobs` row has many
  `applications`). Modeled as an FK column on the child (the "N" side) pointing
  at the parent's `id`.
- **N:M** — many rows on each side relate to many on the other (e.g. `roles` ↔
  `permissions`). MySQL cannot express this directly, so it is modeled with a
  **pivot (join) table** carrying one FK to each parent. Per `DATABASE_GUIDE.md`
  §5 the pivot is named after both entities, snake_case, alphabetical
  (`role_permissions`).

### 1.2 Conventions used in the tables below

- **Via (FK or pivot)** names the FK column that carries the relationship, or the
  pivot table for an N:M. FK columns follow `<entity>_id` (`DATABASE_GUIDE.md`
  §4). Every FK is `CHAR(26)`, indexed, and declares an explicit referential
  action; this matrix records *direction and cardinality*, not the DDL.
- A relationship is read **from the entity in the first column**. The reciprocal
  ("inverse") side is implied and not repeated, except for self-references.
- `?` after a column marks a **nullable** (optional) FK.

### 1.3 The implicit tenant relationship (`workspace_id`)

Every **Workspace-scoped** entity carries a NOT NULL `workspace_id` FK to
`workspaces.id` (`ENTITY_CATALOG.md` §1–2, `WORKSPACE_MODEL.md` §3). This is the
**implicit tenant relationship**: it makes every business row a child of exactly
one `workspaces` row and is the single most important isolation invariant in the
system. To keep the per-domain tables readable, this `workspace_id → workspaces`
edge is stated **once** here and in §8 rather than repeated on every row; the
per-domain rows below list the *other* (non-tenant) relationships, noting
`workspace_id` only where it is the primary or only edge. **Pivot** tables inherit
the scope of their parents and are tenant-guarded transitively through them.

---

## 2. Identity & Access

| Entity | Relationship type | Related entity | Via (FK or pivot) | Notes |
|---|---|---|---|---|
| `users` | 1:N (inverse) | `user_sessions`, `password_resets`, `remember_tokens` | child `user_id` | Global identity; auth artifacts hang off it. |
| `users` | 1:0..1 | `files` | `avatar_file_id?` | Optional avatar; SET NULL on file removal. |
| `users` | 1:N (inverse) | `workspaces` | `workspaces.owner_user_id` | A user may own many workspaces. |
| `users` | 1:N (inverse) | `memberships`, `applications`, `candidate_profiles`, `employees` | child `user_id` | One identity projected into many workspace contexts (`DOMAIN_MODEL.md` §7). |
| `user_sessions` | N:1 | `users` | `user_id` | Global. Active auth sessions. |
| `password_resets` | N:1 | `users` | `user_id` | Global. Short-lived tokens. |
| `remember_tokens` | N:1 | `users` | `user_id` | Global. Rotating tokens. |
| `workspaces` | N:1 | `users` | `owner_user_id` | Tenant root; keyed by `id`, not `workspace_id`. |
| `workspaces` | 1:1 | `workspace_settings` | `workspace_settings.workspace_id` (unique) | One settings row per tenant. |
| `workspaces` | 1:1 | `workspace_branding` | `workspace_branding.workspace_id` (unique) | One branding row per tenant. |
| `workspaces` | 1:N (inverse) | all Workspace-scoped tables | child `workspace_id` | The implicit tenant relationship (§1.3, §8). |
| `workspace_settings` | N:1 | `workspaces` | `workspace_id` | Owned child of the workspace. |
| `workspace_branding` | N:1 | `workspaces` | `workspace_id` | Owned child of the workspace. |
| `memberships` | N:1 | `workspaces` | `workspace_id` | Link User↔Workspace. **Unique (workspace_id, user_id)** — one membership per user per tenant. |
| `memberships` | N:1 | `users` | `user_id` | The member identity. |
| `memberships` | 0..1:1 | `invitations` | `invitation_id?` | Optional origin invitation. |
| `memberships` | N:M | `roles` | pivot `membership_roles` | Assigned roles (§6). |
| `memberships` | N:M | `permissions` | pivot `membership_permissions` | Optional direct grants (§6). |
| `invitations` | N:1 | `workspaces` | `workspace_id` | Pending membership offer. |
| `invitations` | N:1 | `users` | `invited_by` | Inviting user. |
| `roles` | N:1 | `workspaces` | `workspace_id` | Workspace-defined bundle; **no reserved roles** (`WORKSPACE_MODEL.md` §8). |
| `roles` | N:M | `permissions` | pivot `role_permissions` | Permission bundle (§6). |
| `permissions` | N:M (inverse) | `roles`, `memberships` | pivots `role_permissions`, `membership_permissions` | Global catalog of capability keys; referenced, never tenant-scoped. |
| `role_permissions` | N:1 + N:1 | `roles`, `permissions` | `role_id`, `permission_id` | Pivot (Workspace scope via `roles`). |
| `membership_roles` | N:1 + N:1 | `memberships`, `roles` | `membership_id`, `role_id` | Pivot (Workspace). |
| `membership_permissions` | N:1 + N:1 | `memberships`, `permissions` | `membership_id`, `permission_id` | Pivot (Workspace). Direct grants on top of roles. |

---

## 3. Platform Services

| Entity | Relationship type | Related entity | Via (FK or pivot) | Notes |
|---|---|---|---|---|
| `folders` | N:1 | `workspaces` | `workspace_id` | Tenant-scoped tree root container. |
| `folders` | N:0..1 | `folders` | `parent_id?` | **Self-reference** (hierarchy). NULL parent = top level; no cycles by construction. |
| `files` | N:1 | `workspaces` | `workspace_id` | Tenant-scoped storage. |
| `files` | N:1 | `users` | `owner_user_id` | File owner. |
| `files` | N:0..1 | `folders` | `folder_id?` | Optional containing folder. |
| `files` | 1:N (inverse) | `application_documents`, `interview_sessions`, `invoices`, `users` | referencing `file_id`/`avatar_file_id`/`pdf_file_id` | A file may be referenced by documents, recordings, invoice PDFs, avatars. |
| `notifications` | N:1 | `workspaces` | `workspace_id` | Per-user, workspace-contextual. |
| `notifications` | N:1 | `users` | `user_id` | Recipient. |
| `search_documents` | N:1 | `workspaces` | `workspace_id` | Index projection. `entity_type` + `entity_id` are a **polymorphic logical pointer**, not an FK (`DATABASE_GUIDE.md` §4: no FK across the targeted entities). |
| `audit_logs` | N:1 | `workspaces` | `workspace_id` | Immutable. `actor_user_id` references the acting user; `entity_type`/`entity_id` are a logical pointer (no FK). |
| `audit_logs` | N:1 | `users` | `actor_user_id` | Who performed the action. |
| `system_settings` | — | — | — | Global key/value; no relationships. |
| `system_audit_logs` | N:1 | `users` | `actor_user_id` | Global immutable; platform actions by System Owners. |

---

## 4. Recruitment

| Entity | Relationship type | Related entity | Via (FK or pivot) | Notes |
|---|---|---|---|---|
| `jobs` | N:1 | `workspaces` | `workspace_id` | Requisition/posting; versioned, soft-deletable. |
| `jobs` | N:1 | `users` | `created_by` | Author. |
| `jobs` | 1:N (inverse) | `job_versions` | `job_versions.job_id` | Snapshot history. |
| `jobs` | 1:N (inverse) | `job_questions` | `job_questions.job_id` | Screening questions (owned children). |
| `jobs` | 1:N (inverse) | `applications` | `applications.job_id` | Candidacies for this job. |
| `jobs` | 1:0..N (inverse) | `pipelines` | `pipelines.job_id?` | A job may have a dedicated pipeline. |
| `job_versions` | N:1 | `jobs` | `job_id` | Immutable snapshot per publish/edit. |
| `job_questions` | N:1 | `jobs` | `job_id` | Custom screening questions. |
| `pipelines` | N:1 | `workspaces` | `workspace_id` | Stage definitions. |
| `pipelines` | N:0..1 | `jobs` | `job_id?` | NULL `job_id` = reusable template; non-NULL = job-specific. |
| `pipelines` | 1:N (inverse) | `pipeline_stages` | `pipeline_stages.pipeline_id` | Ordered stages. |
| `pipeline_stages` | N:1 | `pipelines` | `pipeline_id` | `position` orders the Kanban columns. |
| `pipeline_stages` | 1:N (inverse) | `applications` | `applications.current_stage_id` | Applications currently resting in this stage. |
| `applications` | N:1 | `workspaces` | `workspace_id` | The User+Job+Workspace candidacy. **Unique (job_id, user_id)** — a user applies to a given job at most once (`DOMAIN_MODEL.md` §6.4). |
| `applications` | N:1 | `jobs` | `job_id` | The job applied to. |
| `applications` | N:1 | `users` | `user_id` | The applicant identity. |
| `applications` | N:0..1 | `pipeline_stages` | `current_stage_id?` | Current Kanban position. |
| `applications` | 1:N (inverse) | `application_stage_history` | `application_id` | Immutable timeline (CASCADE: owned child). |
| `applications` | 1:N (inverse) | `application_documents` | `application_id` | Attached documents (owned children). |
| `applications` | 1:N (inverse) | `interviews` | `interviews.application_id` | Scheduled evaluations. |
| `applications` | 1:0..1 | `offers` | `offers.application_id` (unique) | At most one offer per application (§7). |
| `applications` | 1:0..1 | `employees` | `employees.application_id?` | Post-hire context derived from this application (§7). |
| `application_stage_history` | N:1 | `applications` | `application_id` | Immutable. `from_stage`/`to_stage` reference `pipeline_stages`; `moved_by` references `users`. |
| `application_stage_history` | N:1 | `users` | `moved_by` | Who moved the card. |
| `application_documents` | N:1 | `applications` | `application_id` | Owned child. |
| `application_documents` | N:1 | `files` | `file_id` | The stored document. |
| `candidate_profiles` | N:1 | `workspaces` | `workspace_id` | Per-(User,Workspace) **view/projection**. **Unique (workspace_id, user_id)** — exactly one profile per user per tenant (`DOMAIN_MODEL.md` §6.3). |
| `candidate_profiles` | N:1 | `users` | `user_id` | The projected identity (not an account). |
| `candidate_profiles` | 1:N (inverse) | `candidate_notes` | `candidate_notes.candidate_profile_id` | Notes about this candidate. |
| `candidate_profiles` | N:M | `tags` | pivot `candidate_profile_tags` | Workspace tags (§6). |
| `candidate_profiles` | 1:N (inverse) | `talent_pool_entries` | `talent_pool_entries.candidate_profile_id` | Saved/passive listings. |
| `candidate_notes` | N:1 | `candidate_profiles` | `candidate_profile_id` | Soft-deletable; `visibility` private/shared. |
| `candidate_notes` | N:1 | `users` | `author_user_id` | Note author. |
| `tags` | N:1 | `workspaces` | `workspace_id` | Workspace label (name, color). |
| `tags` | N:M (inverse) | `candidate_profiles` | pivot `candidate_profile_tags` | Applied to candidates. |
| `candidate_profile_tags` | N:1 + N:1 | `candidate_profiles`, `tags` | `candidate_profile_id`, `tag_id` | Pivot (Workspace). |
| `interviews` | N:1 | `workspaces` | `workspace_id` | `type` ai/human. |
| `interviews` | N:1 | `applications` | `application_id` | Evaluation attached to a candidacy. |
| `interviews` | 1:N (inverse) | `interview_sessions` | `interview_sessions.interview_id` | Run(s) of the interview. |
| `interviews` | 1:N (inverse) | `scorecards` | `scorecards.interview_id` | Evaluator scores. |
| `interview_sessions` | N:1 | `interviews` | `interview_id` | A bounded run. |
| `interview_sessions` | N:0..1 | `ai_sessions` | `ai_session_id?` | Links an AI-run interview to its AI session. |
| `interview_sessions` | N:0..1 | `files` | `file_id?` | Optional recording. |
| `scorecards` | N:1 | `interviews` | `interview_id` | `scores` (json), advisory `recommendation`. |
| `scorecards` | N:1 | `users` | `evaluator_user_id` | The evaluator. |
| `offers` | N:1 | `workspaces` | `workspace_id` | Soft-deletable hiring proposal. |
| `offers` | 1:1 | `applications` | `application_id` (unique) | One offer per application (§7). |
| `offers` | N:0..1 | `users` | `approved_by?` | Approver (set when approved). |
| `employees` | N:1 | `workspaces` | `workspace_id` | Post-hire context. |
| `employees` | N:1 | `users` | `user_id` | The hired identity. |
| `employees` | N:0..1 | `applications` | `application_id?` | Source candidacy, when hired through the pipeline. |
| `talent_pool_entries` | N:1 | `workspaces` | `workspace_id` | Saved/passive/past candidates. |
| `talent_pool_entries` | N:1 | `candidate_profiles` | `candidate_profile_id` | The saved candidate. |
| `templates` | N:1 | `workspaces` | `workspace_id` | Versioned; `type` job/pipeline/email/screening. |

---

## 5. Intelligence (AI)

| Entity | Relationship type | Related entity | Via (FK or pivot) | Notes |
|---|---|---|---|---|
| `ai_providers` | 1:N (inverse) | `ai_models` | `ai_models.provider_id` | Global provider registry. |
| `ai_providers` | 1:N (inverse) | `workspace_ai_settings`, `workspace_ai_keys`, `ai_sessions` | child `provider_id` | Referenced by workspace config (global → workspace reference). |
| `ai_models` | N:1 | `ai_providers` | `provider_id` | Global model catalog with capabilities. |
| `ai_models` | 1:N (inverse) | `workspace_ai_settings`, `ai_sessions` | child `model_id` | Referenced by config and sessions. |
| `prompt_templates` | — | — | — | Global versioned prompt library; no FKs (shared reference data). |
| `workspace_ai_settings` | N:1 | `workspaces` | `workspace_id` | Per-tenant AI config. |
| `workspace_ai_settings` | N:1 | `ai_providers` | `provider_id` | Selected provider. |
| `workspace_ai_settings` | N:1 | `ai_models` | `model_id` | Selected model; `fallback` json. |
| `workspace_ai_keys` | N:1 | `workspaces` | `workspace_id` | Encrypted per-tenant keys; never shown after save. |
| `workspace_ai_keys` | N:1 | `ai_providers` | `provider_id` | Provider the key is for; `use_platform_key` toggles BYO vs platform. |
| `workspace_prompts` | N:1 | `workspaces` | `workspace_id` | Versioned per-tenant prompts (key, version, locale). |
| `ai_sessions` | N:1 | `workspaces` | `workspace_id` | One bounded AI interaction. |
| `ai_sessions` | N:1 | `ai_providers` | `provider_id` | Provider used. |
| `ai_sessions` | N:1 | `ai_models` | `model_id` | Model used. |
| `ai_sessions` | 1:N (inverse) | `ai_messages`, `ai_usage`, `ai_fallback_history` | child `ai_session_id` | Messages, usage, and fallbacks belong to the session. |
| `ai_sessions` | 1:0..1 (inverse) | `interview_sessions` | `interview_sessions.ai_session_id?` | An AI session may back an interview run. |
| `ai_messages` | N:1 | `ai_sessions` | `ai_session_id` | role, content, tokens. |
| `ai_usage` | N:1 | `workspaces` | `workspace_id` | Immutable usage metering. |
| `ai_usage` | N:1 | `ai_sessions` | `ai_session_id` | tokens, latency, cost, status. |
| `ai_fallback_history` | N:1 | `ai_sessions` | `ai_session_id` | Immutable; from/to provider, reason. |
| `reports` / `saved_views` | N:1 | `workspaces` | `workspace_id` | Analytics definitions (json). |

---

## 6. Process (Workflow)

| Entity | Relationship type | Related entity | Via (FK or pivot) | Notes |
|---|---|---|---|---|
| `workflows` | N:1 | `workspaces` | `workspace_id` | Versioned; `definition` json, `status` draft/published. |
| `workflows` | 1:N (inverse) | `workflow_executions`, `scheduled_tasks` | child `workflow_id` | Runs and schedules. |
| `workflow_executions` | N:1 | `workspaces` | `workspace_id` | A run instance. |
| `workflow_executions` | N:1 | `workflows` | `workflow_id` | The definition being run. |
| `workflow_executions` | 1:N (inverse) | `workflow_execution_steps`, `workflow_approvals` | child `execution_id` | Steps and approval gates (owned children). |
| `workflow_execution_steps` | N:1 | `workflow_executions` | `execution_id` | Immutable; step, status, input/output/error. |
| `workflow_approvals` | N:1 | `workflow_executions` | `execution_id` | Approval gate. |
| `workflow_approvals` | N:1 | `users` | `approver_user_id` | The approver. |
| `scheduled_tasks` | N:1 | `workspaces` | `workspace_id` | cron, `next_run_at`. |
| `scheduled_tasks` | N:0..1 | `workflows` | `workflow_id?` | The workflow to trigger. |
| `background_jobs` | — | — | — | Global infra queue (queue, payload, attempts); runtime only, no domain FKs. |

---

## 7. Integration

| Entity | Relationship type | Related entity | Via (FK or pivot) | Notes |
|---|---|---|---|---|
| `api_keys` | N:1 | `workspaces` | `workspace_id` | `hashed_key`, scopes, expiry. |
| `access_tokens` | N:0..1 | `users` | `user_id?` | PAT (user), workspace, or system token. |
| `access_tokens` | N:0..1 | `workspaces` | `workspace_id?` | Workspace/Global scope: both FKs nullable by token `type`. |
| `webhooks` | N:1 | `workspaces` | `workspace_id` | url, events, encrypted `secret`. |
| `webhooks` | 1:N (inverse) | `webhook_deliveries` | `webhook_deliveries.webhook_id` | Delivery attempts. |
| `webhook_deliveries` | N:1 | `webhooks` | `webhook_id` | Immutable; event, status, response_code (owned child, CASCADE). |
| `integrations` | N:1 | `workspaces` | `workspace_id` | connector_key, encrypted config, health. |
| `oauth_connections` | N:1 | `workspaces` | `workspace_id` | provider, encrypted tokens, scopes. |

---

## 8. Commerce

| Entity | Relationship type | Related entity | Via (FK or pivot) | Notes |
|---|---|---|---|---|
| `plans` | 1:N (inverse) | `subscriptions` | `subscriptions.plan_id` | Global commercial tier; referenced, never copied (`DOMAIN_MODEL.md` §6.7). |
| `subscriptions` | N:1 | `workspaces` | `workspace_id` | **One active per workspace** (§9); the workspace↔current-subscription 1:1 (active) lives here. |
| `subscriptions` | N:1 | `plans` | `plan_id` | The subscribed tier. |
| `invoices` | N:1 | `workspaces` | `workspace_id` | number, amount, items (json). |
| `invoices` | N:0..1 | `files` | `pdf_file_id?` | Generated PDF. |
| `invoices` | 1:N (inverse) | `payments` | `payments.invoice_id` | Payment attempts/records. |
| `payments` | N:1 | `workspaces` | `workspace_id` | Immutable; provider, provider_ref, status. |
| `payments` | N:1 | `invoices` | `invoice_id` | The invoice being paid. |
| `payment_methods` | N:1 | `workspaces` | `workspace_id` | provider, encrypted token, brand, last4. |
| `coupons` | — | — | — | Global code/type/value/expiry; applied logically, no hard FK. |
| `feature_flags` | 1:N (inverse) | `workspace_feature_flags` | `workspace_feature_flags.flag_key` | Global flag definitions. |
| `workspace_feature_flags` | N:1 | `workspaces` | `workspace_id` | Per-tenant override of a global flag (`flag_key`). |
| `usage_counters` | N:1 | `workspaces` | `workspace_id` | metric, period, value (entitlement metering). |

---

## 9. Operations (Observability)

| Entity | Relationship type | Related entity | Via (FK or pivot) | Notes |
|---|---|---|---|---|
| `error_events` | N:0..1 | `workspaces` | `workspace_id?` | Global **+** Workspace: nullable tenant link (platform vs tenant errors). |
| `metrics` | — | — | — | Global immutable; name/value/labels, no FKs. |
| `health_checks` | — | — | — | Global immutable; component/status, no FKs. |
| `backups` | — | — | — | Global; type/status/location/size, no FKs. |
| `alerts` | N:0..1 | `workspaces` | `workspace_id?` | Global **+** Workspace: nullable tenant link. |

---

## 10. N:M (many-to-many) relationships

Each many-to-many is realized by a dedicated **pivot table** (`DATABASE_GUIDE.md`
§5), carrying exactly one FK to each parent. Pivots inherit the scope of their
parents and have a ULID `id` of their own.

| Pivot table | Side A | Side B | A FK | B FK | Scope |
|---|---|---|---|---|---|
| `role_permissions` | `roles` | `permissions` | `role_id` | `permission_id` | Workspace (via `roles`) |
| `membership_roles` | `memberships` | `roles` | `membership_id` | `role_id` | Workspace |
| `membership_permissions` | `memberships` | `permissions` | `membership_id` | `permission_id` | Workspace |
| `candidate_profile_tags` | `candidate_profiles` | `tags` | `candidate_profile_id` | `tag_id` | Workspace |

Notes:
- A `membership`'s **effective permissions** are the union of permissions granted
  through its roles (`membership_roles` → `role_permissions`) plus any direct
  grants (`membership_permissions`) — exactly the model in `PERMISSION_MODEL.md`.
- `permissions` is a **global** catalog; the pivots that reference it
  (`role_permissions`, `membership_permissions`) are workspace-scoped via their
  *other* parent, never via `permissions`.
- No other entity pair is N:M. Relationships that look many-to-many at first
  glance (e.g. a job's hiring team) are modeled through workspace memberships and
  permissions, not a dedicated pivot, per `DOMAIN_MODEL.md`.

---

## 11. 1:1 (one-to-one) relationships

These are enforced with a **UNIQUE** constraint on the dependent FK (and, for
"current-X" cases, a partial/applicative uniqueness on the active row).

| Relationship | Type | Enforced by | Notes |
|---|---|---|---|
| `applications` ↔ `offers` | 1:0..1 | UNIQUE(`offers.application_id`) | An application has at most one offer (`DOMAIN_MODEL.md` §5). |
| `workspaces` ↔ `workspace_settings` | 1:1 | UNIQUE(`workspace_settings.workspace_id`) | Exactly one settings row per tenant. |
| `workspaces` ↔ `workspace_branding` | 1:1 | UNIQUE(`workspace_branding.workspace_id`) | Exactly one branding row per tenant. |
| `workspaces` ↔ current `subscriptions` | 1:0..1 (active) | one **active** subscription per `workspace_id` (§9 status rule) | History rows may exist; only one is active. |
| `applications` ↔ `employees` | 1:0..1 | UNIQUE(`employees.application_id`) | A hired application yields at most one employee context. |
| `interview_sessions` ↔ `ai_sessions` | 1:0..1 | UNIQUE(`interview_sessions.ai_session_id`) | An AI session backs at most one interview run. |

> The two **compound-unique** business keys are 1:1-style guarantees inside a
> tenant, listed here for completeness: `candidate_profiles` is UNIQUE
> (`workspace_id`, `user_id`) and `applications` is UNIQUE (`job_id`, `user_id`).

---

## 12. Tenancy relationship (N:1 to `workspaces`)

Restating the rule in §1.3 as a first-class relationship: **every
Workspace-scoped entity has an N:1 relationship to `workspaces` via a NOT NULL
`workspace_id`.** This includes — and is not limited to — `workspace_settings`,
`workspace_branding`, `memberships`, `invitations`, `roles`, `folders`, `files`,
`notifications`, `search_documents`, `audit_logs`, `jobs`, `job_versions`,
`job_questions`, `pipelines`, `pipeline_stages`, `applications`,
`application_stage_history`, `application_documents`, `candidate_profiles`,
`candidate_notes`, `tags`, `interviews`, `interview_sessions`, `scorecards`,
`offers`, `employees`, `talent_pool_entries`, `templates`,
`workspace_ai_settings`, `workspace_ai_keys`, `workspace_prompts`, `ai_sessions`,
`ai_messages`, `ai_usage`, `ai_fallback_history`, `reports`/`saved_views`,
`workflows`, `workflow_executions`, `workflow_execution_steps`,
`workflow_approvals`, `scheduled_tasks`, `api_keys`, `webhooks`,
`webhook_deliveries`, `integrations`, `oauth_connections`, `subscriptions`,
`invoices`, `payments`, `payment_methods`, `workspace_feature_flags`, and
`usage_counters`.

Three tables carry a **nullable** `workspace_id` because they are *both* global
and tenant-scoped: `error_events`, `alerts`, and `access_tokens` (the latter also
nullable `user_id`). `workspaces` itself is the tenant root and is keyed by `id`,
so it has **no** `workspace_id`. Pivot tables reach `workspaces` transitively
through their workspace-scoped parent and are tenant-guarded the same way.

---

## 13. Self-review (Phase 3 gate)

**No cyclic ownership.** The graph of *ownership* FKs (parent → owned child) is a
DAG. Roots are `users`, `workspaces`, and the global catalogs (`permissions`,
`plans`, `ai_providers`/`ai_models`, `prompt_templates`, `coupons`,
`feature_flags`). All other tables descend from these via N:1 edges; no chain of
ownership FKs returns to its origin.

- The only **self-references** are `folders.parent_id` (folder tree) and
  `pipeline_stages.position` (ordering — an attribute, not an FK). The folder
  tree is acyclic by construction (a folder cannot be its own ancestor; enforced
  in the service layer), so it does not create an ownership cycle.
- Apparent "back-edges" are *non-owning reference* edges, not ownership cycles,
  so the DAG holds: `interview_sessions.ai_session_id` (a recruitment row
  pointing at an intelligence row), `employees.application_id`, and
  `offers.application_id` all point **upward** to an existing aggregate and never
  loop back down.

**No illogical relationships.** Every FK connects entities that genuinely relate
in `DOMAIN_MODEL.md`. There is no global candidate table — `candidate_profiles`
is a per-tenant projection keyed **UNIQUE (`workspace_id`, `user_id`)**, so a
user has exactly one profile per workspace and none is shared across tenants
(`DOMAIN_MODEL.md` §6.3, `ENTITY_CATALOG.md` §11). Candidacy is bound by
`applications` **UNIQUE (`job_id`, `user_id`)**, so a user applies to a given job
at most once and re-application produces history, not duplicate rows
(`DOMAIN_MODEL.md` §6.4).

**No cross-module FKs.** No relationship in this matrix crosses a module boundary
into another module's private tables; cross-module links resolve through
contracts, not database FKs (`DATABASE_GUIDE.md` §11). The intelligence ↔
recruitment touchpoint (`interview_sessions.ai_session_id`) stays within the
allowed shared surface and is nullable.

**No duplicated data.** Relationships are expressed as references, never copies:
`subscriptions` references `plans`; `applications` references `jobs`/`users`;
documents reference `files`. No entity stores a copy of data owned elsewhere
(`DOMAIN_MODEL.md` §6.7).

Checklist:

- [x] Every workspace-scoped entity has N:1 → `workspaces` via NOT NULL
      `workspace_id` (§12).
- [x] Every N:M is modeled through a pivot table (§10); none modeled by array
      columns.
- [x] Every 1:1 is enforced by a UNIQUE FK (§11).
- [x] FK graph of ownership is acyclic; self-references are tree-shaped (§13).
- [x] `candidate_profiles` UNIQUE (`workspace_id`, `user_id`); `applications`
      UNIQUE (`job_id`, `user_id`).
- [x] Referential actions deferred to `DATABASE_GUIDE.md` §4; types/indexes to
      `INDEXING_GUIDE.md`.

---

### Related Documents
`ENTITY_CATALOG.md` · `DOMAIN_MODEL.md` · `ER_DIAGRAM.md` · `DATABASE_GUIDE.md` ·
`INDEXING_GUIDE.md` · `WORKSPACE_MODEL.md` · `PERMISSION_MODEL.md` ·
`DATABASE_ARCHITECTURE.md`
