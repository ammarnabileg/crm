# AUDIT EVENTS — HaHireAI

> **Status:** Adopted (Phase 4) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `AUDIT_POLICY.md` (policy), `ENTITY_CATALOG.md`.

---

## 0. Purpose & Relationship to the Policy

This document is the **authoritative catalog of audited event keys** for HaHireAI.
Where `AUDIT_POLICY.md` (Phase 3) defines *what auditing guarantees* — immutability,
tenant isolation, retention, read-gating, and the five canonical questions — this
document enumerates the **exact event keys** those guarantees apply to. The policy
owns the rules; this catalog owns the list. The two are read together: if a rule
here ever appears to conflict with `AUDIT_POLICY.md`, the policy wins.

Table existence and tenancy scope are owned by `ENTITY_CATALOG.md`. This file MUST
NOT be read as an entity list, a permission list, or a state machine. Permission
keys referenced here are defined in `PERMISSION_CATALOG.md`; states and transitions
are defined in `STATE_DIAGRAMS.md`; module names match `MODULES.md` exactly.

**Interpretation keywords** (**MUST**, **MUST NOT**, **SHOULD**, **MAY**) follow
RFC 2119, consistent with `AUDIT_POLICY.md` §0.

---

## 1. What Every Audited Event Records

Every audited event is a single append-only row that answers the five canonical
questions of `AUDIT_POLICY.md` §1. Each maps to a concrete column on the audit row:

| Question | Field | Notes |
|---|---|---|
| **Who** | `actor_user_id` | The acting `User` (`ENTITY_CATALOG.md` §3). System Owner actions record the same human identity acting in Platform Context. Automated/system actors are recorded distinctly. |
| **When** | `created_at` | UTC, set on insert. Never back-dated, never updated (`AUDIT_POLICY.md` §3). |
| **Where (tenant)** | `workspace_id` | NOT NULL on `audit_logs`; **absent** on `system_audit_logs` (global scope). Sourced from the authenticated Workspace Context, never from client input. |
| **Where (origin)** | `ip` (+ user agent / request context) | Network origin; SHOULD include user agent and a correlation/request id where available. |
| **What** | `action` + `entity_type` + `entity_id` + `changes` (JSON) | The stable event key from this catalog, the affected entity and its ULID, and a redacted before/after diff. |

- The `action` value is always a **stable key from this catalog** (e.g.
  `recruitment.job.published`), never a free-text sentence.
- Events are stored in one of exactly two immutable stores
  (`AUDIT_POLICY.md` §2):
  - **`audit_logs`** — the **workspace** trail. Rows carry a NOT NULL
    `workspace_id`; read-gated by **`audit.view`**, exported under
    **`audit.export`**.
  - **`system_audit_logs`** — the **platform** trail. No `workspace_id`;
    Platform-Context only; read-gated by **`system.audit.view`**.
- Records are **immutable / append-only**: there is no edit and no soft-delete of
  an audit row. Corrections are made by appending a compensating row
  (`AUDIT_POLICY.md` §3). The only sanctioned removal is the bounded retention
  purge (`AUDIT_POLICY.md` §4).
- Audit writes capture the **committed** outcome — they reflect what actually
  happened, never a speculative "about to" entry.

### 1.1 Event-key grammar

Event keys use the grammar **`<module>.<entity>.<event>`**, where `<event>` is a
**past-tense** verb describing the committed outcome:

- `<module>` is a lower-cased module name from `MODULES.md` (e.g. `auth`,
  `membership`, `recruitment`, `billing`, `integration`, `system`).
- `<entity>` is the affected domain noun (often, but not always, a table from
  `ENTITY_CATALOG.md`).
- `<event>` is past tense: `created`, `updated`, `published`, `removed`,
  `failed`, `accepted`.

State-changing events align with the transitions in `STATE_DIAGRAMS.md`: the
transition *verb* becomes the past-tense event (e.g. Job `publish` →
`recruitment.job.published`; Membership `suspend` → `membership.member.suspended`).

### 1.2 Dual-write convention (workspace + platform)

Per `AUDIT_POLICY.md` §2, a System-Owner action that **targets a specific
workspace** SHOULD be recorded in `system_audit_logs` and MAY **additionally**
surface a workspace-trail entry so the tenant retains visibility. In the catalog
below, the **Scope** column marks such events **system (+ workspace)**.

---

