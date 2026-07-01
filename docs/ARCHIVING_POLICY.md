# ARCHIVING POLICY — HaHireAI

> **Status:** Adopted (Phase 3) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `ENTITY_CATALOG.md`, `DATABASE_GUIDE.md` (§9).

---

## 0. Purpose & Scope

This document is the **authoritative lifecycle policy** for HaHireAI data: when a
record is **archived**, **soft-deleted**, **kept immutable forever**, or — rarely
— **hard-deleted**. It states *which mechanism applies to which entity*, the
retention rules, and how compliance erasure is handled. The modeling convention
behind these mechanisms is `DATABASE_GUIDE.md` §9; the canonical entity list and
each entity's `(soft)` / `(immutable)` / versioned markers are owned by
`ENTITY_CATALOG.md`. This policy MUST NOT be read as the entity list.

**Interpretation keywords** (**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**,
**MAY**) follow RFC 2119. A **MUST/MUST NOT** rule is binding; a violation is a
defect.

**Governing principle.** Business data is, by default, **never physically
destroyed** (`WORKSPACE_MODEL.md` §6; `DATABASE_GUIDE.md` §9). Destruction is the
rare, deliberate exception, not the routine.

---

## 1. The Three (and a Half) Mechanisms

HaHireAI distinguishes three distinct lifecycle operations. They are **different
operations** and **MUST NOT** be conflated in code or schema
(`DATABASE_GUIDE.md` §9) — e.g. archiving MUST NOT set `deleted_at`.

| Mechanism | Meaning | Schema signal | Visible by default? | Recoverable? |
|---|---|---|---|---|
| **Archive** | Record intentionally set aside but still valid and queryable on demand. | A **status/state** change (`status_code`, or an `archived_at` timestamp where modeled). **Not** `deleted_at`. | No (hidden from primary lists) — still retrievable | **Fully** (status change back) |
| **Soft delete** | Record removed from normal use, retained and recoverable. | `deleted_at DATETIME NULL` (NULL = live). | No (excluded by default read path) | **Yes** (clear `deleted_at`) |
| **Hard delete** | Permanent physical row removal. | Actual SQL `DELETE`. | n/a | **No** |

The "half": **Immutable / never-deleted** records (audit, usage, financial, and
other append-only trails) are not subject to archive or soft delete at all — see
§3 and §4.

### 1.1 Archive — recoverable, queryable-on-demand

- Archive is a **lifecycle status**, expressed through the entity's state model
  (`STATE_DIAGRAMS.md`), e.g. a closed `jobs` row or an archived `workspaces`
  row.
- Archived rows are **excluded from primary lists** but remain fully valid and
  **queryable on demand** (reports, search with an explicit "include archived"
  filter, direct retrieval by id).
- Archiving is reversible: restoring is a status change back to active
  (`WORKSPACE_MODEL.md` §6: `Archived → Restored → Active`). It **MUST NOT** be
  implemented by writing `deleted_at`.

### 1.2 Soft delete — hidden by default, recoverable

- Soft delete sets `deleted_at`. The **default read path and the tenant guard**
  (`DATABASE_GUIDE.md` §6, §9) **MUST** exclude `deleted_at IS NOT NULL` unless a
  query explicitly opts in.
- Only entities marked **(soft)** in `ENTITY_CATALOG.md` carry `deleted_at`.
- Restore clears `deleted_at`. Soft delete is **not** erasure — the row and its
  relationships are intact and recoverable within its retention window (§4).

### 1.3 Hard delete — permanent, rare

- Hard delete is **physical** and **irreversible**. It is reserved for:
  - **Legally mandated erasure** (data-subject / GDPR "right to erasure", §5),
  - **Transient/ephemeral rows** (expired tokens, caches, completed queue rows),
  - **True junk** created in error with no business meaning.
- Hard delete of business/tenant data **MUST NOT** occur in normal flows
  (`DATABASE_GUIDE.md` §9). When performed, it MUST be permission-gated, logged
  in the platform trail (`AUDIT_POLICY.md`), and — for tenant data — bounded and
  reviewed.

---

## 2. Choosing the Mechanism (decision rules)

1. **Is the record an immutable/append-only trail** (audit, usage, financial,
   delivery, step, version, history)? → **Never archive, never soft-delete,
   never hard-delete** outside retention/legal erasure. See §3.
2. **Does the entity have a lifecycle "closed/inactive but still meaningful"
   state** (job, workspace, application, offer, employee)? → **Archive** via
   status; keep it queryable on demand.
