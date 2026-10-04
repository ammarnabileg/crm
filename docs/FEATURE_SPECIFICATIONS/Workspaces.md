# FEATURE SPEC — Workspaces

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Workspaces · **Layer:** Identity & Access · **Implemented in:** Phase 9
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Workspaces module owns the **tenant boundary** of HaHireAI. A `Workspace` is
the independent, isolated space in which all business data lives, and the unit of
settings, branding, membership, subscription, and enabled modules. This module is
the **isolation root**: it defines the `workspace_id` that every other
workspace-scoped module references for tenant guarding. A Workspace is **not** a
Company, a Role, or a User; company information, where present, is optional data
held in settings (`WORKSPACE_MODEL.md` §1, §8). The module governs the workspace
**lifecycle** — create, update, archive, restore, soft-delete, and ownership
transfer — and exposes the canonical identity of a workspace to the rest of the
platform via its `Contracts` surface.

## 2. Scope

**In scope**
- Workspace creation, including provisioning of the owner Membership, default
  Settings, and the owner's default permission grant (orchestrated with the
  Memberships, Settings, and Permissions modules).
- Workspace profile: name, slug, description, and identity metadata.
- Branding: logo, cover, colors, favicon, email branding references.
- Lifecycle state machine: `Active → Archived → Active` and `Active → Deleted`
  (soft, recoverable), per `STATE_DIAGRAMS.md` §10.
- Ownership transfer as an action on an `Active` workspace.
- Workspace context resolution (which workspace a request operates within).

**Out of scope**
- Membership records, invitations, and member status — owned by **Memberships**.
- The settings registry values and schema — owned by **Settings**.
- Roles, permission catalog, and authorization checks — owned by **Permissions**.
- Subscription, plan, and limits — owned by **Subscriptions** (Phase 14).
- File binary storage for branding assets — owned by **Files**.
- Recruitment data of any kind.

## 3. Inputs

- Workspace creation requests from an authenticated `User` (name, slug, optional
  branding, optional company metadata).
- Workspace update, archive, restore, and soft-delete commands from authorized
  members.
- Ownership-transfer commands naming the target member.
- Tenant-context resolution requests from the Core Kernel / routing layer.
- Slug-availability checks.

## 4. Outputs

- A persisted `Workspace` aggregate with a stable ULID identity and `workspace_id`
  for downstream tenancy.
- The resolved **Workspace Context** consumed by other modules' tenant guards.
- Branding and identity read models for navigation and presentation.
- Lifecycle-state transitions and the domain events in §7.
- Slug-availability results.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Users** — to resolve the creating/owning `User` identity (consumed via its
  contract).
- **Memberships** — to create the owner Membership at workspace creation and to
  reassign it on ownership transfer (consumed via its contract; Memberships
  depends on Workspaces, so this orchestration MUST avoid a cycle — see §9).
- **Permissions** — to grant the owner the default workspace permission set at
  creation (deny-by-default applies thereafter).
- **Settings** (shared service) — to provision default workspace settings.
- **Files** (shared service) — to store branding assets.
- **Audit** (shared service) — to record lifecycle and ownership actions.
- **Search** (shared service) — to index workspace identity for unified search.
- **Notifications** (shared service) — to notify the owner/members of lifecycle
  changes.

The orchestration of owner Membership + default Settings + owner permission grant
at creation **MUST** be transactional or saga-coordinated so a failure leaves no
partially provisioned tenant.

## 6. Permissions (keys this module declares; resource.action grammar)

- `workspace.create` — create a new workspace.
- `workspace.view` — view workspace profile, branding, and metadata.
- `workspace.update` — update workspace profile and identity metadata.
- `workspace.branding` — manage branding (logo, cover, colors, favicon, email).
- `workspace.archive` — archive an active workspace.
- `workspace.restore` — restore an archived (or soft-deleted, recoverable) workspace.
- `workspace.delete` — soft-delete a workspace.
- `workspace.transfer` — transfer workspace ownership to another member.

System Owners operate via the `system.workspaces.manage` system permission
(declared by Permissions / System Administration), which is distinct from these
workspace-scoped keys (`PERMISSION_MODEL.md` §6). Every action is permission-checked
server-side; deny by default.

## 7. Events (Published / Subscribed)

**Published** (`module.entity.event`, past tense)
- `workspaces.workspace.created`
- `workspaces.workspace.updated`
- `workspaces.workspace.branding_updated`
- `workspaces.workspace.archived`
- `workspaces.workspace.restored`
- `workspaces.workspace.deleted` (soft)
- `workspaces.workspace.ownership_transferred`

**Subscribed**
- None required for core operation. The module SHOULD remain a publisher so that
  Memberships, Notifications, Search, Audit, and (later) Subscriptions react via
  events rather than creating inbound dependencies (avoids cycles per
  `MODULES.md` §5).

## 8. Data Owned (conceptual entities only — defer detail to DATABASE_ARCHITECTURE.md, Phase 3)

- **Workspace** *(aggregate root, tenant root)* — identity, slug, profile, status
  (Active / Archived / Deleted), branding references, optional company metadata,
  owner reference, timestamps. Carries a ULID `id`; this `id` is the `workspace_id`
  used platform-wide.
- **Workspace Branding** — logo, cover, colors, favicon, email branding (modeled
  with the workspace or as an owned child; binary assets live in Files).

All other workspace-scoped tables belong to other modules and merely reference
`workspace_id`. Archiving uses a status/`archived_at` marker and MUST NOT set
`deleted_at`; soft-delete uses `deleted_at` and remains recoverable
(`DATABASE_GUIDE.md` §9; the two MUST NOT be conflated).

## 9. Acceptance Criteria (checklist of testable outcomes)

- [ ] Creating a workspace yields exactly one `Workspace` with a unique ULID `id`
      and a unique slug.
- [ ] Creation atomically provisions the owner Membership, default Settings, and
      the owner's default permission grant; a failure in any step leaves no
      partial tenant.
- [ ] No reserved/default **roles** are created at workspace creation
      (`WORKSPACE_MODEL.md` §6).
- [ ] Lifecycle transitions follow `STATE_DIAGRAMS.md` §10 exactly; any unlisted
      transition is rejected.
- [ ] Archive sets the archive marker only; soft-delete sets `deleted_at` only;
      neither hard-deletes data and both are recoverable via `workspace.restore`.
- [ ] Ownership transfer reassigns the owner Membership without changing workspace
      state and is permitted only on `Active` workspaces.
- [ ] Every listed action is denied without its required permission
      (deny-by-default), enforced server-side.
- [ ] Each lifecycle/ownership action emits its event in §7 and is recorded in the
      Audit trail.
- [ ] The module exposes the active `workspace_id` such that other modules' tenant
      guards can filter by it; no cross-workspace data is ever returned.
- [ ] The module depends on no module that depends on it (the dependency graph
      remains a DAG).

### Related Documents
`WORKSPACE_MODEL.md` · `DOMAIN_MODEL.md` · `MODULES.md` · `ARCHITECTURE.md` ·
`PERMISSION_MODEL.md` · `STATE_DIAGRAMS.md` · `DATABASE_GUIDE.md` ·
`FEATURE_SPECIFICATIONS/Memberships.md` · `FEATURE_SPECIFICATIONS/Settings.md`