## 2. The Authoritative Audited-Events Catalog

Events are grouped by **category** for organization and display only; categories
never affect enforcement (`AUDIT_POLICY.md` §7). The **Scope** column names the
primary store: `workspace` → `audit_logs`; `system` → `system_audit_logs`.
"Captured data" lists the redacted fields placed in `changes` — never secrets,
keys, passwords, or full PII payloads (§3).

### 2.1 Authentication

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `auth.session.logged_in` | A `User` authenticates successfully | workspace | `actor_user_id`, `ip`, user agent, session id (reference), auth method |
| `auth.session.logged_out` | A `User` ends a session | workspace | session id (reference), `ip`, reason (manual/expired) |
| `auth.session.login_failed` | A login attempt is rejected | system (+ workspace) | attempted identifier (email reference, not credential), `ip`, failure reason, attempt count |
| `auth.password.reset` | A password reset completes | workspace | `actor_user_id`, request id, channel (never the password) |
| `auth.mfa.changed` | MFA is enabled/disabled/reconfigured | workspace | factor type, enabled/disabled, changed-field names (never secrets/recovery codes) |

> Authentication is the one category that may write **both** trails: failed and
> security-relevant sign-in events SHOULD also surface in `system_audit_logs`
> (`AUDIT_POLICY.md` §7).

### 2.2 Membership & Invitation

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `membership.invitation.invited` | An invitation is sent (`STATE_DIAGRAMS.md` §8 → Pending) | workspace | `invited_by`, invitee email (reference), role(s) offered, expiry |
| `membership.invitation.resent` | An invitation is re-sent (stays Pending, new expiry) | workspace | invitation id, new expiry, resend count |
| `membership.invitation.cancelled` | An invitation is cancelled (→ Cancelled) | workspace | invitation id, prior status |
| `membership.invitation.accepted` | An invitation is accepted (→ Accepted; creates/activates Membership) | workspace | invitation id, resulting `membership_id`, accepting `user_id` |
| `membership.member.removed` | A member is removed (→ Removed) | workspace | `membership_id`, target `user_id`, role(s) at removal |
| `membership.member.suspended` | A member is suspended (Active → Suspended) | workspace | `membership_id`, prior/new status, reason |
| `membership.member.reactivated` | A member is reactivated (Suspended → Active) | workspace | `membership_id`, prior/new status |

### 2.3 Roles & Permissions

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `permission.role.created` | A role is created | workspace | `role_id`, name, initial permission keys |
| `permission.role.updated` | A role is renamed/edited | workspace | `role_id`, changed-field names, before/after name |
| `permission.role.cloned` | A role is cloned | workspace | source `role_id`, new `role_id`, name |
| `permission.role.deleted` | A role is deleted (soft) | workspace | `role_id`, name, affected membership count |
| `permission.role.permissions_assigned` | Permission keys assigned to a role/member | workspace | target `role_id`/`membership_id`, permission keys added |
| `permission.role.permissions_revoked` | Permission keys revoked from a role/member | workspace | target `role_id`/`membership_id`, permission keys removed |
| `workspace.workspace.ownership_transferred` | Ownership transferred to another member | workspace (+ system) | prior `owner_user_id`, new `owner_user_id`, reassigned owner `membership_id` |

> Ownership transfer reassigns the owner Membership without changing workspace
> state (`STATE_DIAGRAMS.md` §10); it is gated by `workspace.transfer`.

### 2.4 Workspace

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `workspace.workspace.created` | A workspace is created (creator becomes owner) | workspace (+ system) | `workspace_id`, `owner_user_id`, name, plan/trial context |
| `workspace.workspace.updated` | Core workspace fields edited | workspace | changed-field names, before/after values |
| `workspace.settings.updated` | Workspace settings changed | workspace | setting keys changed, before/after (non-secret) |
| `workspace.branding.updated` | Logo/colors/branding changed | workspace | changed asset references (`file_id`), color fields |
| `workspace.workspace.archived` | Workspace archived (Active → Archived) | workspace (+ system) | prior state, reason |
| `workspace.workspace.restored` | Workspace restored (Archived → Active) | workspace (+ system) | prior state |
| `workspace.workspace.deleted` | Workspace soft-deleted (→ Deleted, recoverable) | workspace (+ system) | prior state, `deleted_at` set |

### 2.5 Recruitment