3. **Is the record being removed from use but might need to come back** (a role
   no longer used, a file, a candidate note)? → **Soft delete** (`deleted_at`),
   if the entity is marked **(soft)**.
4. **Is removal legally required, or is the row genuinely ephemeral/junk?** →
   **Hard delete**, per §5 and the rules in §1.3.

When in doubt, prefer the **least destructive** mechanism that satisfies the
requirement (archive over soft delete, soft delete over hard delete).

---

## 3. Immutable & Never-Deleted Records (binding)

The following tables are **(immutable)** in `ENTITY_CATALOG.md` — append-only,
with neither `updated_at` nor `deleted_at`. They are **never archived and never
soft-deleted**, and they are **hard-deleted only** by the controlled retention
process (§4) or a lawful erasure (§5):

`audit_logs`, `system_audit_logs`, `job_versions`,
`application_stage_history`, `ai_usage`, `ai_fallback_history`,
`workflow_execution_steps`, `payments`, `webhook_deliveries`, `metrics`,
`health_checks`.

- **Audit** (`audit_logs`, `system_audit_logs`) — accountability trail; governed
  by `AUDIT_POLICY.md`. Removal **only** via audit retention.
- **AI usage & fallback** (`ai_usage`, `ai_fallback_history`) — billing/governance
  facts; retained for cost reconciliation and disputes.
- **Financial** (`payments`) — money movement is permanent; MUST NOT be edited or
  deleted. Corrections are new compensating rows.
- **Delivery / execution history** (`webhook_deliveries`,
  `workflow_execution_steps`, `application_stage_history`) — point-in-time facts;
  history, not mutable state.
- **Operational telemetry** (`metrics`, `health_checks`) — retained per the
  observability window (§4), then purged in bounded batches.

> **Versioned entities keep history immutably too.** `job_versions` is the
> immutable snapshot store for `jobs`. Version stores and their retention are
> governed by `VERSIONING_POLICY.md`; this policy classifies them as
> never-soft-deleted history.

---

## 4. Per-Entity Classification & Retention

The table below classifies entities by lifecycle mechanism. Markers
(`(soft)` / `(immutable)` / versioned) are authoritative in `ENTITY_CATALOG.md`;
this table assigns the **policy**. "Default read hides it?" indicates whether the
record is excluded from ordinary lists.

