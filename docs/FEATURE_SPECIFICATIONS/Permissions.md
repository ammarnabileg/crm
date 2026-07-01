# FEATURE SPEC — Permissions

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Permissions · **Layer:** Identity & Access · **Implemented in:** Phase 8
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Permissions module is the **authorization engine** of HaHireAI. It maintains
the global **Permission Catalog**, lets workspaces define **roles as data**
(never hard-coded), assigns roles to **memberships**, and answers **deny-by-default**
authorization checks for every protected action (`PERMISSION_MODEL.md`;
Constitution §3.4, §15). Authorization is built on fine-grained **permissions**;
a **Role** is *only* a named bundle of permissions, and no code path branches on a
role's name (`PERMISSION_MODEL.md` §1). This module decides *what an identity may
do*; it does not prove identity (**Authentication**) or own the person record
(**Users**).

## 2. Scope

**In scope**
- The **Permission Catalog / Registry:** aggregate the `resource.action` and `system.<area>.<action>` keys declared by every module's manifest into one authoritative registry.
- **Roles as data:** workspace-scoped, user-created bundles of permission keys; **zero** reserved/default roles ship.
- **Role builder:** create, clone, rename, update, delete roles and assign permissions to them.
- **Assignment:** grant zero-or-more roles to a `Membership`; support direct permission grants where enabled.
- **Authorization checks:** evaluate effective permissions (union of granted roles' permissions + direct grants) for an action, deny-by-default.
- **Convenience templates** at role-creation time that produce **ordinary, editable** roles (not special in code).
- Display-only **categories** for grouping permissions (never affecting enforcement).

**Out of scope**
- **Identity proving** (login/sessions/MFA) — **Authentication**; **profile/account status** — **Users**.
- **Membership lifecycle** (invite/accept/suspend) — the **Memberships** module (Phase 9); this module assigns roles *to* memberships.
- **Workspace lifecycle and the owner's initial grant mechanics** — **Workspaces** (Phase 9) creates the owner Membership and triggers the owner's direct full grant (no "Owner" role).
- **Subscription/enabled-module gating** of UI — layered on top of permission checks (`PERMISSION_MODEL.md` §5), owned by Licensing/Navigation.
- Hard-coding any role or branching on a role name — **forbidden** (Constitution §15).

## 3. Inputs

- Permission-key declarations from every module's `module.php` manifest (feed the catalog).
- Role definitions and edits (create/clone/rename/update/delete; permission assignment) from authorized members.
- Role-to-membership assignment requests and optional direct grants.
- Authorization-check requests from the Application boundary of every protected action across all modules.
- The active **Workspace Context** (for workspace-scoped roles) and the `User`'s system-permission set (for `system.*`).

## 4. Outputs

- The authoritative, queryable **Permission Catalog**.
- Persisted **Role** definitions and their permission assignments (workspace-scoped data).
- Role↔Membership assignments and any direct grants.
- An **allow/deny decision** for each requested `(identity, permission, scope)` — deny-by-default.
- A resolved **effective-permissions** set for a membership (for UI visibility and batch checks).
- The audit-relevant events in §7 (consumed by the **Audit** service).

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Core Kernel** (Foundation) — container, routing, configuration, logger, events; consumes module manifests via the Module Registry to build the catalog.
- **Database** (Foundation) — persistence; roles/assignments are **workspace-scoped**, the catalog is **global** (`DATABASE_GUIDE.md` §6.3).
- **Users** (Identity & Access) — resolves the acting `User` and its `system.*` capability.
- Per `MODULES.md` §5, **Permissions is depended upon by all protected actions**; it is consumed via its **contract** by every module's Application boundary. **Memberships** depends on Permissions (assignment); Permissions does not depend on Memberships' internals (no cycle).
- **Audit** (Platform Services) — receives permission/role events (subscribes; no inbound dependency, avoiding a cycle).

## 6. Permissions (keys this module declares; resource.action grammar)

Workspace-scoped role-management keys plus system-level catalog oversight:

- `role.view` — view roles in the workspace.
- `role.create` — create a role.
- `role.update` — rename/update a role and assign permissions to it.
- `role.clone` — clone an existing role.
- `role.delete` — delete a role.
- `role.assign` — assign/unassign roles (and direct grants) to a membership.
- `permission.view` — view the permission catalog (for the role builder).
- `system.permissions.manage` — manage the global catalog and system-level grants (Platform Context).

> All keys follow `resource.action` / `system.<area>.<action>` grammar
> (Constitution §7). Enforcement references **keys only**, never role names
> (`PERMISSION_MODEL.md` §5).

## 7. Events (Published / Subscribed)

**Published**
- `permissions.role.created` — a role was created.
- `permissions.role.updated` — a role was renamed/updated or its permissions changed.
- `permissions.role.cloned` — a role was cloned.
- `permissions.role.deleted` — a role was deleted.
- `permissions.role.assigned` / `permissions.role.unassigned` — a role was (un)assigned to a membership.
- `permissions.grant.added` / `permissions.grant.revoked` — a direct permission grant changed.
- `permissions.catalog.synced` — the catalog was (re)aggregated from module manifests.

**Subscribed**
- `workspaces.workspace.created` — provision the owner's **direct full grant** of workspace permissions (no reserved "Owner" role).
- `memberships.membership.removed` — clean up that membership's role assignments and grants.

## 8. Data Owned (conceptual entities only — defer detail to DATABASE_ARCHITECTURE.md, Phase 3)

- **Permission** *(global catalog)* — the static registry of capability keys, grouped into display-only categories.
- **Role** *(root, workspace-scoped)* — a named, user-created bundle of permission keys belonging to exactly one workspace.
- **Role↔Permission Assignment** *(workspace-scoped)* — which permission keys a role bundles.
- **Membership↔Role Assignment** *(workspace-scoped)* — which roles a membership holds.
- **Direct Grant** *(workspace-scoped, where enabled)* — a permission granted to a membership outside any role (e.g. the owner's initial full grant).

The **Membership** entity itself is owned by the **Memberships** module; this
module references it and attaches role assignments/grants to it.

## 9. Acceptance Criteria (checklist of testable outcomes)

- [ ] Authorization is **deny-by-default**: every state-changing or data-reading action requires an explicit permission; absence ⇒ denied (`PERMISSION_MODEL.md` §5).
- [ ] Every protected action passes a **server-side** permission check at the Application boundary; hiding UI is never a substitute (`PERMISSION_MODEL.md` §5).
- [ ] Checks reference **permission keys only**; **no** code path branches on a role's name (Invariant 1; Constitution §15).
- [ ] The product ships **zero** default/reserved roles; convenience templates create **ordinary editable** roles (`PERMISSION_MODEL.md` §3).
- [ ] An authorized member can **create, clone, rename, update, delete** roles and **assign permissions** to them without any code change (Invariant 3).
- [ ] Effective permissions for a membership = **union** of all granted roles' permissions **plus** any direct grants (`PERMISSION_MODEL.md` §4).
- [ ] The **same user** can hold **different** roles/permissions in **different** workspaces (Invariant 4).
- [ ] The **workspace owner** receives a full set of workspace permissions via **direct grant** on workspace creation — **not** via a reserved "Owner" role (`PERMISSION_MODEL.md` §4; §7 subscription).
- [ ] The **Permission Catalog** aggregates keys from **every** module's manifest; adding a module adds its permissions **without altering the engine** (Invariant 5).
- [ ] Keys conform to `resource.action` / `system.<area>.<action>` grammar; `system.*` keys are visible only in the **Platform Context** (`PERMISSION_MODEL.md` §6).
- [ ] Workspace permissions in one workspace **never** affect another workspace (`PERMISSION_MODEL.md` §6).
- [ ] Role- and permission-affecting actions emit events that the **Audit** service records (who/when/where/what — `PERMISSION_MODEL.md` §7).

### Related Documents
`PERMISSION_MODEL.md` · `PERMISSION_CATALOG.md` · `SYSTEM_PERMISSIONS.md` ·
`WORKSPACE_PERMISSIONS.md` · `ROLE_BUILDER.md` · `ACCESS_POLICIES.md` ·
`DOMAIN_MODEL.md` · `ARCHITECTURE.md` · `MODULES.md` · `Users.md` · `Authentication.md`
