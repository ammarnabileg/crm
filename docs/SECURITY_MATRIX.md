# SECURITY MATRIX — HaHireAI

> **Status:** Adopted (Phase 4) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PERMISSION_CATALOG.md`, `ACCESS_POLICIES.md`, `NAVIGATION_MAP.md`.

---

## 1. How to Read This Matrix

This is the **single mapping of every screen and every action to the permission
key and policy that gate it.** It is a cross-reference, not a new source of
truth: every key cited here is defined verbatim in `PERMISSION_CATALOG.md`, every
screen comes from `NAVIGATION_MAP.md`, and every per-action *Purpose / Required
Permission / Dependencies / Denied Behaviour* contract lives in
`ACCESS_POLICIES.md`. Where this matrix and the catalog ever disagree, the
**catalog governs** (`PERMISSION_MODEL.md` §8).

### 1.1 The two halves of access: visibility vs authorization

Access in HaHireAI is decided in **two independent layers** that must *both*
pass. Conflating them is a defect.

| Layer | Question | Inputs | Where enforced | Failure mode |
|---|---|---|---|---|
| **Visibility** | Should this entry/screen/button *appear*? | permission key **AND** subscription/entitlement **AND** enabled module | Sidebar/UI engine (`NAVIGATION_MAP.md` §7, `SIDEBAR_MODEL.md`) | Entry is hidden / not rendered |
| **Authorization** | May this caller *execute* this use case? | permission key only (composite actions: several keys) | Application boundary, server-side (`SECURITY_GUIDE.md` §3) | `AuthorizationException` → 403, audited per policy |

- **Visibility is a UX convenience.** Hiding a sidebar item or button never
  authorizes anything and never substitutes for the server check
  (`PERMISSION_MODEL.md` §5; `SECURITY_GUIDE.md` §3). A hidden-but-reachable
  action **MUST** still be denied server-side.
- **Authorization is the real gate.** Every state-changing **or** data-reading
  use case verifies its required permission **before** executing — the API is
  just another caller of the same use case (`MODULES.md` §5; `SECURITY_GUIDE.md`
  §9). Read actions check their `*.view` key; no action is exempt.
- **Subscription + module gate visibility, not authorization.** A member may
  *hold* `interview.ai.run`, but if the plan excludes the AI Engine or the
  module is disabled, the action is unavailable. This is a Licensing/Subscriptions
  precondition (`PERMISSION_MATRIX.md` §5), **separate** from — never a
  replacement for — the permission check.

### 1.2 Deny-by-default

Absence of an explicit grant means **denied** — for permissions, routes, file
visibility, API scopes, and module capabilities (`SECURITY_GUIDE.md` §1.2;
`PERMISSION_MODEL.md` §5). Nothing is open unless something deliberately opens
it. Every protected action below maps to **at least one** required permission
key; there is no "unprotected" row.

### 1.3 Tenant isolation is always on

Every Workspace-Context row below is *additionally* and *unconditionally* scoped
to the active `workspace_id` by the mandatory repository tenant guard
(`SECURITY_GUIDE.md` §4; `DATABASE_GUIDE.md` §6.2). Isolation is the #1
invariant: it is **not** a column you can satisfy or skip — it applies to **every**
workspace-scoped read and write, including Files, AI usage, Billing, Search, and
both audit trails (`AUDIT_POLICY.md` §5). A permission grant in Workspace A has
no effect in Workspace B (`PERMISSION_MODEL.md` §6). Platform-Context rows act on
**global** data and carry no `workspace_id`; a System-Owner action that reaches
into one tenant's data is an explicit, permission-gated, audited **bypass**
(`DATABASE_GUIDE.md` §6.2; `AUDIT_POLICY.md` §5).

### 1.4 Key reconciliations (catalog is authoritative)

`NAVIGATION_MAP.md` is a Phase 2 draft that earlier cited a few *representative* gates
that predate the canon catalog. This matrix uses the **exact** Phase 4 keys from
`PERMISSION_CATALOG.md`:

| `NAVIGATION_MAP.md` draft gate | Authoritative key (this matrix) |
|---|---|
| `system.workspaces.view` (Overview) | `system.dashboard.view` |
| `system.analytics.view` (System Analytics) | `system.observability.view` |
| `system.developer.access` (Developer Tools) | `system.dashboard.view` + feature flag |
| `recruitment.view` (Recruitment Dashboard) | `job.view` (entry-level recruitment read) |
| `file.view` (Files) | `files.view` |
| `candidate.view` (Talent Pool screen) | `talent.view` |

---

## 2. Platform Context Matrix

Scope: the SaaS platform itself (`NAVIGATION_MAP.md` §3). Every entry requires a
`system.*` permission, is **invisible** in any Workspace Context, and operates on
**global** data (no `workspace_id`). Held only by **System Owners** — a `User`
granted `system.*`, never a reserved account (`PERMISSION_MODEL.md` §6).

| Screen / Action | Required `system.*` permission | Policy / notes |
|---|---|---|
| **Enter Platform Context** | `system.dashboard.view` | Gate to the entire console; absent ⇒ context not offered. |
| Overview (health & KPIs) | `system.dashboard.view` | Landing; read-only aggregate. |
| **Workspaces** (tenant list) | `system.workspaces.manage` | Cross-tenant system view; read+manage under one key. |
| Workspace Detail | `system.workspaces.manage` | Members, subscription, status, lifecycle. |
| → Suspend / resume workspace | `system.workspaces.manage` | Tenant-scope **bypass**; audited in `system_audit_logs`. |
| → Assign / change license | `system.workspaces.manage` (+ `system.subscriptions.manage` for plan) | Entitlement change; audited. |
| → Impersonate / enter as tenant | `system.workspaces.manage` | Explicit bypass; **always** audited (`AUDIT_POLICY.md` §2.2). |
| **Users** (global directory) | `system.users.manage` | The single `User` identity; global, not tenant data. |
| User Detail | `system.users.manage` | Profile, memberships, system flags. |
| → Suspend / reactivate / set system flag | `system.users.manage` | Privilege change; audited. |
| **Subscriptions** | `system.subscriptions.manage` | Per-workspace subscription state & revenue. |
| Subscription Detail | `system.subscriptions.manage` | One workspace's subscription lifecycle. |
| Plan Detail | `system.plans.manage` | A global Plan (features + limits). |
| → Create / edit plan or coupon | `system.plans.manage` | Catalog of plans & coupons; audited. |
| → Change a workspace's subscription | `system.subscriptions.manage` | Tenant entitlement change; audited. |
| **AI Providers** (global catalog) | `system.ai.manage` | Global provider/model catalog & platform keys. |
| Provider Detail | `system.ai.manage` | Models, credentials, fallback config. |
| → Add / rotate provider credential | `system.ai.manage` | Secret shown once; encrypted at rest (`SECURITY_GUIDE.md` §6.4); audited. |
| **System Analytics** | `system.observability.view` | Cross-tenant platform metrics; read-only. |
| **Audit Logs** (platform trail) | `system.audit.view` | `system_audit_logs`, global, immutable (`AUDIT_POLICY.md` §2.2, §6). |
| → Export platform audit | `system.audit.view` | Export inherits the same gate; MUST NOT widen scope. |
| **Platform Settings** | `system.settings.manage` | Global defaults & system configuration. |
| → Change platform setting / feature flag | `system.settings.manage` | Definition change; audited as System category. |
| **Diagnostics** | `system.diagnostics.run` | Health probes, runtime checks. |
| → Maintenance mode / cache / cleanup | `system.maintenance.manage` | Distinct from `diagnostics.run`; audited. |
| → Manage backups / restore | `system.backups.manage` | Backup/restore lifecycle; audited. |
| Developer Tools | `system.dashboard.view` **+ feature flag** | Hidden unless explicitly enabled (`NAVIGATION_MAP.md` §3). |
| (capability) Global connectors | `system.integrations.manage` | Platform-wide connector management. |

> No Platform-Context action branches on a role name; each is gated purely by its
> `system.*` key. `system.*` keys are the **only** system-scoped keys
> (`PERMISSION_CATALOG.md` §3).

---

## 3. Workspace Context Matrix

Scope: exactly one active workspace; **all** rows are tenant-isolated
(§1.3). Each row lists the **required permission(s)** (exact catalog keys), the
**enabling module** (`MODULES.md`) whose presence + subscription makes the entry
*visible*, and the **policy/notes**. Composite actions list every key required.
Recruitment is one bounded context; its sub-areas are visible only when the
**Recruitment** module is enabled and the matching `*.view` key is held.

### 3.1 Dashboard & Recruitment overview

| Screen / Action | Required permission(s) | Enabling module | Policy / notes |
|---|---|---|---|
| Dashboard (workspace landing) | `workspace.view` | Workspaces | Default landing on entering the workspace. |
| Recruitment ▸ Dashboard | `job.view` | Recruitment | Entry-level recruitment read (reconciles draft `recruitment.view`, §1.4). |

### 3.2 Jobs & Job Detail

| Screen / Action | Required permission(s) | Enabling module | Policy / notes |
|---|---|---|---|
| Jobs (requisitions list) | `job.view` | Recruitment | Visibility gate for all Jobs. |
| Job Detail ▸ Overview | `job.view` | Recruitment | Requisition summary + state. |
| → Create job | `job.create` | Recruitment | Audited (Recruitment category, `AUDIT_POLICY.md` §7). |
| → Edit job | `job.update` | Recruitment | Versioned (`job_versions`, immutable). |
| → Publish job | `job.publish` | Recruitment | Opens public posting; enables public apply. Audited. |
| → Pause published job | `job.pause` | Recruitment | State transition (`STATE_DIAGRAMS.md`). |
| → Close job | `job.close` | Recruitment | Audited. |
| → Archive job | `job.archive` | Recruitment | Removes from active lists. |
| → Delete job (soft) | `job.delete` | Recruitment | Soft-delete; still respects access controls. |
| → Clone / duplicate job | `job.clone` | Recruitment | Produces a Draft. |
| → Manage public link / share | `job.share` | Recruitment | Public link is the only un-authenticated surface. |
| Job Detail ▸ Applications (tab) | `application.view` | Recruitment | Candidacies bound to this Job. |
| Job Detail ▸ Pipeline (tab) | `pipeline.view` | Recruitment | This Job's stages (Kanban). |
| Job Detail ▸ Interviews (tab) | `interview.view` | Recruitment | Interviews for this Job's applications. |
| Job Detail ▸ Offers (tab) | `offer.view` | Recruitment | Offers on this Job's applications. |
| Job Detail ▸ Hiring Team (tab) | `member.view` + `job.view` | Recruitment, Memberships | Members assigned to this Job. |
| → Assign / remove hiring team member | `job.update` | Recruitment | Edits the requisition's team. |
| Job Detail ▸ Settings (tab) | `job.update` | Recruitment | Stages, screening questions, required docs. |
| → Manage screening questions / templates | `template.manage` | Recruitment | Template definitions (`PERMISSION_CATALOG.md` §Templates). |

### 3.3 Applications & Pipeline

| Screen / Action | Required permission(s) | Enabling module | Policy / notes |
|---|---|---|---|
| Applications (list / drill-in) | `application.view` | Recruitment | The spine: User + Job + Workspace. |
| Application ▸ Activity Timeline | `application.view` | Recruitment | Append-only, audit-backed history. |
| Application ▸ Documents | `application.view` + `files.view` | Recruitment, Files | Referenced Files (CV owned by the User, not copied). |
| → Update application | `application.update` | Recruitment | Status/data/documents; audited. |
| → Reject application | `application.reject` | Recruitment | State transition; audited. |
| → Record withdrawal | `application.withdraw` | Recruitment | Candidate-initiated outcome recorded. |
| Pipeline (cross-job Kanban) | `pipeline.view` | Recruitment | Read of stages across Jobs. |
| → Move stage / edit stages / bulk action | `pipeline.manage` | Recruitment | Stage moves + bulk; each move audited. |

### 3.4 Candidate Profile (PII)

| Screen / Action | Required permission(s) | Enabling module | Policy / notes |
|---|---|---|---|
| Candidate Profile (view) | `candidate.view` | Recruitment | **PII gate** (§4.4); per-`(User, Workspace)` view, not a global account. |
| → Add / view notes | `candidate.note` | Recruitment | Notes are workspace-scoped. |
| → Apply tag | `candidate.tag` | Recruitment | Tagging on the profile. |
| → Create / manage tag definitions | `candidate.tag.manage` | Recruitment | Distinct from applying a tag. |
| → Add rating / scorecard | `candidate.rate` | Recruitment | Evaluation data. |
| → **Export candidate data** | `candidate.export` | Recruitment | **High-risk PII export** — rate-limited + audited (§4). |

### 3.5 Interviews

| Screen / Action | Required permission(s) | Enabling module | Policy / notes |
|---|---|---|---|
| Interviews (list) / Interview Detail | `interview.view` | Recruitment | Scheduled AI + human evaluations. |
| → Schedule interview | `interview.schedule` | Recruitment | Human or AI; audited. |
| → **Run AI interview** | `interview.ai.run` (+ `ai.run`) | Recruitment → AI Engine | Visibility also needs AI in plan + module on; AI usage metered to this workspace (`SECURITY_GUIDE.md` §4.2); audited. |
| → Submit evaluation / scorecard | `interview.evaluate` | Recruitment | Evaluation outcome; audited. |
| → Cancel interview | `interview.cancel` | Recruitment | State transition. |

### 3.6 Offers

| Screen / Action | Required permission(s) | Enabling module | Policy / notes |
|---|---|---|---|
| Offers (list) / Offer Detail | `offer.view` | Recruitment | Offers & approvals. |
| → Create offer (draft) | `offer.create` | Recruitment | Draft on an application. |
| → Edit draft offer | `offer.update` | Recruitment | Draft-only edits. |
| → Send / extend offer | `offer.send` | Recruitment | Sends to candidate; audited. |
| → Revoke offer | `offer.revoke` | Recruitment | Revocation; audited. |
| → Convert hire to Employee | `employee.create` | Recruitment | On `Hired`; creates post-hire context. Audited. |
| → Update employee context | `employee.update` | Recruitment | Post-hire context of a `User`. |
| → View employees | `employee.view` | Recruitment | Post-hire read. |

### 3.7 Talent Pool

| Screen / Action | Required permission(s) | Enabling module | Policy / notes |
|---|---|---|---|
| Talent Pool (saved / passive / past) | `talent.view` | Recruitment | Reconciles draft `candidate.view` gate (§1.4). |
| → Manage talent pool / smart lists | `talent.manage` | Recruitment | Saved lists & smart lists. |
| → Save candidate to pool | `talent.manage` (+ `candidate.view`) | Recruitment | From a Candidate Profile. |

### 3.8 Reports & Analytics

| Screen / Action | Required permission(s) | Enabling module | Policy / notes |
|---|---|---|---|
| Reports / Recruitment ▸ Analytics | `report.view` | Reports / Analytics | Saved views & workspace reporting. |
| → Export report | `report.export` | Reports / Analytics | Export inherits scope; MUST NOT widen visibility. |

### 3.9 Members, Roles & Permissions

| Screen / Action | Required permission(s) | Enabling module | Policy / notes |
|---|---|---|---|
| Members / Member Detail | `member.view` | Memberships | Memberships, status, grants. |
| → Invite member | `member.invite` | Memberships | Audited (Membership category). |
| → Resend invitation | `member.invite.resend` | Memberships | — |
| → Cancel invitation | `member.invite.cancel` | Memberships | — |
| → Update member (roles/status) | `member.update` (+ `permission.assign` if changing grants) | Memberships, Permissions | Role/grant change is audited (`PERMISSION_MODEL.md` §7). |
| → Suspend member | `member.suspend` | Memberships | Audited. |
| → Reactivate member | `member.reactivate` | Memberships | Audited. |
| → Remove member | `member.remove` | Memberships | Audited. |
| Members ▸ Roles (Role Builder) | `role.view` | Permissions | Roles are **data**; no reserved roles (`ROLE_BUILDER.md`). |
| → Create role | `role.create` | Permissions | Audited. |
| → Rename / edit role | `role.update` | Permissions | Audited. |
| → Clone role | `role.clone` | Permissions | Audited. |
| → Delete role | `role.delete` | Permissions | Audited. |
| → Assign / revoke permissions on a role | `permission.assign` | Permissions | **Privilege change** — always audited. |
| → Transfer workspace ownership | `workspace.transfer` | Workspaces | Privilege change; session re-auth + audit. |

### 3.10 Files

| Screen / Action | Required permission(s) | Enabling module | Policy / notes |
|---|---|---|---|
| Files (browse) | `files.view` | Files | Workspace-scoped storage (reconciles draft `file.view`, §1.4). |
| → Upload file | `files.upload` | Files | Type+content sniffing, size limit, AV scan hook (`SECURITY_GUIDE.md` §7). |
| → Download file | `files.download` | Files | Served via tenant-guarded, permission-checked controller only. |
| → Delete file | `files.delete` | Files | Soft-delete per retention; audited. |
| → Manage folders / visibility | `files.manage` | Files | Folder + visibility changes; audited. |

### 3.11 Search, Notifications, Settings, Billing

| Screen / Action | Required permission(s) | Enabling module | Policy / notes |
|---|---|---|---|
| Search (unified, top-bar) | `search.use` | Search | Index is workspace-scoped; results never cross tenants (`SECURITY_GUIDE.md` §4.2). |
| → Open a search result | permission of the **target** entity | (target's module) | Cross-links honor the target's own gate (`NAVIGATION_MAP.md` §6). |
| Notifications (top-bar) | `notifications.view` | Notifications | Per-user, workspace-contextual. |
| → Manage preferences / mark / clear | `notifications.manage` | Notifications | — |
| Settings | `settings.view` | Settings | Workspace config landing. |
| → Update workspace settings | `settings.update` | Settings | Audited (Workspace category). |
| → Edit workspace core fields | `workspace.update` | Workspaces | Name/slug/profile; audited. |
| → Manage branding (logo/colors) | `workspace.branding` | Workspaces | Audited. |
| → Archive / restore / delete workspace | `workspace.archive` / `workspace.restore` / `workspace.delete` | Workspaces | Lifecycle; each audited. |
| → View / configure AI settings | `ai.view` / `ai.configure` | AI Engine | Configure needs AI in plan + module on. |
| → Manage workspace AI keys | `ai.keys.manage` | AI Engine | Secret shown once; encrypted at rest (`SECURITY_GUIDE.md` §6.4); audited. |
| → Manage workspace prompts | `ai.prompts.manage` | AI Engine | Prompt published ⇒ audited. |
| → View / manage integrations | `integration.view` / `integration.manage` | Integration Platform | Connector config; audited. |
| → Manage API keys (workspace) | `apikey.manage` | Integration Platform | Scoped, revocable, hashed at rest (`SECURITY_GUIDE.md` §9). |
| → Manage webhooks | `webhook.manage` | Integration Platform | HMAC secret per endpoint; shown once. |
| → View workspace audit log | `audit.view` | Audit | `audit_logs`, tenant-scoped, immutable (`AUDIT_POLICY.md` §2.1). |
| → Export workspace audit log | `audit.export` | Audit | Inherits gate + tenant scope. |
| Billing | `billing.view` | Billing / Subscriptions | Invoices, payments, subscription summary. |
| → Manage plan / payment methods | `billing.manage` | Billing / Subscriptions | Financial action; audited; never crosses tenants (`SECURITY_GUIDE.md` §4.2). |

> Workflow keys (`workflow.view/create/update/delete/publish/execute/approve`)
> follow the identical pattern — `*.view` gates visibility, each verb gates its
> action, all under the Workflow Engine module — and are catalogued in
> `PERMISSION_CATALOG.md` §Workflow.

### 3.12 Baseline capabilities (no workspace permission)

These are available to **any authenticated `User`** and are therefore **not**
gated by a workspace permission (`PERMISSION_CATALOG.md` §1) — but they remain
subject to CSRF, rate limits, and tenant stamping:

| Action | Gate | Notes |
|---|---|---|
| Create a workspace | authenticated `User` | Creator becomes owner via **direct grant**, not a reserved role. |
| Accept an invitation (join) | authenticated `User` + valid invite token | Single-use, expiring token. |
| Apply to a **public** job | authenticated `User` | Creates an `Application`; the public posting is the only un-authenticated surface (`job.share`/`job.publish`). |
| Manage own profile / sessions | authenticated `User` (self) | A user manages their own identity. |

---

## 4. Cross-Cutting Policies

These apply across **every** row above and are enforced server-side regardless of
UI state.

### 4.1 CSRF on all writes
Every `POST/PUT/PATCH/DELETE` (and any state-changing `GET`, which SHOULD be
avoided) **MUST** carry and validate a per-session CSRF token; requests failing
validation are rejected (`SECURITY_GUIDE.md` §5). This covers **all** `→ action`
rows in §2–§3. API callers use scoped token auth instead and are subject to the
same permission checks (`SECURITY_GUIDE.md` §9).

### 4.2 Rate limits on sensitive actions
Authentication endpoints (login, reset, MFA, token exchange) are rate-limited per
identifier and per IP with progressive backoff/lockout (`SECURITY_GUIDE.md`
§2.6). Bulk/PII-heavy actions — notably `candidate.export`, `report.export`,
`audit.export`, and AI-invoking actions (`ai.run`, `interview.ai.run`) — are
additionally rate-limited as high-risk (`SECURITY_GUIDE.md` §10). Webhook/API
requests enforce replay/nonce protection (`SECURITY_GUIDE.md` §9).

### 4.3 Audit on sensitive actions
Sensitive access and changes **MUST** be audited — who, when, where, what changed
— via the shared Audit module; the authoritative event-key list is
`AUDIT_EVENTS.md` (categories in `AUDIT_POLICY.md` §7). Always-audited classes:
permission/role changes (`permission.assign`, `role.*`, `member.update`),
ownership transfer (`workspace.transfer`), workspace lifecycle, exports
(`candidate.export`, `report.export`, `audit.export`), AI configuration/usage,
billing changes, and **every** Platform-Context `system.*` action (the platform
trail, `AUDIT_POLICY.md` §2.2). Audit is append-only and never edited or deleted
(`AUDIT_POLICY.md` §3); `changes` is redacted of secrets and full PII before
write (`AUDIT_POLICY.md` §1).

### 4.4 PII gating (`candidate.view` / `candidate.export`)
Candidate and employee personal data is first-class PII. Read access to a
Candidate Profile **MUST** hold `candidate.view`; there is no broad read
(`SECURITY_GUIDE.md` §10). Bulk export is the highest-risk PII action: it
requires `candidate.export`, is rate-limited (§4.2), is audited (§4.3), and is
tenant-scoped (§1.3). PII flagged sensitive is encrypted at rest
(`SECURITY_GUIDE.md` §6.4) and always in transit (§8). Documents (CVs) are
referenced Files owned by the `User`, gated by `files.view`/`files.download` plus
the Files tenant + visibility checks (`SECURITY_GUIDE.md` §7).

### 4.5 Secrets shown once
Any secret created in §2–§3 (provider credential, workspace AI key, API key,
webhook signing secret) is displayed **once** on creation and thereafter only as
a masked reference; reads never return the raw value, and the value is encrypted
at rest (`SECURITY_GUIDE.md` §6.4).

---

## 5. Self-Review Checklist

This matrix is conformant only if **all** hold:

- [ ] **No action without a permission.** Every screen and every `→ action` row
      cites at least one exact `PERMISSION_CATALOG.md` key; composite actions
      list all required keys. Deny-by-default leaves nothing exempt (§1.2).
- [ ] **No reliance on role names.** No row gates on a role; every gate is a
      permission key or a baseline capability (`PERMISSION_MODEL.md` §1;
      `SECURITY_GUIDE.md` §3). Roles are data (`ROLE_BUILDER.md`).
- [ ] **Visibility ≠ authorization.** Subscription/module appear only as
      *visibility* preconditions; the server permission check is always the gate
      (§1.1).
- [ ] **Isolation everywhere.** Every Workspace-Context row is tenant-scoped by
      the repository guard; Platform rows are global; any cross-tenant reach is an
      explicit, audited bypass (§1.3; `SECURITY_GUIDE.md` §4).
- [ ] **Read is a permission too.** Each screen uses a `*.view`/`*.use` key for
      its read gate (§1.2; `PERMISSION_MATRIX.md` §5).
- [ ] **Cross-cutting controls applied.** CSRF on writes, rate limits and audit
      on sensitive actions, PII gating on candidate read/export (§4).
- [ ] **Catalog governs.** Every key matches `PERMISSION_CATALOG.md` verbatim;
      draft-doc divergences are reconciled in §1.4, not invented here.

---

### Related Documents

`PERMISSION_CATALOG.md` · `PERMISSION_MODEL.md` · `PERMISSION_MATRIX.md` ·
`ACCESS_POLICIES.md` · `NAVIGATION_MAP.md` · `MODULES.md` · `SECURITY_GUIDE.md` ·
`AUDIT_POLICY.md` · `AUDIT_EVENTS.md` · `WORKSPACE_MODEL.md` ·
`DATABASE_GUIDE.md` · `ROLE_BUILDER.md` · `SYSTEM_PERMISSIONS.md` ·
`WORKSPACE_PERMISSIONS.md` · `STATE_DIAGRAMS.md`
