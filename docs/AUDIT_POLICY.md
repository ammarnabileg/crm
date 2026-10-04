# AUDIT POLICY — HaHireAI

> **Status:** Adopted (Phase 3) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `ENTITY_CATALOG.md`. **Event list:** `AUDIT_EVENTS.md` (Phase 4).

---

## 0. Purpose & Scope

This document is the **authoritative policy** for how HaHireAI records,
protects, isolates, retains, and exposes audit data. It governs *what auditing
guarantees* — not the per-action key list, which is enumerated in
`AUDIT_EVENTS.md` (Phase 4). Where this policy names a table, the table's
existence and scope are owned by `ENTITY_CATALOG.md`; this policy MUST NOT be
read as an entity list.

**Interpretation keywords** (**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**,
**MAY**) follow RFC 2119. A **MUST/MUST NOT** rule is binding; a violation is a
defect. Auditing is a first-class platform guarantee (Constitution §10;
`DATABASE_GUIDE.md` §1, principle 4 "Auditable"), not a debugging afterthought.

Two audit stores exist and only two: the **workspace** trail (`audit_logs`) and
the **platform** trail (`system_audit_logs`). Both are listed as
**(immutable)** in `ENTITY_CATALOG.md` (§4, §7).

---

## 1. What Auditing Guarantees

An audit record answers, for every significant action, the five canonical
questions. Each maps to a concrete field.

| Question | Field | Notes |
|---|---|---|
| **Who** | `actor_user_id` | The acting `User` (`DOMAIN_MODEL.md` §2). System Owner actions record the same human identity acting in Platform Context. System/automated actors are recorded distinctly (§4.4). |
| **When** | `created_at` | UTC `DATETIME`, set on insert (`DATABASE_GUIDE.md` §2, §5). Never back-dated, never updated. |
| **Where (tenant)** | `workspace_id` | Present and `NOT NULL` on `audit_logs`; **absent** on `system_audit_logs` (global scope). |
| **Where (origin)** | `ip` (+ user agent / request context) | The network origin of the request. SHOULD include user agent and a correlation/request id where available. |
| **What** | `action` + `entity_type` + `entity_id` + `changes` (JSON) | The action key, the affected entity and its ULID, and a structured before/after diff. |

- The `action` value is a **stable key** from `AUDIT_EVENTS.md` (e.g.
  `role.updated`, `member.invited`), never a free-text sentence.
- `changes` is a JSON column holding a **before/after** view of the mutated
  fields (an app-owned blob per `DATABASE_GUIDE.md` §8). It **MUST NOT** contain
  secrets, raw credentials, decrypted keys, or full PII payloads (see §6);
  redaction happens before write.
- `entity_type` + `entity_id` reference the affected record by its `CHAR(26)`
  ULID without a cross-module foreign key (`DATABASE_GUIDE.md` §11). Audit is a
  shared-service trail; it records references, it does not own the entities.

---

## 2. The Two Audit Stores

### 2.1 `audit_logs` — Workspace trail (immutable)

- **Scope:** Workspace (`ENTITY_CATALOG.md` §4). Every row carries a
  `NOT NULL` `workspace_id` and is invisible to every other tenant (§5).
- **Captures:** actions taken *inside a workspace* — recruitment activity,
  membership and role/permission changes, file operations, billing changes,
  AI usage governance events, integration changes, and workspace settings.
- **Reader permission:** `audit.view` (a workspace permission, granted via
  roles — `PERMISSION_MODEL.md` §2–§5).

### 2.2 `system_audit_logs` — Platform trail (immutable)

- **Scope:** Global (`ENTITY_CATALOG.md` §7). **No** `workspace_id`.
- **Captures:** platform-level actions by **System Owners** in Platform Context
  — e.g. workspace lifecycle administration, plan/subscription administration,
  diagnostics, global AI provider/model management, feature-flag definition
  changes, impersonation, and any tenant-scope **bypass** (`DATABASE_GUIDE.md`
  §6.2 — a bypass MUST be explicit, permission-gated, and audited here).
- **Reader permission:** `system.audit.view` (a system permission —
  `PERMISSION_MODEL.md` §6).

> A System Owner action that *targets a specific workspace* (e.g. an
> administrative change applied to one tenant) SHOULD be recorded in
> `system_audit_logs` and MAY additionally surface a workspace-trail entry so
> the tenant retains visibility, per `AUDIT_EVENTS.md`.

