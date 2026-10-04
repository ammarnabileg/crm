# WORKSPACE PERMISSIONS — HaHireAI

> **Status:** Adopted (Phase 4) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PERMISSION_CATALOG.md` §2, `PERMISSION_MODEL.md`.

---

## 1. Overview

Workspace permissions are the non-`system.*` keys in the authoritative registry
(`PERMISSION_CATALOG.md` §2). They authorize action **inside exactly one
workspace** — the isolated tenant boundary defined in `WORKSPACE_MODEL.md` §1 —
and **never** reach across workspaces (`PERMISSION_MODEL.md` §6).

Core rules inherited from canon:

- **Deny-by-default.** Every state-changing *and* every data-reading action
  requires an explicit permission; absence ⇒ denied
  (`PERMISSION_MODEL.md` §5). Read actions check the corresponding `*.view` key.
- **Keys, not roles.** Authorization checks reference permission **keys** only;
  no code path **MAY** branch on a role name (`PERMISSION_MODEL.md` §1).
- **Roles are bundles of these keys.** A `Role` is workspace-scoped data created
  by users; effective permissions for a `Membership` = union of granted roles'
  permissions plus any direct grants (`PERMISSION_MODEL.md` §3–§4).
- **Same key, evaluated per workspace.** Holding a key in one workspace has **no**
  effect in another (`WORKSPACE_MODEL.md` §8, invariant 1).

This document mirrors the catalog's category grouping (display-only,
`PERMISSION_MODEL.md` §2) and adds, per key, **what it allows** and its
**dependencies**. The catalog remains authoritative for the exact key set; this
document **MUST NOT** introduce keys absent from `PERMISSION_CATALOG.md` §2.

---

## 2. Baseline Capabilities — No Permission Required

Per `PERMISSION_CATALOG.md` §1, some actions are available to **any authenticated
`User`** and are therefore **not** gated by any workspace permission. They are not
roles, not grants, and not displayable in the role builder:

| Capability | Notes |
|---|---|
| `workspace.create` | Any user MAY create a workspace; they become its owner. |
| `workspace.join` | Any user MAY accept an invitation to join a workspace. |
| apply to a public job | Any user MAY apply, creating an `Application`. |
| manage own profile / sessions | A user always manages their own identity. |

> These are platform baselines, not tenant grants. `workspace.create` and
> `workspace.join` are **capabilities of being a `User`**, distinct from the
> `workspace.*` permission family in §3.1, which governs an **existing**
> workspace's administration. **Everything else** requires an explicit permission
> from §3.

---

## 3. Workspace Permission Catalog (mirrors `PERMISSION_CATALOG.md` §2)

Categories below are **display-only** and never affect enforcement
(`PERMISSION_MODEL.md` §2). "Dependencies" expresses the implication rule of
`PERMISSION_CATALOG.md` Rule 1: a meaningful action on a resource presumes the
ability to **view** that resource, so an action key is only useful alongside the
matching `*.view` key. Authoritative dependency mappings live in
`ACCESS_POLICIES.md`; the entries here are the normative implications.

### 3.1 Workspace

| Key | What it allows | Dependencies |
|---|---|---|
| `workspace.view` | View the workspace overview. | — (entry point for all workspace surfaces) |
| `workspace.update` | Edit workspace core fields. | `workspace.view` |
| `workspace.settings` | Manage workspace settings. | `workspace.view`; relates to `settings.view`/`settings.update` |
| `workspace.branding` | Manage logo, colors, and branding. | `workspace.view` |
| `workspace.archive` | Archive the workspace. | `workspace.view` |
| `workspace.restore` | Restore an archived workspace. | `workspace.view` |
| `workspace.delete` | Soft-delete the workspace. | `workspace.view` |
| `workspace.transfer` | Transfer ownership. | `workspace.view`; audited (`AUDIT_EVENTS.md`) |

### 3.2 Members

| Key | What it allows | Dependencies |
|---|---|---|
| `member.view` | View members. | — |
| `member.invite` | Invite members. | `member.view` |
| `member.invite.resend` | Resend invitations. | `member.view`, `member.invite` |
| `member.invite.cancel` | Cancel invitations. | `member.view`, `member.invite` |
| `member.update` | Update a member (roles/status). | `member.view`; assigning roles also needs `role.view` + `permission.assign` |
| `member.suspend` | Suspend a member. | `member.view` |
| `member.reactivate` | Reactivate a member. | `member.view` |
| `member.remove` | Remove a member. | `member.view` |

### 3.3 Roles & Permissions

| Key | What it allows | Dependencies |
|---|---|---|
| `role.view` | View roles. | — |
| `role.create` | Create a role. | `role.view` |
| `role.update` | Rename/edit a role. | `role.view` |
| `role.clone` | Clone a role. | `role.view`, `role.create` |
| `role.delete` | Delete a role. | `role.view` |
| `permission.assign` | Assign permissions to roles / members. | `role.view`; assigning to members also needs `member.view`. All actions audited (`PERMISSION_MODEL.md` §7) |

### 3.4 Jobs

| Key | What it allows | Dependencies |
|---|---|---|
| `job.view` | View jobs. | — |
| `job.create` | Create a job. | `job.view` |
| `job.update` | Edit a job. | `job.view` |
| `job.publish` | Publish a job. | **`job.view`** (publishing a job implies viewing it) |
| `job.pause` | Pause a published job. | `job.view`, `job.publish` |
| `job.close` | Close a job. | `job.view` |
| `job.archive` | Archive a job. | `job.view` |
| `job.delete` | Soft-delete a job. | `job.view` |
| `job.clone` | Duplicate a job. | `job.view`, `job.create` |
| `job.share` | Manage public link / share. | `job.view`; typically paired with `job.publish` |

### 3.5 Candidates

| Key | What it allows | Dependencies |
|---|---|---|
| `candidate.view` | View candidate profiles (workspace view). | — |
| `candidate.note` | Add/view notes. | `candidate.view` |
| `candidate.tag` | Apply tags. | `candidate.view` |
| `candidate.tag.manage` | Create/manage tag definitions. | `candidate.view`, `candidate.tag` |
| `candidate.rate` | Add ratings/scorecards. | `candidate.view` |
| `candidate.export` | Export candidate data. | `candidate.view`; high-impact, audited |

### 3.6 Applications & Pipeline

| Key | What it allows | Dependencies |
|---|---|---|
| `application.view` | View applications. | `candidate.view` (an application references a candidate) |
| `application.update` | Update an application. | `application.view` |
| `application.reject` | Reject an application. | `application.view` |
| `application.withdraw` | Record a withdrawal. | `application.view` |
| `pipeline.view` | View the pipeline. | `application.view` |
| `pipeline.manage` | Move stages, edit stages, bulk actions. | **`pipeline.view`** and **`application.view`** (managing the pipeline implies viewing applications) |

### 3.7 Interviews

| Key | What it allows | Dependencies |
|---|---|---|
| `interview.view` | View interviews. | `application.view` |
| `interview.schedule` | Schedule interviews. | `interview.view` |
| `interview.ai.run` | Run an AI interview (capability). | `interview.view`; requires the AI Engine (`ai.run` capability) and the AI module enabled (§4) |
| `interview.evaluate` | Submit evaluations/scorecards. | `interview.view` |
| `interview.cancel` | Cancel interviews. | `interview.view`, `interview.schedule` |

### 3.8 Offers & Employees

| Key | What it allows | Dependencies |
|---|---|---|
| `offer.view` | View offers. | — |
| `offer.create` | Create an offer. | `offer.view` |
| `offer.update` | Edit a draft offer. | `offer.view` |
| `offer.send` | Send an offer. | `offer.view`, `offer.create` |
| `offer.revoke` | Revoke an offer. | `offer.view`, `offer.send` |
| `employee.view` | View employees. | — |
| `employee.create` | Create employee (on hire). | `employee.view`; typically follows `offer.send` acceptance |
| `employee.update` | Update an employee. | `employee.view` |

### 3.9 Talent Pool & Templates

| Key | What it allows | Dependencies |
|---|---|---|
| `talent.view` | View the talent pool. | — |
| `talent.manage` | Manage talent pool / smart lists. | `talent.view` |
| `template.view` | View templates. | — |
| `template.manage` | Manage templates. | `template.view` |

### 3.10 Platform Services

| Key | What it allows | Dependencies |
|---|---|---|
| `report.view` | View reports/analytics. | — |
| `report.export` | Export reports. | `report.view` |
| `files.view` | View files. | — |
| `files.upload` | Upload files. | `files.view` |
| `files.download` | Download files. | `files.view` |
| `files.delete` | Delete files. | `files.view` |
| `files.manage` | Manage folders/visibility. | `files.view` |
| `notifications.view` | View notifications. | — |
| `notifications.manage` | Manage preferences / mark / clear. | `notifications.view` |
| `search.use` | Use unified search. | — (results are still filtered by the viewer's per-resource `*.view` keys) |
| `audit.view` | View the workspace audit log. | — |
| `audit.export` | Export the workspace audit log. | `audit.view` |
| `settings.view` | View settings. | — |
| `settings.update` | Update settings. | `settings.view` |

### 3.11 AI (workspace)

| Key | What it allows | Dependencies |
|---|---|---|
| `ai.view` | View AI settings/usage. | — |
| `ai.run` | Invoke AI capabilities. | `ai.view` |
| `ai.configure` | Configure provider/model/limits. | `ai.view` |
| `ai.keys.manage` | Manage workspace API keys. | `ai.view`, `ai.configure` |
| `ai.prompts.manage` | Manage workspace prompts. | `ai.view` |

### 3.12 Commerce (workspace)

| Key | What it allows | Dependencies |
|---|---|---|
| `billing.view` | View billing/invoices/usage. | — |
| `billing.manage` | Manage plan/payment methods. | `billing.view` |

### 3.13 Integration (workspace)

| Key | What it allows | Dependencies |
|---|---|---|
| `integration.view` | View integrations. | — |
| `integration.manage` | Connect/configure integrations. | `integration.view` |
| `apikey.manage` | Create/rotate/revoke API keys. | `integration.view`; high-impact, audited |
| `webhook.manage` | Manage webhooks. | `integration.view` |

### 3.14 Workflow (workspace)

| Key | What it allows | Dependencies |
|---|---|---|
| `workflow.view` | View workflows/executions. | — |
| `workflow.create` | Create a workflow. | `workflow.view` |
| `workflow.update` | Edit a workflow. | `workflow.view` |
| `workflow.delete` | Delete a workflow. | `workflow.view` |
| `workflow.publish` | Publish a workflow. | `workflow.view`, `workflow.create` |
| `workflow.execute` | Manually trigger a workflow. | `workflow.view` |
| `workflow.approve` | Act on approval steps. | `workflow.view` |

---

## 4. Visibility Gating ≠ Authorization

A permission may be **granted yet not visible**. Per `PERMISSION_CATALOG.md`
Rule 3 and `PERMISSION_MODEL.md` §5–§6, workspace keys are gated **for
visibility** by the workspace's **subscription** and its **enabled modules** —
which is **separate** from authorization:

- **Authorization** is the deny-by-default permission check that runs
  **server-side** on every action. It is decisive.
- **Visibility** decides whether a sidebar item, button, or role-builder option
  is **shown**, derived from the same permission checks **plus** subscription and
  enabled modules (`PERMISSION_MODEL.md` §5; `SIDEBAR_MODEL.md`).

Consequences (MUST):

1. If a module is disabled or the subscription excludes a feature, its keys
   **MUST NOT** be offered in the UI for that workspace — even to the owner — and
   the corresponding actions are unavailable in that workspace.
2. **Hiding UI is never a substitute for server-side enforcement**
   (`PERMISSION_MODEL.md` §5). A hidden-but-granted action that is still reachable
   (e.g. via API) is authorized **only** if the permission check passes.
3. Visibility is **per workspace**: subscription and enabled modules are
   per-workspace (`WORKSPACE_MODEL.md` §8, invariant 4), so the same key may be
   visible in one workspace and hidden in another for the same user.

> Example: a `User` granted `interview.ai.run` in a workspace whose plan does not
> include the AI Engine will not see the AI-interview action there; the capability
> is inert for that tenant until the module is enabled. The grant itself is
> unchanged and may be active in another tenant.

---

## 5. The Same User, Different Permissions Per Workspace

A single `User` (`USER_MODEL.md` §1) participates in many workspaces and **MAY**
hold **different** roles and permissions in each, simultaneously
(`PERMISSION_MODEL.md` §4; `WORKSPACE_MODEL.md` §7; `USER_MODEL.md` §4):

- Effective permissions are computed **per `Membership`** — the union of that
  membership's granted roles plus its direct grants — and apply **only** within
  that workspace.
- The contexts "recruiter," "interviewer," "candidate," "employee," and
  "workspace owner" are **not accounts**; they are the consequence of the
  permissions on a given `Membership` (`USER_MODEL.md` §4). The same person can be
  an owner in Workspace A, an interviewer in Workspace B, and a candidate in
  Workspace C.
- A grant in one workspace has **no** effect in another
  (`PERMISSION_MODEL.md` §6; `WORKSPACE_MODEL.md` §8, invariant 1). There is no
  global workspace permission.

---

## 6. The Owner Is Granted, Not Reserved

The workspace **owner** receives a **full set of workspace permissions at
creation, via direct grant — not via a reserved "Owner" role**
(`PERMISSION_MODEL.md` §3–§4; `WORKSPACE_MODEL.md` §6). Key consequences:

- The product ships **zero default roles** and **no reserved/system roles**
  (`PERMISSION_MODEL.md` §3). Creating a workspace creates the **owner
  `Membership`**, **default settings**, and the owner's **direct permission
  grants** — but **no roles** (`WORKSPACE_MODEL.md` §6).
- Because the owner holds keys by **direct grant**, no code branches on
  "owner" — checks remain key-based (`PERMISSION_MODEL.md` §1, invariant 1). An
  owner can therefore **build any role and grant any workspace permission without
  a code change** (`PERMISSION_MODEL.md` §8, invariant 3).
- **Ownership transfer** reassigns the owner `Membership`
  (`WORKSPACE_MODEL.md` §6) and is an audited, permission-checked action
  (`workspace.transfer`; `PERMISSION_MODEL.md` §7).
- An owner's keys are still **workspace-scoped**: they confer nothing in any other
  workspace and nothing at the platform level (`SYSTEM_PERMISSIONS.md` §4).

> Convenience **templates** MAY be offered at role-creation time, but a template
> produces an ordinary, editable role that is **not special in code**
> (`PERMISSION_MODEL.md` §3). See `ROLE_BUILDER.md`.

---

### Related Documents

`PERMISSION_CATALOG.md` · `PERMISSION_MODEL.md` · `SYSTEM_PERMISSIONS.md` ·
`WORKSPACE_MODEL.md` · `USER_MODEL.md` · `MODULES.md` · `ROLE_BUILDER.md` ·
`ACCESS_POLICIES.md` · `SECURITY_MATRIX.md` · `AUDIT_EVENTS.md` ·
`SIDEBAR_MODEL.md`