**Jobs** (`STATE_DIAGRAMS.md` §2)

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `recruitment.job.created` | A job is created (Draft) | workspace | `job_id`, `created_by`, title, initial fields |
| `recruitment.job.updated` | A job is edited | workspace | `job_id`, changed-field names; new `job_versions` snapshot reference |
| `recruitment.job.published` | A job is published (Draft/Paused → Published) | workspace | `job_id`, `public_token` (reference), version |
| `recruitment.job.paused` | A published job is paused (→ Paused) | workspace | `job_id`, prior state |
| `recruitment.job.closed` | A job is closed (→ Closed) | workspace | `job_id`, prior state, reason |
| `recruitment.job.archived` | A job is archived (→ Archived, terminal) | workspace | `job_id`, prior state |
| `recruitment.job.deleted` | A job is soft-deleted | workspace | `job_id`, prior state |

**Applications & Pipeline** (`STATE_DIAGRAMS.md` §3)

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `recruitment.application.submitted` | A `User` submits an application (→ Applied) | workspace | `application_id`, `job_id`, applicant `user_id` |
| `recruitment.application.stage_changed` | An application moves stage | workspace | `application_id`, from/to stage id, `moved_by`; mirrors `application_stage_history` |
| `recruitment.application.rejected` | An application is rejected (→ Rejected, terminal) | workspace | `application_id`, prior stage, reason |
| `recruitment.application.withdrawn` | A candidate withdraws (→ Withdrawn, terminal) | workspace | `application_id`, prior stage |
| `recruitment.application.hired` | An application reaches Hired (Offer accepted) | workspace | `application_id`, `offer_id`, resulting `employee_id` |

**Candidates**

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `recruitment.candidate.noted` | A note is added to a candidate profile | workspace | `candidate_profile_id`, note id, visibility (private/shared) — not note body |
| `recruitment.candidate.tagged` | A tag is applied to a candidate profile | workspace | `candidate_profile_id`, `tag_id`, tag name |
| `recruitment.candidate.exported` | Candidate data is exported | workspace | `candidate_profile_id`(s) or query reference, export format, record count |

**Interviews** (`STATE_DIAGRAMS.md` §5)

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `recruitment.interview.scheduled` | An interview is scheduled (→ Scheduled) | workspace | `interview_id`, `application_id`, type (ai/human), time |
| `recruitment.interview.ai_run` | An AI interview session is run | workspace | `interview_id`, `interview_session_id`, `ai_session_id` (reference) |
| `recruitment.interview.evaluated` | A scorecard/evaluation is submitted (→ Evaluated) | workspace | `interview_id`, `scorecard` id, `evaluator_user_id`, recommendation (advisory) |

**Offers** (`STATE_DIAGRAMS.md` §4)

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `recruitment.offer.created` | An offer is created (Draft) | workspace | `offer_id`, `application_id`, terms field names |
| `recruitment.offer.sent` | An offer is sent (Approved → Sent) | workspace | `offer_id`, recipient reference, expiry |
| `recruitment.offer.revoked` | An offer is revoked (→ Revoked, terminal) | workspace | `offer_id`, prior state, reason |
| `recruitment.offer.accepted` | An offer is accepted (→ Accepted, terminal → Hire) | workspace | `offer_id`, accepting `user_id` |
| `recruitment.offer.declined` | An offer is declined (→ Declined, terminal) | workspace | `offer_id`, reason |

**Employees** (`STATE_DIAGRAMS.md` §6)

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `recruitment.employee.created` | An employee is created on hire (→ Onboarding) | workspace | `employee_id`, `user_id`, `application_id` |

### 2.6 Files

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `files.file.uploaded` | A file is uploaded | workspace | `file_id`, `owner_user_id`, `folder_id`, name, size, content type (not contents) |
| `files.file.deleted` | A file is deleted (soft) | workspace | `file_id`, name, prior `folder_id` |

### 2.7 AI

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `ai.settings.changed` | Workspace AI settings changed (provider/model/limits/fallback) | workspace | changed-field names, before/after provider/model ids, limit values |
| `ai.keys.changed` | A workspace AI key is added/rotated/removed | workspace | `provider_id`, action (added/rotated/removed), `use_platform_key` flag — **never the key value** |
| `ai.capability.run` | An AI capability/session is invoked | workspace | `ai_session_id`, capability, `provider_id`, `model_id`, status (governance reference; usage detail lives in `ai_usage`) |