---

## 3. Immutability — Append-Only (binding)

Audit data is **write-once**. This is the core integrity property of the policy.

- `audit_logs` and `system_audit_logs` are **(immutable)** in
  `ENTITY_CATALOG.md`: they have neither `updated_at` nor `deleted_at` and are
  append-only.
- The application and repository layer **MUST NOT** issue `UPDATE` or `DELETE`
  against either table in any normal flow. There is no "edit audit entry" and no
  "soft delete audit entry" operation. Correcting a mistaken record is done by
  **appending a new** compensating record, never by mutating history.
- Audit rows are **never soft-deleted and never archived** (`ARCHIVING_POLICY.md`
  classifies both audit tables as immutable / never-deleted). Removal happens
  **only** through the retention process in §4, executed as a controlled,
  bounded purge of records past their window — never an ad-hoc delete.
- A failure to write audit MUST be surfaced (logged/alerted), never swallowed.
  Audit writes capture the **committed** outcome and MUST reflect what actually
  happened — no speculative "about to do X" entries that never occurred.

---

## 4. Retention

Audit retention balances accountability, tenant value, and storage discipline
(`DATABASE_GUIDE.md` §1, principle 6 "Scalable" — no unbounded growth without a
retention plan). Concrete windows are governed jointly with
`ARCHIVING_POLICY.md`.

| Store | Default minimum retention | Disposition after window |
|---|---|---|
| `audit_logs` (workspace) | **24 months** (SHOULD; plan tier MAY extend) | Eligible for bounded purge or cold export, per `ARCHIVING_POLICY.md`. |
| `system_audit_logs` (platform) | **≥ 24 months** (SHOULD; security/compliance MAY require longer) | Purge only by the controlled retention job; security events MAY be retained longer. |

- Retention windows are **configuration**, expressed in policy/plan terms — not
  hard-coded constants scattered in code (consistent with
  `PERMISSION_MODEL.md`/`WORKSPACE_MODEL.md`: behavior is data, not code).
- Purging past-window records is the **only** sanctioned deletion of audit data.
  It MUST be a bounded, scheduled, logged operation; the purge action itself
  SHOULD be recorded in the platform trail.
- **Legal hold overrides retention.** When records are under hold (litigation,
  investigation, regulatory request), the purge MUST exclude them until the hold
  is released.
- Workspace **suspension or cancellation does not** trigger audit deletion.
  Audit data follows its retention window independently of subscription state
  (see §5 and `ARCHIVING_POLICY.md` on suspension/cancellation).

---

## 5. Tenant Isolation of Audit Data (binding)

Workspace isolation is the single most important security invariant of the
system (`WORKSPACE_MODEL.md` §3; `DATABASE_GUIDE.md` §6) and it applies in full
to audit data.

- Every `audit_logs` query **MUST** be scoped by the active `workspace_id`
  through the mandatory repository tenant guard (`DATABASE_GUIDE.md` §6.2). One
  tenant **MUST NOT** read, infer, or export another tenant's audit trail.
- `workspace_id` for an audit write **MUST** come from the authenticated
  **Workspace Context** (`WORKSPACE_MODEL.md` §7), never from client input.
- `system_audit_logs` is **global** and **MUST NOT** be exposed in any Workspace
  Context. It is visible only in **Platform Context** to holders of
  `system.audit.view`.
- A cross-tenant read of audit data (e.g. a platform investigation) is a
  System-Owner, permission-gated, **bypass** operation per `DATABASE_GUIDE.md`
  §6.2 — and that access SHOULD itself be audited in the platform trail.

---

## 6. Who Can Read Audit Data

Audit is **read-restricted** and, like every action, **deny-by-default**
(`PERMISSION_MODEL.md` §5).

| Trail | Required permission | Context |
|---|---|---|
| `audit_logs` (workspace) | `audit.view` | Workspace Context |
| `system_audit_logs` (platform) | `system.audit.view` | Platform Context |

- No member sees the workspace trail without `audit.view`; no operator sees the
  platform trail without `system.audit.view`. UI visibility derives from these
  same checks and is never a substitute for server-side enforcement
  (`PERMISSION_MODEL.md` §5).
- There is **no write permission** for audit by end users. Audit records are
  produced by the system as a side effect of audited actions, not authored by
  members. No permission grants the ability to edit or delete audit history
  (§3); the only lifecycle operation is policy-driven retention (§4).
