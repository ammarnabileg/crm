# ENTITY CATALOG — HaHireAI

> **Status:** Adopted (Canon) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `DOMAIN_MODEL.md`, `DATABASE_GUIDE.md`.
> This is the **authoritative list of every entity (table)** in the system, its
> **tenancy scope**, owning module, and key relationships. `ER_DIAGRAM.md`,
> `RELATIONSHIP_MATRIX.md`, `INDEXING_GUIDE.md`, and all migrations defer to this.

---

## 1. How to read this catalog

- **Scope** is one of:
  - **Global** — platform-wide; **no** `workspace_id`.
  - **Workspace** — tenant data; **MUST** carry a NOT NULL `workspace_id` and be
    tenant-guarded (`DATABASE_GUIDE.md` §6, `WORKSPACE_MODEL.md` §3).
  - **Pivot** — a join table (scope follows its parents).
- Every table has a ULID `CHAR(26)` `id` and `created_at` / `updated_at`. Tables
  marked **(soft)** also have `deleted_at`; **(immutable)** tables have neither
  `updated_at` nor `deleted_at` (append-only). See `ARCHIVING_POLICY.md`.
- **Versioned** tables keep history (see `VERSIONING_POLICY.md`).
- Detailed columns, types, and indexes live in `DATABASE_ARCHITECTURE.md` and
  `INDEXING_GUIDE.md`; this catalog defines *what exists* and *its scope*.

## 2. Tenancy summary

**Global tables** (no `workspace_id`): `users`, `user_sessions`,
`password_resets`, `remember_tokens`, `permissions`, `plans`, `coupons`,
`feature_flags`, `ai_providers`, `ai_models`, `prompt_templates` (global
library), `system_settings`, `system_audit_logs`, `error_events` (system),
`metrics`, `health_checks`, `backups`, `background_jobs`, `workspaces` (the
tenant root itself).

**Everything else is Workspace-scoped.** When in doubt, a table is
Workspace-scoped. (`workspaces` is the one row-per-tenant table that is keyed by
`id`, not `workspace_id`.)

## 3. Identity & Access

| Entity (table) | Scope | Key relationships / notes |
|---|---|---|
| `users` (soft) | Global | The single human identity. `avatar_file_id`→files. System Owner = user holding `system.*` (no separate table). |
| `user_sessions` | Global | `user_id`→users. Auth sessions. |
| `password_resets` | Global | `user_id`→users. Short-lived tokens. |
| `remember_tokens` | Global | `user_id`→users. Rotating. |
| `workspaces` (soft) | Global (tenant root) | `owner_user_id`→users. Keyed by `id`; all workspace data references this. |
| `workspace_settings` | Workspace | `workspace_id`→workspaces. Key/value or typed columns. |
| `workspace_branding` | Workspace | `workspace_id`→workspaces. Logo/cover/colors/favicon. |
| `memberships` (soft) | Workspace | `workspace_id`, `user_id`. Unique (workspace_id,user_id). Status per `STATE_DIAGRAMS.md §7`. |
| `invitations` | Workspace | `workspace_id`, `invited_by`→users, `email`/`code`/`token`. Status §8. |
| `roles` (soft) | Workspace | `workspace_id`. A named bundle of permissions. No reserved roles. |
| `permissions` | Global | The catalog of permission keys (`key`, `category`). |
| `role_permissions` | Pivot (Workspace) | `role_id`→roles, `permission_id`→permissions. |
| `membership_roles` | Pivot (Workspace) | `membership_id`→memberships, `role_id`→roles. |
| `membership_permissions` | Pivot (Workspace) | Optional direct grants: `membership_id`, `permission_id`. |

## 4. Platform Services