> `ai.keys.changed` records **only** that a key changed and which provider —
> the secret value is never written to `changes` (§3; `ENTITY_CATALOG.md` §6:
> `workspace_ai_keys.encrypted_key` is never shown after save).

### 2.8 Billing

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `billing.plan.changed` | A subscription plan is changed | workspace (+ system) | prior `plan_id`, new `plan_id`, effective date, proration reference |
| `billing.payment.received` | A payment succeeds | workspace | `payment_id`, `invoice_id`, amount, provider, provider_ref (token reference, not card data) |
| `billing.payment.failed` | A payment attempt fails | workspace | `invoice_id`, amount, provider, failure code/reason |
| `billing.invoice.generated` | An invoice is generated | workspace | `invoice_id`, number, amount, period |
| `billing.trial.started` | A trial begins (Trialing) | workspace | `subscription_id`, `plan_id`, trial end |
| `billing.trial.ended` | A trial ends (`STATE_DIAGRAMS.md` §9) | workspace | `subscription_id`, outcome (converted/expired) |
| `billing.subscription.suspended` | A subscription is suspended (PastDue → Suspended) | workspace (+ system) | `subscription_id`, prior state, reason |
| `billing.subscription.reactivated` | A suspended/past-due subscription returns to Active | workspace (+ system) | `subscription_id`, prior state |
| `billing.subscription.cancelled` | A subscription is cancelled (→ Cancelled) | workspace (+ system) | `subscription_id`, prior state, effective date |

### 2.9 Integration

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `integration.api_key.created` | A workspace API key is created | workspace | `api_key` id, scopes, expiry — **never the raw or hashed key** |
| `integration.api_key.rotated` | An API key is rotated | workspace | `api_key` id, scopes, new expiry — never key material |
| `integration.api_key.revoked` | An API key is revoked | workspace | `api_key` id, prior status |
| `integration.webhook.created` | A webhook is created | workspace | `webhook_id`, url, subscribed events — never the signing secret |
| `integration.webhook.updated` | A webhook is updated | workspace | `webhook_id`, changed-field names (url/events/status) |
| `integration.webhook.deleted` | A webhook is deleted | workspace | `webhook_id`, url |
| `integration.integration.connected` | A connector/OAuth integration is connected | workspace | `integration` id / `oauth_connection` id, connector_key, provider, scopes — never tokens |

### 2.10 System (Platform Context)

These events are written to **`system_audit_logs`** by System Owners in Platform
Context (`AUDIT_POLICY.md` §2.2). Events that target a single tenant are marked
**system (+ workspace)** per §1.2.

| Event key | Trigger | Scope | Captured data |
|---|---|---|---|
| `system.user.managed` | A System Owner creates/updates/disables a user globally | system | target `user_id`, action, changed-field names (never credentials) |
| `system.workspace.suspended` | A workspace is suspended platform-side | system (+ workspace) | target `workspace_id`, prior state, reason |
| `system.workspace.resumed` | A suspended workspace is resumed | system (+ workspace) | target `workspace_id`, prior state |
| `system.plan.changed` | A plan or coupon definition is changed | system | `plan_id`/`coupon` id, changed-field names, before/after |
| `system.maintenance.toggled` | Maintenance mode is enabled/disabled | system | mode on/off, scope, message reference |
| `system.backup.created` | A backup is created | system | `backup` id, type, location reference, size |
| `system.backup.restored` | A backup is restored | system | `backup` id, target, outcome |
| `system.diagnostics.run` | Diagnostics are executed | system | check set, outcome summary, duration |