- Exported audit data (CSV/report) inherits the same permission gate and the
  same tenant scope; an export MUST NOT widen visibility.
- `changes` and origin fields can carry sensitive context; readers see only
  their own scope, and redaction (§1) keeps secrets and full PII out of the
  record in the first place. PII handling defers to `ARCHIVING_POLICY.md`
  (GDPR/erasure) and `SECURITY_GUIDE.md`.

---

## 7. Categories of Audited Actions

Audited actions are grouped into the categories below **for organization and
display**; categories never affect enforcement (mirroring
`PERMISSION_MODEL.md` §2). This policy fixes the **categories**; the
**exhaustive event-key list** is deferred to `AUDIT_EVENTS.md` (Phase 4).

| Category | Representative actions (illustrative — see `AUDIT_EVENTS.md`) | Primary store |
|---|---|---|
| **Authentication** | login success/failure, logout, password reset, session/token issuance & revocation, MFA changes | workspace + platform |
| **Membership** | invitation sent/accepted/revoked, member joined/removed, status change, ownership transfer | `audit_logs` |
| **Role & Permission** | role created/updated/cloned/deleted, permission assigned/revoked, member role changed (`PERMISSION_MODEL.md` §7) | `audit_logs` |
| **Workspace** | workspace created/archived/restored/soft-deleted, settings changed, branding changed | `audit_logs` (+ platform for admin actions) |
| **Files** | file uploaded/replaced/moved/deleted, visibility or retention change, folder changes | `audit_logs` |
| **Recruitment** | job created/published/closed, application stage moves, interview scheduled/completed, scorecard submitted, offer extended/approved/withdrawn, hire | `audit_logs` |
| **Billing** | subscription created/changed/cancelled, plan change, invoice issued, payment recorded, payment method changed, coupon applied | `audit_logs` (+ platform for `system.subscriptions.manage`) |
| **AI** | AI session run, provider/model/key configuration change, fallback governance events, prompt published | `audit_logs` (+ platform for `system.ai.manage`) |
| **Integration** | API key created/revoked, webhook created/updated/deleted, OAuth connection added/removed, connector configured | `audit_logs` |
| **System** | platform settings change, feature-flag definition change, diagnostics run, impersonation, tenant-scope bypass, retention purge | `system_audit_logs` |

> **Note on related immutable trails.** Several modules keep their own
> **append-only history** that is *not* the audit log but complements it —
> e.g. `application_stage_history`, `ai_usage`, `ai_fallback_history`,
> `payments`, `webhook_deliveries`, `workflow_execution_steps`, and
> `job_versions` (all **(immutable)** in `ENTITY_CATALOG.md`). These are
> domain/usage records, not the who-did-what trail; `AUDIT_EVENTS.md` defines
> which domain events *also* emit an `audit_logs` entry. Version history is
> governed by `VERSIONING_POLICY.md`; lifecycle/retention of these stores is
> governed by `ARCHIVING_POLICY.md`.

---

## 8. Conformance Checklist

An action or store is audit-conformant only if **all** apply:

- [ ] Every significant, state-changing action emits an audit record with a
      stable `action` key (§1, §7).
- [ ] Each record carries who (`actor_user_id`), when (`created_at`), where
      (`workspace_id` + `ip`), and what (`entity_type`/`entity_id` + `changes`)
      (§1).
- [ ] Workspace actions write `audit_logs`; platform/System-Owner actions write
      `system_audit_logs` (§2).
- [ ] No `UPDATE`/`DELETE` path exists for either audit table outside the
      retention purge; records are append-only (§3).
- [ ] `audit_logs` reads/writes pass the tenant guard and are scoped by
      `workspace_id`; `system_audit_logs` is Platform-Context only (§5).
- [ ] Reads are gated by `audit.view` / `system.audit.view`, deny-by-default
      (§6).
- [ ] `changes` is redacted of secrets and full PII before write (§1, §6).
- [ ] Retention windows are honored, legal holds respected, and purges bounded
      and logged (§4).

---

### Related Documents
`ENTITY_CATALOG.md` · `AUDIT_EVENTS.md` (Phase 4) · `ARCHIVING_POLICY.md` ·
`VERSIONING_POLICY.md` · `DATABASE_GUIDE.md` · `PERMISSION_MODEL.md` ·
`WORKSPACE_MODEL.md` · `DOMAIN_MODEL.md` · `SECURITY_GUIDE.md`
