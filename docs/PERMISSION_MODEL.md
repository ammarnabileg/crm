# PERMISSION MODEL — HaHireAI

> **Status:** Adopted (Canon) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md`, `DOMAIN_MODEL.md`.
> **Companion catalogs:** `PERMISSION_CATALOG.md`, `SYSTEM_PERMISSIONS.md`,
> `WORKSPACE_PERMISSIONS.md`, `SECURITY_MATRIX.md` (Phase 4).

---

## 1. The Foundational Rule

Authorization is built on **permissions**, never on roles. A **Role** is *only*
a named bundle of permissions. The system **MUST NOT** contain hard-coded roles
and **MUST NOT** branch on a role's name anywhere in code.

```
Permissions  →  Roles  →  Members  →  Workspace
   (atoms)      (bundles)  (assignment)  (scope)
```

## 2. Permissions (the atoms)

- A **Permission** is a fine-grained capability identified by a stable key.
- **Grammar:** `resource.action` (lowercase, dot-separated), optionally
  `resource.subresource.action` — e.g. `job.create`, `candidate.export`,
  `interview.ai.run`.
- **System permissions** use the `system.` prefix — e.g.
  `system.workspaces.manage` (see `SYSTEM_PERMISSIONS.md`).
- Permissions are **declared by each module** in its manifest and aggregated into
  a global **Permission Catalog**. The catalog is the single registry of every
  permission that exists (see `PERMISSION_CATALOG.md`).
- Permissions are grouped into **categories** (Workspace, Members, Roles,
  Recruitment, Jobs, Candidates, Applications, Pipeline, Interviews, Offers,
  Reports, Files, Billing, Settings, AI, …) **for display only**. Categories
  never affect enforcement.

## 3. Roles (the bundles)

- A **Role** belongs to exactly one workspace and is **data**, created by users.
- The workspace owner (or any member with `role.*` permissions) can: **create,
  rename, clone, update, delete** roles and **assign permissions** to them.
- There are **no reserved/system roles**. The product ships **zero** default
  roles. (Convenience *templates* MAY be offered at creation time, but a template
  produces an ordinary editable role — it is not special in code.)
- Detailed behavior: `ROLE_BUILDER.md` (Phase 4).

## 4. Assignment

- A **Membership** is granted zero or more roles. Effective permissions =
  union of all granted roles' permissions (plus any direct grants, if enabled).
- The **same user** can have **different roles/permissions in different
  workspaces** — this is required and expected.
- The **workspace owner** receives a full set of workspace permissions at
  creation, via direct grant — not via a reserved "Owner" role.

## 5. Enforcement (Access Policy)

- **Deny by default.** Every action requires an explicit permission; absence ⇒
  denied.
- **Every** state-changing or data-reading action passes a **permission check**
  before executing. No action is exempt (read actions check view permissions).
- Checks reference **permission keys only** — never role names.
- For each protected action, the documentation records: **Purpose, Required
  Permission, Dependencies, Denied Behaviour** (see `ACCESS_POLICIES.md`).
- UI visibility (sidebar items, buttons) is derived from the same permission
  checks plus subscription/enabled-modules — see `SIDEBAR_MODEL.md`. Hiding UI is
  never a substitute for server-side enforcement.

## 6. System vs Workspace Permissions

| Scope | Held by | Examples | Visibility |
|---|---|---|---|
| **System** (`system.*`) | System Owners | `system.workspaces.manage`, `system.subscriptions.manage`, `system.diagnostics.run`, `system.ai.manage`, `system.audit.view` | Platform Context only |
| **Workspace** | Members via roles/grants | `job.create`, `member.invite`, `interview.ai.run`, `billing.manage` | Workspace Context, gated by subscription + enabled modules |

Workspace permissions in one workspace **never** affect another workspace.

## 7. Auditing

Permission- and role-affecting actions are audited (who/when/where/what changed):
role created/updated/deleted, permissions assigned/revoked, member role changed,
ownership transferred. Full list: `AUDIT_EVENTS.md` (Phase 4).

## 8. Invariants

1. No hard-coded roles; no code path depends on a role's name.
2. Deny-by-default; every action is permission-checked server-side.
3. A workspace owner can build any role and grant any workspace permission
   without a code change.
4. The same user can hold different permissions per workspace.
5. Adding a module adds its permissions to the catalog without altering the
   permission engine.
6. New roles required by a customer are created by the customer — never by
   editing code.

---

### Related Documents
`PERMISSION_CATALOG.md` · `SYSTEM_PERMISSIONS.md` · `WORKSPACE_PERMISSIONS.md` ·
`ROLE_BUILDER.md` · `ACCESS_POLICIES.md` · `SECURITY_MATRIX.md` ·
`SECURITY_GUIDE.md`
