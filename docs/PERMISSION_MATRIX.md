# PERMISSION MATRIX — HaHireAI

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PERMISSION_MODEL.md`. **Full catalog & policies:** Phase 4 (`PERMISSION_CATALOG.md`, `SECURITY_MATRIX.md`).

---

## 1. How to Read This Matrix

This document is the **Phase 2 reference list** of every permission key in
HaHireAI and the module that owns it. It is a *map*, not the enforcement engine
and not the authoritative catalog — both of those are produced in Phase 4
(`PERMISSION_CATALOG.md`, `SECURITY_MATRIX.md`). Where this matrix and
`PERMISSION_MODEL.md` ever disagree, the **Permission Model governs**.

### 1.1 The chain of authority

Authorization in HaHireAI flows in one direction (see `PERMISSION_MODEL.md` §1):

```
Permissions  →  Roles  →  Members  →  Workspace
   (atoms)      (bundles)  (assignment)  (scope)
```

- A **Permission** is a fine-grained capability identified by a stable key
  (e.g. `job.create`). It is the atomic unit of authorization.
- A **Role** is *only* a named bundle of permission keys, defined inside one
  workspace. Roles are **data**, created by users.
- A **Membership** is granted zero or more roles (plus optional direct grants).
  Effective permissions = the union of all granted roles' permissions.
- A **Workspace** is the isolation boundary; a member's workspace permissions in
  one workspace never affect any other workspace.

### 1.2 Deny-by-default

Every action — state-changing **or** data-reading — requires an explicit
permission. **Absence of a permission means denied.** No action is exempt; read
actions check their corresponding `*.view` permission. Checks reference
**permission keys only**, never role names (see §6 and `PERMISSION_MODEL.md` §5).

### 1.3 What the columns mean

The **Master Matrix** in §2 has these columns:

| Column | Meaning |
|---|---|
| **Permission key** | The stable `resource.action` identifier used in checks. |
| **Category** | A **display-only** grouping for the Role Builder UI. Never affects enforcement (see §5). |
| **What it allows** | The capability the key authorizes, in one line. |
| **Owning / Using module(s)** | The module that **declares** the permission, plus modules that **check** it. Names match `MODULES.md` exactly. |
| **Scope** | `Workspace` (held by members via roles/grants, gated by subscription + enabled modules) or `System` (held only by System Owners, visible only in Platform Context). See §4. |

### 1.4 Permission grammar (recap)

- **Grammar:** `resource.action` — lowercase, dot-separated — optionally
  `resource.subresource.action` (e.g. `interview.ai.run`, `ai.keys.manage`).
- **System permissions** use the `system.` prefix and the
  `system.area.action` form (e.g. `system.workspaces.manage`).
- Permissions are **declared per module** in its manifest (`module.php`) and
  aggregated into the global Permission Catalog. Categories are assigned **for
  display only**.

---

## 2. Master Matrix

One row per permission. The seed below is **canonical and verbatim**; additional
keys that follow the grammar are marked **(+)** in the *What it allows* column and
consolidated in §2.x notes. All keys are unique (see §6).

### 2.1 Workspace, Members & Roles (Identity & Access)

| Permission key | Category | What it allows | Owning / Using module(s) | Scope |
|---|---|---|---|---|
| `workspace.view` | Workspace | View the current workspace and its profile. | Workspaces | Workspace |
| `workspace.update` | Workspace | Edit workspace name, slug, branding, and general profile. | Workspaces | Workspace |
| `workspace.delete` | Workspace | Soft-delete (and request restore of) the workspace. | Workspaces | Workspace |
| `workspace.settings` | Workspace | Open and manage workspace settings (timezone, language, policies). | Workspaces, Settings | Workspace |
| `member.view` | Members | View the workspace member list and membership details. | Memberships | Workspace |
| `member.invite` | Members | Invite users to the workspace and manage pending invitations. | Memberships | Workspace |
| `member.update` | Members | Change a member's status and assigned roles. | Memberships, Permissions | Workspace |
| `member.remove` | Members | Remove a member from the workspace. | Memberships | Workspace |
| `role.create` | Roles | Create a new role (bundle of permissions) in the workspace. | Permissions | Workspace |
| `role.update` | Roles | Rename, clone, and edit an existing role. | Permissions | Workspace |
| `role.delete` | Roles | Delete a role. | Permissions | Workspace |
| `permission.assign` | Roles | Assign or revoke permissions on a role (and direct grants, if enabled). | Permissions | Workspace |

### 2.2 Recruitment — Jobs, Candidates, Applications, Interviews, Offers, Employees

| Permission key | Category | What it allows | Owning / Using module(s) | Scope |
|---|---|---|---|---|
| `job.create` | Jobs | Create a new job (hiring requisition/posting). | Recruitment (Jobs) | Workspace |
| `job.update` | Jobs | Edit an existing job's details and hiring team. | Recruitment (Jobs) | Workspace |
| `job.publish` | Jobs | Publish a job to its public posting (and unpublish). | Recruitment (Jobs) | Workspace |
| `job.archive` | Jobs | Archive a job, removing it from active lists. | Recruitment (Jobs) | Workspace |
| `job.delete` | Jobs | Delete a job. | Recruitment (Jobs) | Workspace |
| `candidate.view` | Candidates | View a workspace-scoped Candidate Profile. | Recruitment (Candidate Profiles) | Workspace |
| `candidate.note` | Candidates | Add or edit notes on a Candidate Profile. | Recruitment (Candidate Profiles) | Workspace |
| `candidate.tag` | Candidates | Add or remove tags on a Candidate Profile. | Recruitment (Candidate Profiles) | Workspace |
| `candidate.export` | Candidates | Export candidate data out of the workspace. | Recruitment (Candidate Profiles) | Workspace |
| `application.view` | Applications | View applications (candidacies) and their timeline. | Recruitment (Applications) | Workspace |
| `application.update` | Applications | Update an application's data, status, and documents. | Recruitment (Applications) | Workspace |
| `pipeline.manage` | Applications | Manage pipeline stages and move applications between them. | Recruitment (Pipeline) | Workspace |
| `interview.schedule` | Interviews | Schedule a human or AI interview on an application. | Recruitment (Interviews) | Workspace |
| `interview.ai.run` | Interviews | Run an AI interview session on an application. | Recruitment (Interviews), AI Engine | Workspace |
| `offer.create` | Offers | Create a draft offer on an application. | Recruitment (Offers) | Workspace |
| `offer.send` | Offers | Send/extend an offer to the candidate. | Recruitment (Offers) | Workspace |
| `employee.create` | Employees | Create an employee context from a hired application. | Recruitment (Employees) | Workspace |
| `employee.update` | Employees | Update an employee's post-hire context. | Recruitment (Employees) | Workspace |

### 2.3 Reports, Files, Notifications & AI (Intelligence + Platform Services)

| Permission key | Category | What it allows | Owning / Using module(s) | Scope |
|---|---|---|---|---|
| `report.view` | Reports | View reports, dashboards, and saved views. | Reports / Analytics | Workspace |
| `report.export` | Reports | Export reports and saved views. | Reports / Analytics | Workspace |
| `files.upload` | Files | Upload files into the workspace. | Files | Workspace |
| `files.delete` | Files | Delete files from the workspace. | Files | Workspace |
| `notifications.manage` | Notifications | Manage notification preferences and the notification center. | Notifications | Workspace |
| `ai.configure` | AI | Configure workspace AI providers, models, prompts, and limits. | AI Engine | Workspace |
| `ai.run` | AI | Invoke AI capabilities (run an AI Session). | AI Engine | Workspace |
| `ai.keys.manage` | AI | Manage workspace-scoped AI provider keys. | AI Engine | Workspace |

### 2.4 Commerce & Settings

| Permission key | Category | What it allows | Owning / Using module(s) | Scope |
|---|---|---|---|---|
| `billing.view` | Billing | View invoices, payments, and the subscription summary. | Billing, Subscriptions | Workspace |
| `billing.manage` | Billing | Manage payment methods, plans, coupons, and billing actions. | Billing, Subscriptions | Workspace |
| `settings.update` | Settings | Update workspace settings entries in the settings registry. | Settings | Workspace |

> **Subscription vs Billing note.** `billing.*` authorizes *acting on* invoices
> and payment configuration. Whether a workspace **may** use a paid module at all
> is a **subscription/licensing gate**, not a permission — see §5. The
> Subscriptions and Licensing modules enforce entitlements; they do not introduce
> separate per-key authorization beyond `billing.*` in this phase.

### 2.5 System (System Owner only — `system.*`)

These keys are held **only** by System Owners and are visible **only** in the
Platform Context (see §4 and `SYSTEM_BLUEPRINT.md` §8).

| Permission key | Category | What it allows | Owning / Using module(s) | Scope |
|---|---|---|---|---|
| `system.users.manage` | System | Manage platform-wide `User` identities. | System Administration, Users | System |
| `system.workspaces.manage` | System | Manage all tenant workspaces (create, archive, restore, transfer). | System Administration, Workspaces | System |
| `system.subscriptions.manage` | System | Manage plans, tenant subscriptions, and entitlements globally. | System Administration, Subscriptions, Licensing | System |
| `system.settings.manage` | System | Manage global/system settings and defaults. | System Administration, Settings | System |
| `system.diagnostics.run` | System | Run platform diagnostics, health checks, and maintenance probes. | System Administration, Observability | System |
| `system.ai.manage` | System | Manage global AI providers, models, and platform AI configuration. | System Administration, AI Engine | System |
| `system.audit.view` | System | View the system-level audit trail across the platform. | System Administration, Audit | System |

### 2.6 Added keys (following the grammar) — optional, non-seed

The following keys extend the seed using the same grammar and are **proposed**
for the Phase 4 catalog. They are listed separately so the verbatim seed stays
unambiguous. Each is marked **(+)**.

| Permission key | Category | What it allows | Owning / Using module(s) | Scope |
|---|---|---|---|---|
| `workspace.transfer` | Workspace | **(+)** Transfer workspace ownership to another member. | Workspaces, Memberships | Workspace |
| `audit.view` | Audit | **(+)** View the workspace-scoped audit/activity trail. | Audit | Workspace |
| `search.use` | Search | **(+)** Use unified workspace-scoped search. | Search | Workspace |
| `talent_pool.manage` | Candidates | **(+)** Manage the workspace Talent Pool (saved/passive candidates). | Recruitment (Talent Pool) | Workspace |
| `template.manage` | Recruitment | **(+)** Manage recruitment templates (job, email, scorecard). | Recruitment (Templates) | Workspace |
| `workflow.manage` | Process | **(+)** Create and manage automations, triggers, and approvals. | Workflow Engine | Workspace |
| `integration.manage` | Integration | **(+)** Manage workspace API keys, webhooks, and connectors. | Integration Platform | Workspace |
| `subscription.view` | Billing | **(+)** View the workspace subscription, status, and limits. | Subscriptions | Workspace |

> Adopting any **(+)** key in Phase 4 must keep it unique and must not change the
> permission engine — only the declaring module's manifest (see
> `PERMISSION_MODEL.md` §8 invariant 5 and `MODULES.md` §6).

---

## 3. Module → Declared Permissions

Each module **declares** the permissions it owns in its `module.php` manifest
(`MODULES.md` §6). The table below lists every module from the canonical Module
Catalog and the seed (+ optional) keys it declares. Modules that declare **no**
permissions in this phase are shown with `—`; they still participate in
enforcement by **checking** other modules' keys (those checks are captured in the
*Using module(s)* column of §2).

| Module (per `MODULES.md`) | Layer | Declared permission keys |
|---|---|---|
| **Core Kernel** | Foundation | — |
| **Database** | Foundation | — |
| **Installer** | Foundation | — |
| **Authentication** | Identity & Access | — (identity is checked, not permission-gated at login) |
| **Users** | Identity & Access | — (workspace-facing; system-facing via `system.users.manage`) |
| **Permissions** | Identity & Access | `role.create`, `role.update`, `role.delete`, `permission.assign` |
| **Workspaces** | Identity & Access | `workspace.view`, `workspace.update`, `workspace.delete`, `workspace.settings`, `workspace.transfer` **(+)** |
| **Memberships** | Identity & Access | `member.view`, `member.invite`, `member.update`, `member.remove` |
| **Settings** | Platform Services | `settings.update` |
| **Files** | Platform Services | `files.upload`, `files.delete` |
| **Notifications** | Platform Services | `notifications.manage` |
| **Search** | Platform Services | `search.use` **(+)** |
| **Audit** | Platform Services | `audit.view` **(+)** |
| **Recruitment** | Business Domain | `job.create`, `job.update`, `job.publish`, `job.archive`, `job.delete`, `candidate.view`, `candidate.note`, `candidate.tag`, `candidate.export`, `application.view`, `application.update`, `pipeline.manage`, `interview.schedule`, `interview.ai.run`, `offer.create`, `offer.send`, `employee.create`, `employee.update`, `talent_pool.manage` **(+)**, `template.manage` **(+)** |
| **AI Engine** | Intelligence | `ai.configure`, `ai.run`, `ai.keys.manage` |
| **Reports / Analytics** | Intelligence | `report.view`, `report.export` |
| **Workflow Engine** | Process | `workflow.manage` **(+)** |
| **Integration Platform** | Integration | `integration.manage` **(+)** |
| **Subscriptions** | Commerce | `subscription.view` **(+)** |
| **Billing** | Commerce | `billing.view`, `billing.manage` |
| **Licensing** | Commerce | — (enforces entitlements/limits; gates visibility, not authorization — see §5) |
| **Observability** | Operations | — (system-facing via `system.diagnostics.run`) |
| **System Administration** | Administration | `system.users.manage`, `system.workspaces.manage`, `system.subscriptions.manage`, `system.settings.manage`, `system.diagnostics.run`, `system.ai.manage`, `system.audit.view` |

> `interview.ai.run` is **declared by Recruitment** (it gates the recruitment
> action) and **uses** the AI Engine capability to execute. The AI Engine itself
> declares only `ai.*`. This avoids duplicating a key across two modules
> (see §6).

---

## 4. System vs Workspace Separation

HaHireAI is **two contexts, one product** (`SYSTEM_BLUEPRINT.md` §8):

| Scope | Held by | Visibility | Keys |
|---|---|---|---|
| **System** (`system.*`) | System Owners (a `User` granted system permissions) | **Platform Context only** | `system.users.manage`, `system.workspaces.manage`, `system.subscriptions.manage`, `system.settings.manage`, `system.diagnostics.run`, `system.ai.manage`, `system.audit.view` |
| **Workspace** | Members, via roles + direct grants | **Workspace Context**, additionally gated by subscription + enabled modules | every non-`system.*` key in §2 |

Rules (from `PERMISSION_MODEL.md` §6 and `DOMAIN_MODEL.md` §2):

1. **`system.*` keys are the only system-scoped keys.** Every other key in this
   matrix is workspace-scoped.
2. **System Owner is not a separate account.** It is a `User` holding
   system-level permissions; the same user may also be an ordinary member,
   candidate, or employee in any workspace.
3. **Workspace permissions never cross workspaces.** A grant in Workspace A has
   no effect in Workspace B.
4. **Context selects which keys are even visible.** Platform Context surfaces
   `system.*`; Workspace Context surfaces workspace keys for the active
   workspace. Navigation is generated per context (`NAVIGATION_MAP.md`,
   `SIDEBAR_MODEL.md`).

---

## 5. Notes on Visibility, Gating & Enforcement

- **Categories are display-only.** The *Category* column groups keys for the Role
  Builder UI and documentation. Categories **never** affect enforcement
  (`PERMISSION_MODEL.md` §2). Renaming or regrouping a category changes no
  authorization behavior.
- **Subscription and enabled-modules gate *visibility*, not *authorization*.** A
  member may hold `interview.ai.run`, but if the workspace's plan does not include
  the AI Engine, or the module is disabled, the action is **hidden / unavailable**
  — this is a Licensing/Subscriptions gate, *separate* from the permission check.
  Holding the permission is necessary but not sufficient; the gate is an
  additional precondition, never a replacement for the permission.
- **UI hiding ≠ enforcement.** Hiding a sidebar item or button (derived from
  permission checks + subscription + enabled modules) is a convenience only.
  **Every** action is still permission-checked **server-side** at the Application
  authorization boundary (`SYSTEM_BLUEPRINT.md` §2, `PERMISSION_MODEL.md` §5).
  A request that bypasses the UI is still denied without the key.
- **Read is a permission too.** `*.view` keys gate data reads. Deny-by-default
  applies equally to queries and commands.
- **No role names anywhere.** Checks resolve a member's **effective permission
  set** (union of role permissions + direct grants) and test for a key. No code
  path branches on a role's name.

---

## 6. Self-Review Checklist

Use this checklist to validate the matrix against canon before it advances toward
the Phase 4 catalog.

- [ ] **No hard-coded roles.** Every row is a *permission key*; no role names
      appear in any column, and no enforcement here implies a reserved role
      (`PERMISSION_MODEL.md` §1, `DOMAIN_MODEL.md` §6 invariant 5).
- [ ] **No duplicate permission.** Each key appears exactly once as an *owned*
      (declared) permission; cross-module use is recorded under *Using module(s)*,
      not as a second declaration (e.g. `interview.ai.run`).
- [ ] **No action without a permission.** Every state-changing or data-reading
      action maps to at least one key; deny-by-default leaves no action exempt
      (`PERMISSION_MODEL.md` §5).
- [ ] **Same user, different permissions per workspace.** Nothing in the matrix
      ties a key to a global role; effective permissions are resolved per
      `(User, Workspace)` membership (`DOMAIN_MODEL.md` §7, `WORKSPACE_MODEL.md`
      §5).
- [ ] **Grammar conformance.** Every key is lowercase `resource.action` (or
      `resource.subresource.action`); system keys use `system.area.action`.
- [ ] **Scope correctness.** Only `system.*` keys are System-scoped and
      Platform-Context-only; all others are Workspace-scoped.
- [ ] **Categories are display-only.** Removing the *Category* column would change
      no authorization outcome.
- [ ] **Additive growth.** Any new module/key is added via its manifest without
      changing the permission engine (`MODULES.md` §6, `SYSTEM_BLUEPRINT.md` §9).

---

### Related Documents

`PERMISSION_MODEL.md` · `MODULES.md` · `DOMAIN_MODEL.md` · `WORKSPACE_MODEL.md` ·
`SYSTEM_BLUEPRINT.md` · `NAVIGATION_MAP.md` · `SIDEBAR_MODEL.md` ·
`PERMISSION_CATALOG.md` (Phase 4) · `SYSTEM_PERMISSIONS.md` (Phase 4) ·
`WORKSPACE_PERMISSIONS.md` (Phase 4) · `ACCESS_POLICIES.md` (Phase 4) ·
`SECURITY_MATRIX.md` (Phase 4) · `ROLE_BUILDER.md` (Phase 4)