> Tenant-scope **bypass** reads (e.g. a platform investigation reading one
> tenant's `audit_logs`) are System-Owner, permission-gated operations that
> SHOULD themselves be audited in `system_audit_logs` (`AUDIT_POLICY.md` §5).

---

## 3. Sensitive-Data Rules (binding)

`changes` and origin fields can carry sensitive context. Redaction happens
**before write** — the goal is that secrets and full PII never enter the record in
the first place (`AUDIT_POLICY.md` §1, §6).

- **MUST NOT** log secrets or credentials of any kind: passwords, password reset
  tokens, MFA secrets/recovery codes, session/remember tokens, AI provider keys
  (`workspace_ai_keys.encrypted_key`), API keys (raw **or** hashed), webhook
  signing secrets, OAuth/integration tokens, or payment card data.
- **MUST NOT** log full PII payloads. Resume/CV contents, note bodies, message
  contents, and candidate document contents are **not** copied into `changes`.
  Record a **reference** (e.g. `file_id`, `candidate_profile_id`, note id) plus
  the **names** of the fields that changed.
- For value diffs, log **changed-field names** with before/after values **only**
  for non-sensitive fields. For sensitive fields, log that the field changed and
  nothing more (e.g. `ai.keys.changed` records the provider and the fact of
  rotation, never the key).
- Identifiers are logged as **references**, never as the secret itself: a payment
  records `provider_ref`/token reference, not the card number; a session records
  the session id, not the bearer token.
- Exported audit data inherits the **same** redaction, the same permission gate
  (`audit.view` / `audit.export` / `system.audit.view`), and the same tenant
  scope — an export MUST NOT widen visibility (`AUDIT_POLICY.md` §6).
- If a sensitive value is ever detected in a candidate `changes` payload, the
  write MUST redact it; a redaction failure MUST be surfaced, never silently
  written (`AUDIT_POLICY.md` §3).

---

## 4. Retention & Export

Retention and export are governed by `AUDIT_POLICY.md` §4 and §6 jointly with
`ARCHIVING_POLICY.md`; this section summarizes how those rules apply to the events
above and adds nothing new.

- **Immutability.** Every event in this catalog is append-only. No `UPDATE` or
  `DELETE` path exists for `audit_logs` or `system_audit_logs` outside the
  controlled retention purge (`AUDIT_POLICY.md` §3). Corrections append a
  compensating event.
- **Retention windows.** Workspace events (`audit_logs`) are retained **≥ 24
  months** (plan tier MAY extend); platform events (`system_audit_logs`) **≥ 24
  months**, with security-relevant events (e.g. `auth.session.login_failed`,
  `system.*`) eligible for longer retention. Windows are **configuration**, not
  hard-coded constants (`AUDIT_POLICY.md` §4).
- **Purge is the only deletion.** Past-window removal is a bounded, scheduled,
  logged purge; the purge action itself SHOULD be recorded in the platform trail.
  **Legal hold overrides retention** — held records are excluded from purge until
  the hold is released.
- **Lifecycle independence.** Workspace **suspension or cancellation does not**
  delete audit events (`billing.subscription.suspended/cancelled`,
  `system.workspace.suspended`): the trail follows its retention window
  independently of subscription state (`AUDIT_POLICY.md` §4).
- **Export gating.** `audit.export` exports the workspace trail within the active
  tenant scope; `system.audit.view` governs platform-trail visibility. Deny by
  default (`AUDIT_POLICY.md` §6); there is **no** end-user write or edit
  permission for audit data.
- **Related immutable trails.** Several domain stores keep their own append-only
  history that **complements** (and is not) the audit trail — e.g.
  `application_stage_history`, `ai_usage`, `ai_fallback_history`, `payments`,
  `webhook_deliveries`, `workflow_execution_steps`, `job_versions`. Where a
  domain event above also emits one of these (noted in §2), the audit row records
  the who-did-what; the domain store records the detail. Their lifecycle is
  governed by `ARCHIVING_POLICY.md` and `VERSIONING_POLICY.md`.

---

## 5. Adding & Changing Events

- A new audited action adds a **new event key** to §2 using the
  `<module>.<entity>.<event>` grammar (past tense) and reusing the module names of
  `MODULES.md` and the entity nouns of `ENTITY_CATALOG.md`.
- Event keys are **stable**. Renaming or removing a key is a breaking change for
  log consumers and dashboards and requires an ADR — consistent with the
  append-mostly discipline of `PERMISSION_CATALOG.md` §4.
- Adding an event never changes the audit engine, the two stores, or the
  enforcement model (`AUDIT_POLICY.md` §8). It only extends this list.
- Every new state-changing action in `STATE_DIAGRAMS.md` SHOULD have a
  corresponding past-tense event here; every event here SHOULD map to a real,
  committed action.

---

### Related Documents
`AUDIT_POLICY.md` · `ENTITY_CATALOG.md` · `PERMISSION_CATALOG.md` ·
`STATE_DIAGRAMS.md` · `MODULES.md` · `ARCHIVING_POLICY.md` ·
`VERSIONING_POLICY.md` · `DATABASE_GUIDE.md` · `SECURITY_GUIDE.md`