| Entity (table) | Archive | Soft delete (`deleted_at`) | Immutable / never delete | Hard delete on request | Default read hides it? | Retention (SHOULD) |
|---|---|---|---|---|---|---|
| `users` (soft) | — | Yes | — | **Yes** (GDPR erasure, §5) | If deleted | Account lifetime; PII erasable on lawful request |
| `workspaces` (soft) | **Yes** (archive/restore) | Yes | — | Only by lawful tenant-closure request | If archived/deleted | Lifetime of tenant; survives suspension/cancellation (§6) |
| `workspace_settings`, `workspace_branding` | Follows workspace | — | — | With workspace erasure | With workspace | With workspace |
| `memberships` (soft) | — | Yes | — | With user/workspace erasure | If deleted | Lifetime of membership; history retained |
| `invitations` | — | — (status lifecycle) | — | Expired MAY be purged | Expired hidden | Short-lived; purge expired after window |
| `roles` (soft), `role_permissions`, `membership_roles`, `membership_permissions` | — | `roles` soft; pivots follow parent | — | With workspace erasure | If deleted | Lifetime of workspace |
| `folders` (soft), `files` (soft) | — | Yes | — | **Yes** (file owner / legal request) | If deleted | Per workspace storage/retention policy |
| `notifications` | — (`archived_at`) | — | — | MAY purge old | Archived/read hidden | Short/medium; purge old per policy |
| `search_documents` | — | — | — | Rebuildable projection | — | Derived; rebuilt from sources |
| `audit_logs`, `system_audit_logs` (immutable) | **Never** | **Never** | **Yes** | Only via retention/legal | n/a | Per `AUDIT_POLICY.md` (≥ 24 months) |
| `jobs` (soft, versioned) | **Yes** (close/archive) | Yes | history in `job_versions` | With workspace erasure | If archived/deleted | Lifetime of workspace |
| `job_versions` (immutable) | Never | Never | **Yes** | With job/workspace erasure | n/a | Per `VERSIONING_POLICY.md` |
| `job_questions`, `pipelines`, `pipeline_stages` | Follow job/workspace | — | — | With workspace erasure | — | Lifetime of workspace |
| `applications` (soft) | **Yes** (closed/withdrawn states) | Yes | timeline in history table | With user/workspace erasure | If archived/deleted | Lifetime of workspace; PII erasable (§5) |
| `application_stage_history` (immutable) | Never | Never | **Yes** | With application erasure | n/a | With application |
| `application_documents` | — | Follows `files` | — | With file/erasure | — | With application/files |
| `candidate_profiles` | — (per-workspace projection) | — | — | **Yes** (GDPR erasure of candidate PII, §5) | — | Lifetime of workspace; erasable |
| `candidate_notes` (soft) | — | Yes | — | With profile erasure | If deleted | Lifetime of workspace |
| `tags`, `candidate_profile_tags`, `talent_pool_entries` | — | — | — | With workspace erasure | — | Lifetime of workspace |
| `interviews`, `interview_sessions`, `scorecards` | Follow application | — | — | With application/workspace erasure | — | Lifetime of workspace |
| `offers` (soft) | **Yes** (status lifecycle) | Yes | snapshot facts retained | With workspace erasure | If archived/deleted | Lifetime of workspace |
| `employees` (soft) | **Yes** (post-hire status) | Yes | — | With workspace erasure | If archived/deleted | Lifetime of workspace |
| `templates` (versioned), `workspace_prompts` (versioned), `workflows` (versioned) | **Yes** (status) | — | history in version store | With workspace erasure | If archived | Per `VERSIONING_POLICY.md` |
| `prompt_templates` (global, versioned) | **Yes** (status) | — | history retained | Platform decision | If archived | Platform retention |
| `ai_sessions`, `ai_messages` | — | — | — | With workspace erasure / PII request | — | Lifetime of workspace; PII redactable |
| `ai_usage` (immutable), `ai_fallback_history` (immutable) | **Never** | **Never** | **Yes** | Only via retention/legal | n/a | Billing/governance window (≥ 24 months) |
| `workflow_executions`, `workflow_approvals` | — | — | steps immutable | With workspace erasure | — | Lifetime of workspace |
| `workflow_execution_steps` (immutable) | Never | Never | **Yes** | With execution erasure | n/a | With execution |
| `scheduled_tasks` | — (enable/disable) | — | — | With workspace erasure | Disabled hidden | Lifetime of workspace |
| `api_keys`, `access_tokens`, `webhooks`, `integrations`, `oauth_connections` | — (status/revoke) | — | — | With workspace erasure | Revoked hidden | Lifetime of workspace; revoked retained for trail |
| `webhook_deliveries` (immutable) | **Never** | **Never** | **Yes** | Only via retention | n/a | Delivery-log window; purge old |
| `subscriptions` | — (status lifecycle) | — | — | With workspace erasure | Cancelled retained | Lifetime of tenant (§6) |
| `invoices` | — | — | — | Only via lawful erasure | — | Financial retention (often 7+ years) |
| `payments` (immutable) | **Never** | **Never** | **Yes** | Only via lawful erasure | n/a | Financial retention (often 7+ years) |
| `payment_methods` | — (detach) | — | — | **Yes** (on request) | Detached hidden | Until detached/erased |
| `coupons`, `plans`, `feature_flags`, `workspace_feature_flags`, `usage_counters` | — (status) | — | — | Platform/workspace decision | Inactive hidden | Platform / per-workspace |
| `error_events`, `alerts` | — (status) | — | — | MAY purge old | Resolved hidden | Observability window; purge old |
| `metrics` (immutable), `health_checks` (immutable) | **Never** | **Never** | **Yes** | Only via retention | n/a | Observability window; purge old in batches |
| `backups`, `background_jobs` | — (status) | — | — | Expired/completed purged | Completed hidden | Operational; purge per policy |

**Retention rules (cross-cutting):**

- Retention windows are **policy/plan configuration**, not hard-coded constants
  (consistent with `WORKSPACE_MODEL.md` §4 and `PERMISSION_MODEL.md`). A plan
  tier MAY extend a window; it MUST NOT shorten a legally mandated minimum.
- **Financial** records (`payments`, `invoices`) follow the longest applicable
  legal/tax retention and are exempt from routine purge.
- **Purge of past-window immutable rows** is the *only* sanctioned deletion of
  those rows; it MUST be bounded, scheduled, and logged.
- **Legal hold overrides retention and erasure**: held records MUST NOT be
  purged or erased until the hold is released.

---

## 5. PII & GDPR / Right-to-Erasure Handling