| Entity (table) | Scope | Key relationships / notes |
|---|---|---|
| `folders` (soft) | Workspace | `workspace_id`, `parent_id`→folders. |
| `files` (soft) | Workspace | `workspace_id`, `owner_user_id`→users, `folder_id`→folders. Visibility + retention. |
| `notifications` | Workspace | `workspace_id`, `user_id`. `read_at`/`archived_at`, category, payload(json). |
| `search_documents` | Workspace | `workspace_id`, `entity_type`, `entity_id`. Index projection for unified search. |
| `audit_logs` (immutable) | Workspace | `workspace_id`, `actor_user_id`, action, entity_type/id, ip, changes(json). |
| `system_settings` | Global | Platform-wide settings. |
| `system_audit_logs` (immutable) | Global | Platform-level actions by System Owners. |

## 5. Recruitment (workspace-scoped)

| Entity (table) | Scope | Key relationships / notes |
|---|---|---|
| `jobs` (soft) **versioned** | Workspace | `workspace_id`, `created_by`→users, `public_token`. Status per §2 of STATE_DIAGRAMS. |
| `job_versions` (immutable) | Workspace | `job_id`→jobs. Snapshot per publish/edit. |
| `job_questions` | Workspace | `job_id`→jobs. Custom screening questions. |
| `pipelines` | Workspace | `workspace_id`, `job_id`(nullable→template). |
| `pipeline_stages` | Workspace | `pipeline_id`→pipelines, `position`. Customizable. |
| `applications` (soft) | Workspace | `workspace_id`, `job_id`, `user_id`, `current_stage_id`. Unique (job_id,user_id). Status §3. |
| `application_stage_history` (immutable) | Workspace | `application_id`, from/to stage, `moved_by`. |
| `application_documents` | Workspace | `application_id`, `file_id`→files, type. |
| `candidate_profiles` | Workspace | `workspace_id`, `user_id`. The per-(User,Workspace) **view**. Unique (workspace_id,user_id). |
| `candidate_notes` (soft) | Workspace | `candidate_profile_id`, `author_user_id`, visibility (private/shared). |
| `tags` | Workspace | `workspace_id`, name, color. |
| `candidate_profile_tags` | Pivot (Workspace) | `candidate_profile_id`, `tag_id`. |
| `interviews` | Workspace | `workspace_id`, `application_id`, type(ai/human). Status §5. |
| `interview_sessions` | Workspace | `interview_id`, `ai_session_id`(nullable→ai_sessions), recording `file_id`. |
| `scorecards` | Workspace | `interview_id`, `evaluator_user_id`, scores(json), recommendation (advisory). |
| `offers` (soft) | Workspace | `workspace_id`, `application_id`, `approved_by`. Status §4. |
| `employees` (soft) | Workspace | `workspace_id`, `user_id`, `application_id`. Post-hire context. Status §6. |
| `talent_pool_entries` | Workspace | `workspace_id`, `candidate_profile_id`, list/reason. |
| `templates` **versioned** | Workspace | `workspace_id`, type(job/pipeline/email/screening), content(json). |

## 6. Intelligence (AI)

| Entity (table) | Scope | Key relationships / notes |
|---|---|---|
| `ai_providers` | Global | Provider registry (openai/anthropic/gemini/openrouter/azure/ollama). |
| `ai_models` | Global | `provider_id`→ai_providers, capabilities. |
| `prompt_templates` (global) **versioned** | Global | Shared prompt library. |
| `workspace_ai_settings` | Workspace | `workspace_id`, `provider_id`, `model_id`, temperature, limits, fallback(json). |
| `workspace_ai_keys` | Workspace | `workspace_id`, `provider_id`, `encrypted_key`, `use_platform_key`. Encrypted; never shown after save. |
| `workspace_prompts` **versioned** | Workspace | `workspace_id`, key, version, template, locale. |
| `ai_sessions` | Workspace | `workspace_id`, capability, `provider_id`, `model_id`, status. |
| `ai_messages` | Workspace | `ai_session_id`, role, content, tokens. |
| `ai_usage` (immutable) | Workspace | `workspace_id`, `ai_session_id`, tokens, latency, cost, status. |
| `ai_fallback_history` (immutable) | Workspace | `ai_session_id`, from/to provider, reason. |
| `reports` / `saved_views` | Workspace | `workspace_id`, definition(json). Analytics. |

