# ACCESS POLICIES — HaHireAI

> **Status:** Adopted (Phase 4) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PERMISSION_CATALOG.md`, `PERMISSION_MODEL.md`. **Matrix:** `SECURITY_MATRIX.md`.

---

## 1. Purpose & Scope

This document is the **per-action access policy** for HaHireAI. For each major
protected action it records the **required permission(s)**, any **dependencies**,
and the **denied behaviour**. It binds the enforcement rules of
`PERMISSION_MODEL.md` to concrete actions using the **exact keys** registered in
`PERMISSION_CATALOG.md`. Where this document and the catalog/model disagree, the
**catalog and model govern**. The screen-level visibility map is
`NAVIGATION_MAP.md`; the consolidated who-can-do-what grid is
`SECURITY_MATRIX.md`.

This is **documentation only** — no code, no role names.

---

## 2. The Policy Model

1. **Deny by default.** Every action requires an explicit permission key; absence
   of the key ⇒ **denied** (`PERMISSION_MODEL.md` §5). There is no implicit
   allow.
2. **Enforced at the Application boundary.** Authorization checks run **server
   side**, at the Application (use-case) boundary, **before** any state change or
   data read executes (`PERMISSION_MODEL.md` §5). Every state-changing *and* every
   data-reading action is checked; read actions check the relevant `*.view` key.
3. **Checks reference permission keys only — never role names.** No policy in this
   document, and no code, may branch on a role's name or title
   (`PERMISSION_MODEL.md` §1, §5).
4. **UI hiding is not enforcement.** Hidden sidebar entries or disabled buttons
   are a usability affordance, not a security control. A request that reaches the
   Application boundary is authorized there regardless of whether any UI exposed
   it (`PERMISSION_MODEL.md` §5; `NAVIGATION_MAP.md` §1).
5. **The tenant guard always applies.** Every workspace-scoped action is
   additionally constrained to the **active workspace's** data; cross-tenant
   access is impossible regardless of permissions (`PERMISSION_MODEL.md` §6;
   `NAVIGATION_MAP.md` §6). The tenant guard is **independent of** and **prior
   to** the permission check — a permitted action on another tenant's data is
   still denied.
6. **Subscription & enabled modules gate visibility, not authorization.** They
   determine whether a capability is offered to a workspace, but the
   authorization decision is the permission check itself
   (`PERMISSION_CATALOG.md` §3, rule 3).
7. **Composite actions require all listed keys.** Where the table lists more than
   one permission, **every** one MUST hold; missing any ⇒ denied
   (`PERMISSION_CATALOG.md` §4, rule 1).
8. **System scope is separate.** `system.*` keys are held by System Owners and
   evaluated only in the Platform Context (`PERMISSION_MODEL.md` §6;
   `PERMISSION_CATALOG.md` §3). They never substitute for workspace permissions.

### 2.1 Denied-Behaviour Conventions

| Notation | Meaning |
|---|---|
| **403 + audit** | Request rejected at the Application boundary with an authorization error; the denied attempt SHOULD be recorded (see `AUDIT_EVENTS.md`). |
| **hidden** | The entry/control is not rendered when the gate fails (UI affordance). Hiding is **in addition to** server-side 403, never instead of it. |
| **404 (tenant)** | Cross-tenant or out-of-scope resources are reported as not found rather than forbidden, so existence is not leaked across tenants. Applies under the tenant guard (§2.5). |
| **409 (state)** | The action is permitted but rejected because the entity's current state forbids the transition (`STATE_DIAGRAMS.md`); listed where state and permission interact. |

Unless noted, every protected action's denied behaviour is **403 + audit**, and
its UI entry is additionally **hidden** when the view gate fails.

---

## 3. Baseline (Non-Permission) Actions

These are available to **any authenticated `User`** and are **not** gated by a
workspace permission (`PERMISSION_CATALOG.md` §1). They are listed so policies do
not erroneously gate them.

| Action | Gate | Denied Behaviour |
|---|---|---|
| Create a workspace | authenticated user (`workspace.create` baseline) | redirect to sign-in if unauthenticated |
| Accept an invitation / join | authenticated user (`workspace.join` baseline) | invalid/expired invitation ⇒ 410/404 |
| Apply to a **published** public job | authenticated user (any) | job not `Published` ⇒ 404/409 (`STATE_DIAGRAMS.md` §2) |
| Manage own profile / sessions | self only | 403 on another user's identity |

---

## 4. Per-Action Policy Tables

Keys below are the **exact** keys from `PERMISSION_CATALOG.md`. Every row also
carries the implicit **tenant guard** (§2.5) and **deny-by-default** (§2.1).
"Dependencies" lists permissions/states that must also hold for the action to be
meaningful or reachable.

### 4.1 Workspace

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View workspace overview | `workspace.view` | — | 403 + audit; entry hidden |
| Edit workspace core fields | `workspace.update` | `workspace.view` | 403 + audit |
| Manage workspace settings | `workspace.settings` | `settings.view` | 403 + audit |
| Manage branding (logo/colors) | `workspace.branding` | `workspace.view` | 403 + audit |
| Archive the workspace | `workspace.archive` | state `Active` (`STATE_DIAGRAMS.md` §10) | 403 + audit; 409 if not Active |
| Restore an archived workspace | `workspace.restore` | state `Archived` | 403 + audit; 409 if not Archived |
| Soft-delete the workspace | `workspace.delete` | state `Active` | 403 + audit |
| Transfer ownership | `workspace.transfer` | state `Active`; target is an Active member | 403 + audit (high-sensitivity; `ROLE_BUILDER.md` §6) |

### 4.2 Members (Memberships)

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View members | `member.view` | — | 403 + audit; entry hidden |
| Invite a member | `member.invite` | `member.view` | 403 + audit |
| Resend an invitation | `member.invite.resend` | `member.view`; invitation `Pending` (`STATE_DIAGRAMS.md` §8) | 403 + audit; 409 if not Pending |
| Cancel an invitation | `member.invite.cancel` | `member.view`; invitation `Pending` | 403 + audit; 409 if not Pending |
| Update a member (status/roles) | `member.update` | `member.view` | 403 + audit |
| **Assign/remove a role on a member** | `member.update` **+** `permission.assign` | `member.view`; role exists (`ROLE_BUILDER.md` §8) | 403 + audit (**composite** — both required) |
| Suspend a member | `member.suspend` | `member.view`; membership `Active` (`STATE_DIAGRAMS.md` §7) | 403 + audit; 409 if not Active |
| Reactivate a member | `member.reactivate` | membership `Suspended` | 403 + audit; 409 if not Suspended |
| Remove a member | `member.remove` | `member.view`; not the sole role-manager (`ROLE_BUILDER.md` §7.3) | 403 + audit |

### 4.3 Roles & Permissions

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View roles | `role.view` | — | 403 + audit; entry hidden |
| Create a role | `role.create` | `role.view` | 403 + audit |
| Rename/edit a role | `role.update` | `role.view` | 403 + audit |
| **Edit a role's permission set** | `role.update` **+** `permission.assign` | `role.view` (**composite**) | 403 + audit |
| Clone a role | `role.clone` | `role.view` | 403 + audit |
| Delete a role | `role.delete` | `role.view`; reassignment rules (`ROLE_BUILDER.md` §7) | 403 + audit; blocked if it causes management lockout |
| Assign permissions to roles/members | `permission.assign` | `role.view` | 403 + audit |

> Selectable permissions are workspace keys only; `system.*` keys are never
> assignable to a workspace role (`ROLE_BUILDER.md` §4).

### 4.4 Jobs (Recruitment)

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View jobs / job detail | `job.view` | Recruitment enabled | 403 + audit; entry hidden |
| Create a job | `job.create` | `job.view` | 403 + audit |
| Edit a job | `job.update` | `job.view` | 403 + audit |
| Publish a job | `job.publish` | `job.view`; state `Draft` (`STATE_DIAGRAMS.md` §2) | 403 + audit; 409 if not Draft |
| Pause a published job | `job.pause` | state `Published` | 403 + audit; 409 if not Published |
| Close a job | `job.close` | state `Published`/`Paused` | 403 + audit; 409 otherwise |
| Archive a job | `job.archive` | state `Draft`/`Closed` | 403 + audit; 409 otherwise |
| Soft-delete a job | `job.delete` | `job.view` | 403 + audit |
| Clone/duplicate a job | `job.clone` | `job.view` | 403 + audit |
| Manage public link / share | `job.share` | `job.view`; state `Published` for live link | 403 + audit |

### 4.5 Candidates (Recruitment)

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View candidate profile | `candidate.view` | Recruitment enabled | 403 + audit; entry hidden; 404 (tenant) cross-workspace |
| Add/view notes | `candidate.note` | `candidate.view` | 403 + audit |
| Apply tags | `candidate.tag` | `candidate.view` | 403 + audit |
| Create/manage tag definitions | `candidate.tag.manage` | `candidate.view` | 403 + audit |
| Add ratings/scorecards | `candidate.rate` | `candidate.view` | 403 + audit |
| Export candidate data | `candidate.export` | `candidate.view` (**sensitive**; `files.download` if file export) | 403 + audit (export attempts always audited) |

### 4.6 Applications & Pipeline (Recruitment)

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View applications | `application.view` | `job.view`; Recruitment enabled | 403 + audit; entry hidden |
| Update an application | `application.update` | `application.view` | 403 + audit |
| Reject an application | `application.reject` | `application.view`; state non-terminal (`STATE_DIAGRAMS.md` §3) | 403 + audit; 409 if terminal |
| Record a withdrawal | `application.withdraw` | `application.view`; state non-terminal | 403 + audit; 409 if terminal |
| View the pipeline | `pipeline.view` | `application.view` | 403 + audit; entry hidden |
| Move stages / edit stages / bulk actions | `pipeline.manage` | `pipeline.view`; valid transition (`STATE_DIAGRAMS.md` §3) | 403 + audit; 409 on forbidden transition |
| **Advance a stage that starts an AI interview** | `pipeline.manage` **+** `interview.ai.run` | `interview.view`; AI within subscription | 403 + audit (**composite**) |

### 4.7 Interviews (Recruitment / AI Engine)

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View interviews / interview detail | `interview.view` | Recruitment enabled | 403 + audit; entry hidden |
| Schedule an interview | `interview.schedule` | `interview.view`; application non-terminal | 403 + audit |
| **Run an AI interview** | `interview.ai.run` | `interview.view`; AI Engine enabled; AI within subscription | 403 + audit; **403/feature-gated** if AI not subscribed |
| Submit evaluations/scorecards | `interview.evaluate` | `interview.view`; interview `Completed` (`STATE_DIAGRAMS.md` §5) | 403 + audit; 409 if not Completed |
| Cancel an interview | `interview.cancel` | `interview.view`; state `Scheduled`/`InProgress` | 403 + audit; 409 otherwise |

> AI interview outcomes are **advisory**; the human stage transition is governed
> by §4.6 (`STATE_DIAGRAMS.md` §3). `interview.ai.run` authorizes invoking the
> capability, not deciding the candidate's progression.

### 4.8 Offers & Employees (Recruitment)

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View offers / offer detail | `offer.view` | Recruitment enabled | 403 + audit; entry hidden |
| Create an offer | `offer.create` | `offer.view`; application non-terminal (`STATE_DIAGRAMS.md` §3) | 403 + audit |
| Edit a draft offer | `offer.update` | `offer.view`; offer `Draft` (`STATE_DIAGRAMS.md` §4) | 403 + audit; 409 if not Draft |
| Send an offer | `offer.send` | `offer.view`; offer `Approved` | 403 + audit; 409 if not Approved |
| Revoke an offer | `offer.revoke` | `offer.view`; offer `Approved`/`Sent` | 403 + audit; 409 otherwise |
| View employees | `employee.view` | Recruitment enabled | 403 + audit; entry hidden |
| **Create employee (on hire)** | `employee.create` | application `Hired` (`STATE_DIAGRAMS.md` §3, §6); typically a consequence of `Offer.accept` | 403 + audit; 409 if application not Hired |
| Update employee | `employee.update` | `employee.view` | 403 + audit |

### 4.9 Talent Pool & Templates (Recruitment)

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View talent pool | `talent.view` | Recruitment enabled | 403 + audit; entry hidden |
| Manage talent pool / smart lists | `talent.manage` | `talent.view` | 403 + audit |
| View templates | `template.view` | Recruitment enabled | 403 + audit; entry hidden |
| Manage templates | `template.manage` | `template.view` | 403 + audit |

### 4.10 Reports / Analytics

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View reports/analytics | `report.view` | underlying module view perms for drill-down | 403 + audit; entry hidden |
| Export reports | `report.export` | `report.view` (**sensitive**) | 403 + audit (export attempts audited) |

> Drilling from a metric into underlying records additionally requires the target
> entity's view permission (e.g. `job.view`, `application.view`) under the tenant
> guard (`NAVIGATION_MAP.md` §6).

### 4.11 Files

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View files | `files.view` | — | 403 + audit; entry hidden; 404 (tenant) cross-workspace |
| Upload files | `files.upload` | `files.view` | 403 + audit |
| Download files | `files.download` | `files.view` | 403 + audit |
| Delete files | `files.delete` | `files.view` | 403 + audit |
| Manage folders/visibility | `files.manage` | `files.view` | 403 + audit |

> A CV referenced by an Application is a File **owned by the User**, not copied
> (`NAVIGATION_MAP.md` §5). Viewing it through an Application also requires
> `application.view`; downloading it requires `files.download`.

### 4.12 Notifications · Search · Audit

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View notifications | `notifications.view` | — | 403 + audit |
| Manage preferences / mark / clear | `notifications.manage` | `notifications.view` | 403 + audit |
| Use unified search | `search.use` | per-result entity view perms enforced on click-through | 403 + audit; results filtered to permitted, in-tenant entities |
| View workspace audit log | `audit.view` | — | 403 + audit; entry hidden |
| Export workspace audit log | `audit.export` | `audit.view` (**sensitive**) | 403 + audit |

> Search MUST NOT return entities the requester cannot view, and MUST NOT cross
> tenants — even when a permission would otherwise allow it
> (`NAVIGATION_MAP.md` §6; tenant guard §2.5).

### 4.13 Settings

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View settings | `settings.view` | — | 403 + audit; entry hidden |
| Update settings | `settings.update` | `settings.view`; area-specific keys for delegated areas (e.g. `workspace.branding`, `ai.configure`) | 403 + audit |

### 4.14 AI (workspace · AI Engine)

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View AI settings/usage | `ai.view` | AI Engine enabled | 403 + audit; entry hidden |
| Invoke AI capabilities | `ai.run` | `ai.view`; AI within subscription | 403 + audit; feature-gated if not subscribed |
| Configure provider/model/limits | `ai.configure` | `ai.view` | 403 + audit |
| Manage workspace API keys | `ai.keys.manage` | `ai.view` (**high-sensitivity**) | 403 + audit |
| Manage workspace prompts | `ai.prompts.manage` | `ai.view` | 403 + audit |

> `interview.ai.run` (§4.7) is the **recruitment-scoped** capability to run an AI
> interview; `ai.run` is the generic AI invocation gate. A composite recruitment
> flow that calls AI MAY require both (`PERMISSION_CATALOG.md` §2 → AI;
> `MODULES.md` §5).

### 4.15 Billing (Commerce)

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View billing/invoices/usage | `billing.view` | Billing/Subscriptions enabled | 403 + audit; entry hidden |
| Manage plan/payment methods | `billing.manage` | `billing.view` (**high-sensitivity**) | 403 + audit |

### 4.16 Integration (Integration Platform)

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View integrations | `integration.view` | Integration enabled | 403 + audit; entry hidden |
| Connect/configure integrations | `integration.manage` | `integration.view` | 403 + audit |
| Create/rotate/revoke API keys | `apikey.manage` | `integration.view` (**high-sensitivity**) | 403 + audit |
| Manage webhooks | `webhook.manage` | `integration.view` | 403 + audit |

### 4.17 Workflow (Workflow Engine)

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| View workflows/executions | `workflow.view` | Workflow enabled | 403 + audit; entry hidden |
| Create a workflow | `workflow.create` | `workflow.view` | 403 + audit |
| Edit a workflow | `workflow.update` | `workflow.view` | 403 + audit |
| Delete a workflow | `workflow.delete` | `workflow.view` | 403 + audit |
| Publish a workflow | `workflow.publish` | `workflow.view` | 403 + audit |
| Manually trigger a workflow | `workflow.execute` | `workflow.view`; **plus** the permission(s) for any action the workflow performs | 403 + audit (**composite** — workflow runs under the actor's authority) |
| Act on an approval step | `workflow.approve` | `workflow.view`; execution `WaitingApproval` (`STATE_DIAGRAMS.md` §11) | 403 + audit; 409 if not awaiting approval |

> A workflow MUST NOT escalate privilege: an automated or manually triggered
> action is authorized against the **same** permission keys a human would need
> (`MODULES.md` §5; deny-by-default §2.1).

### 4.18 System (`system.*` — Platform Context, System Owners only)

Evaluated **only** in the Platform Context; never a substitute for workspace
permissions (`PERMISSION_MODEL.md` §6; `PERMISSION_CATALOG.md` §3). Out-of-context
access ⇒ **404 (tenant/context)** so platform surfaces are not enumerable by
workspace users.

| Action | Required Permission(s) | Dependencies | Denied Behaviour |
|---|---|---|---|
| Access Platform Context | `system.dashboard.view` | System Owner | 404 (context) for non-owners |
| Manage all users | `system.users.manage` | `system.dashboard.view` | 403 + audit |
| Manage all workspaces (suspend/resume/license) | `system.workspaces.manage` | `system.dashboard.view` | 403 + audit |
| Manage subscriptions/revenue | `system.subscriptions.manage` | `system.dashboard.view` | 403 + audit |
| Manage plans & coupons | `system.plans.manage` | `system.dashboard.view` | 403 + audit |
| Manage platform settings | `system.settings.manage` | `system.dashboard.view` | 403 + audit |
| Manage global AI providers/models | `system.ai.manage` | `system.dashboard.view` | 403 + audit |
| Manage global connectors | `system.integrations.manage` | `system.dashboard.view` | 403 + audit |
| Run diagnostics | `system.diagnostics.run` | `system.dashboard.view` | 403 + audit |
| View platform metrics/logs/errors | `system.observability.view` | `system.dashboard.view` | 403 + audit |
| Maintenance mode / cache / cleanup | `system.maintenance.manage` | `system.dashboard.view` (**high-sensitivity**) | 403 + audit |
| Manage backups/restore | `system.backups.manage` | `system.dashboard.view` (**high-sensitivity**) | 403 + audit |
| View platform audit log | `system.audit.view` | `system.dashboard.view` | 403 + audit |

---

## 5. Composite Actions — Summary

Actions requiring **multiple permission keys** (all MUST hold;
`PERMISSION_CATALOG.md` §4, rule 1):

| Composite action | Keys (all required) |
|---|---|
| Assign/remove a role on a member | `member.update` + `permission.assign` |
| Edit a role's permission set | `role.update` + `permission.assign` |
| Advance a stage that triggers an AI interview | `pipeline.manage` + `interview.ai.run` |
| Recruitment flow invoking generic AI | the action's key + `ai.run` |
| Export candidate/report **files** | the export key + `files.download` |
| Manually trigger a workflow that performs gated actions | `workflow.execute` + each performed action's key |
| Hire → create employee | `employee.create` + application state `Hired` (consequence of `offer.accept`) |

For composites, the denied behaviour is **403 + audit** when **any** required
key is missing.

---

## 6. Cross-Cutting Rules (Restated)

1. **Deny-by-default** governs every row; an unlisted action is denied until a
   policy and a catalog key exist for it (`PERMISSION_MODEL.md` §5).
2. **Tenant guard first.** The active-workspace constraint is evaluated **before**
   and **independently of** permissions; cross-tenant access yields **404
   (tenant)**, never a permitted result (§2.5).
3. **Keys, not names.** No policy branches on a role's name
   (`PERMISSION_MODEL.md` §1).
4. **UI hiding ≠ enforcement.** Every "hidden" entry is *also* 403-enforced
   server-side (§2.4).
5. **State gates compose with permission gates.** Holding the permission does not
   bypass the state machine; an out-of-state action yields **409**
   (`STATE_DIAGRAMS.md`).
6. **Audit the decision.** State-changing actions and **all denied attempts on
   sensitive actions** (exports, key management, ownership transfer, member
   removal) are recorded (`AUDIT_EVENTS.md`; `PERMISSION_MODEL.md` §7).
7. **Append-mostly catalog.** New actions add rows here and keys to
   `PERMISSION_CATALOG.md`; they never alter the permission engine
   (`PERMISSION_CATALOG.md` §4–§5).

---

### Related Documents

`PERMISSION_CATALOG.md` · `PERMISSION_MODEL.md` · `SECURITY_MATRIX.md` ·
`ROLE_BUILDER.md` · `AUDIT_EVENTS.md` · `STATE_DIAGRAMS.md` ·
`NAVIGATION_MAP.md` · `MODULES.md` · `SYSTEM_PERMISSIONS.md` ·
`WORKSPACE_PERMISSIONS.md`