HaHireAI is multi-tenant and stores candidate PII; lawful erasure is a
first-class operation, distinct from routine deletion.

- **Identity PII lives in `users`** (the single human identity —
  `DOMAIN_MODEL.md` §3). `candidate_profiles` is a **per-(User, Workspace)
  projection**, not a second copy of the person (`DOMAIN_MODEL.md` Invariant 3;
  `DATABASE_GUIDE.md` §10 — reference, don't duplicate). Erasing PII therefore
  targets the owning records, not scattered copies.
- A validated **data-subject erasure request** triggers **hard delete** (or
  irreversible anonymization) of the subject's PII in `users`,
  `candidate_profiles`, `candidate_notes`, candidate-linked `files` /
  `application_documents`, and free-text PII inside `ai_messages` /
  `applications`, per scope of the request and applicable law.
- **Immutable trails are not free-text-erased.** Where a legal record must
  persist (e.g. `audit_logs`, `payments`, `ai_usage`), the **PII is minimized at
  write time** (`AUDIT_POLICY.md` §1, §6) and the retained row references the
  subject by `CHAR(26)` id; on erasure the *linkable identity* is removed/
  anonymized while the **non-identifying fact** (that an action/charge occurred)
  may be lawfully retained. Financial and audit obligations can **override**
  erasure for the minimum legally required period (then purged).
- Erasure is **permission-gated and audited** (recorded in the platform trail,
  `AUDIT_POLICY.md`). The erasure action itself is logged; the *erased content*
  is not reproduced in the log.
- **Tenant isolation still applies**: an erasure executed for one workspace
  MUST NOT reach into another workspace's projection of the same user without a
  separate lawful basis. The user's global `users` record is handled at platform
  scope.

---

## 6. Suspension, Cancellation & Tenant Data (binding)

Subscription state controls **access**, not **existence**
(`WORKSPACE_MODEL.md` §6; `SUBSCRIPTION_ENGINE.md`).

- A **suspended** or **cancelled** `subscriptions` state **MUST NOT** hard-delete
  workspace data. Jobs, applications, candidate profiles, files, audit, and
  configuration are **retained** and remain recoverable on reactivation.
- During suspension, data SHOULD be made **inaccessible/read-restricted** (gated
  by subscription, per `PERMISSION_MODEL.md` §6) rather than deleted.
- After cancellation, tenant data is retained for a **grace/retention window**
  (policy configuration). Only after that window, and only via an explicit,
  permission-gated, logged process, MAY a tenant's data be purged — and even
  then `payments`/`invoices` follow their longer financial retention, and
  `audit`/`system_audit_logs` follow `AUDIT_POLICY.md`.
- The `workspaces` row itself is **(soft)** and supports
  `Archived → Restored → Active` (`WORKSPACE_MODEL.md` §6); cancellation maps to
  archive/soft-delete with retention, **never** to immediate hard delete.

---

## 7. Conformance Checklist

A new or changed entity is lifecycle-conformant only if **all** apply:

- [ ] Its mechanism (archive / soft delete / immutable / hard-deletable) matches
      its marker in `ENTITY_CATALOG.md` and the classification in §4.
- [ ] Archiving uses a status/state change and **never** sets `deleted_at`
      (§1, `DATABASE_GUIDE.md` §9).
- [ ] Soft delete sets `deleted_at`; the default read path and tenant guard
      exclude soft-deleted rows (§1.2, `DATABASE_GUIDE.md` §6, §9).
- [ ] Immutable trails are never archived or soft-deleted; removal only via
      bounded retention purge or lawful erasure (§3, §4).
- [ ] Hard delete of business data occurs only for legal erasure or genuine
      ephemera/junk, permission-gated and logged (§1.3, §5).
- [ ] Retention windows are honored as policy/plan configuration; financial and
      audit minimums respected; legal holds override (§4).
- [ ] PII erasure targets owning records, minimizes PII in immutable trails, and
      is audited (§5).
- [ ] Suspension/cancellation never hard-deletes tenant data; retention windows
      apply before any purge (§6).

---

### Related Documents
`ENTITY_CATALOG.md` · `DATABASE_GUIDE.md` (§9) · `AUDIT_POLICY.md` ·
`VERSIONING_POLICY.md` · `WORKSPACE_MODEL.md` · `DOMAIN_MODEL.md` ·
`PERMISSION_MODEL.md` · `SUBSCRIPTION_ENGINE.md` · `STATE_DIAGRAMS.md` ·
`SECURITY_GUIDE.md`