## 7. Process (Workflow)

| Entity (table) | Scope | Key relationships / notes |
|---|---|---|
| `workflows` **versioned** | Workspace | `workspace_id`, category, status(draft/published), definition(json). |
| `workflow_executions` | Workspace | `workflow_id`, `workspace_id`, status, current_step, retry_count. §11. |
| `workflow_execution_steps` (immutable) | Workspace | `execution_id`, step, status, input/output/error. |
| `workflow_approvals` | Workspace | `execution_id`, `approver_user_id`, status. |
| `scheduled_tasks` | Workspace | `workspace_id`, `workflow_id`, cron, next_run_at. |
| `background_jobs` | Global | Queue: queue, payload, status, attempts, available_at. (Infra; runtime.) |

## 8. Integration

| Entity (table) | Scope | Key relationships / notes |
|---|---|---|
| `api_keys` | Workspace | `workspace_id`, `hashed_key`, scopes, status, expires_at. |
| `access_tokens` | Workspace/Global | PAT/workspace/system tokens: `user_id`?/`workspace_id`?, type, `hashed_token`, scopes. |
| `webhooks` | Workspace | `workspace_id`, url, events, `secret`(encrypted), status. |
| `webhook_deliveries` (immutable) | Workspace | `webhook_id`, event, status, attempts, response_code. |
| `integrations` | Workspace | `workspace_id`, connector_key, config(json, secrets encrypted), health. |
| `oauth_connections` | Workspace | `workspace_id`, provider, tokens(encrypted), scopes. |

## 9. Commerce

| Entity (table) | Scope | Key relationships / notes |
|---|---|---|
| `plans` | Global | key, name, price, currency, interval, features(json), limits(json). No hard-coded plan in code. |
| `subscriptions` | Workspace | `workspace_id`, `plan_id`→plans. Status §9. One active per workspace. |
| `invoices` | Workspace | `workspace_id`, number, amount, items(json), status, `pdf_file_id`. |
| `payments` (immutable) | Workspace | `workspace_id`, `invoice_id`, provider, provider_ref, status. |
| `payment_methods` | Workspace | `workspace_id`, provider, token(encrypted), brand, last4. |
| `coupons` | Global | code, type, value, expiry. |
| `feature_flags` | Global | key, description (definitions). |
| `workspace_feature_flags` | Workspace | `workspace_id`, flag_key, enabled (overrides). |
| `usage_counters` | Workspace | `workspace_id`, metric, period, value. |

## 10. Operations (Observability)

| Entity (table) | Scope | Key relationships / notes |
|---|---|---|
| `error_events` | Global + Workspace | `workspace_id`(nullable), message, stack, severity, status. |
| `metrics` (immutable) | Global | name, value, labels(json), recorded_at. |
| `health_checks` (immutable) | Global | component, status, detail, checked_at. |
| `backups` | Global | type, status, location, size. |
| `alerts` | Global + Workspace | `workspace_id`(nullable), type, channel, status. |

## 11. Catalog Self-Review (Phase 3 gate)

- [ ] Every workspace-scoped table carries a NOT NULL `workspace_id`.
- [ ] No entity duplicates data derivable from another (references, not copies).
- [ ] `candidate_profiles` is per-(User,Workspace); no global candidate table.
- [ ] No table depends on a fixed role name.
- [ ] A new module adds tables without altering existing ones.
- [ ] Immutable/append-only tables identified; soft-delete vs archive vs
      hard-delete classified (`ARCHIVING_POLICY.md`).

---

### Related Documents
`DOMAIN_MODEL.md` · `DATABASE_GUIDE.md` · `DATABASE_ARCHITECTURE.md` ·
`ER_DIAGRAM.md` · `RELATIONSHIP_MATRIX.md` · `INDEXING_GUIDE.md` ·
`AUDIT_POLICY.md` · `ARCHIVING_POLICY.md` · `VERSIONING_POLICY.md`
