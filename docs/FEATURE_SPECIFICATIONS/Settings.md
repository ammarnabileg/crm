# FEATURE SPEC — Settings

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Settings · **Layer:** Platform Services · **Implemented in:** Phase 9
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Settings module is the **shared registry** for configuration at two scopes:
**workspace** settings (independent per tenant) and **system** settings (global
defaults). It provides a single, typed, validated place to read and write
configuration such as timezone, language, currency, date format, security policy,
AI settings, recruitment settings, notification preferences, and storage policy
(`WORKSPACE_MODEL.md` §4). It is a **shared service** that exists once and is
consumed via its contract; modules MUST NOT re-implement settings storage
(`PROJECT_CONSTITUTION.md` §4, `MODULES.md` §4). System defaults apply only when a
workspace setting is absent (`WORKSPACE_MODEL.md` §4).

## 2. Scope

**In scope**
- A keyed settings registry with a declared schema, type, default, and scope
  (workspace or system) per setting.
- Read/resolve with fallback: workspace value → system default.
- Write/update of workspace settings by authorized members and system settings by
  System Owners.
- Setting **groups**: general (timezone, language, currency, date format),
  security, AI, recruitment, notifications, storage.
- Validation of values against each setting's declared type and constraints.

**Out of scope**
- Branding assets and workspace identity — owned by **Workspaces** (Settings MAY
  store related preferences but not the binary assets).
- Roles and permission definitions — owned by **Permissions**.
- The actual behavior the settings configure (e.g. AI execution, file retention
  enforcement) — owned by the respective modules, which *read* settings here.
- Subscription/plan limits — owned by **Subscriptions** (Phase 14); Settings does
  not enforce entitlements.

## 3. Inputs

- Setting-read requests (by key, for a given workspace or system scope) from any
  module.
- Setting-update commands from authorized members (workspace scope) and System
  Owners (system scope).
- Setting-definition registrations contributed by modules (declared keys, types,
  defaults, scope, group).

## 4. Outputs

- Resolved setting values with correct fallback semantics.
- The settings schema/catalog for presentation (settings screens) and validation.
- Domain events in §7 on change.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Workspaces** — to resolve the workspace scope / tenant context (consumed via
  contract).
- **Permissions** — for authorization of update actions.
- **Audit** (shared service) — to record every setting change (old → new).
- **Search** (shared service) — to index settings keys/labels for unified search.
- **Notifications** (shared service) — OPTIONAL, to notify on security-relevant
  setting changes.

Other modules depend on Settings (a Foundation/Platform-shared service); Settings
MUST NOT depend on business modules, preserving the DAG.

## 6. Permissions (keys this module declares; resource.action grammar)

- `settings.view` — view workspace settings.
- `settings.update` — update workspace settings.

System-scope settings are governed by a system permission (e.g.
`system.settings.manage`, declared in the system permission set) and are visible
only in the Platform Context (`PERMISSION_MODEL.md` §6). Reads of individual
settings by other modules are internal service calls, but any user-facing view is
gated by `settings.view`; deny by default.

## 7. Events (Published / Subscribed)

**Published** (`module.entity.event`, past tense)
- `settings.setting.updated`
- `settings.setting.reset` (reverted to system default)
- `settings.group.updated` (a batch update to a settings group)

**Subscribed**
- `workspaces.workspace.created` — provision default workspace settings for the
  new tenant.
- `workspaces.workspace.deleted` — mark the tenant's settings inactive alongside
  the soft-deleted workspace (no hard delete).

## 8. Data Owned (conceptual entities only — defer detail to DATABASE_ARCHITECTURE.md, Phase 3)

- **Setting** *(workspace-scoped and system-scoped)* — key, scope
  (`workspace_id` nullable for system scope), value, value type, group, timestamps.
- **Setting Definition** *(global catalog)* — declared key, type, default,
  allowed-values/constraints, group, owning module — feeds validation and the
  settings schema.

Workspace settings carry `workspace_id`; system settings are global. The registry
is additive: a new module registers its setting definitions without altering the
settings engine (`WORKSPACE_MODEL.md` §8 invariant 5).

## 9. Acceptance Criteria (checklist of testable outcomes)

- [ ] Reading a setting returns the workspace value when present, otherwise the
      system default; absence of both yields the declared default.
- [ ] Workspace settings are isolated per `workspace_id`; one workspace never
      reads or overrides another's settings.
- [ ] Updating a setting validates the value against its declared type/constraints
      and rejects invalid input.
- [ ] `settings.update` is required for workspace updates; system-scope updates
      require the system settings permission; deny by default, enforced server-side.
- [ ] Every change emits the appropriate event in §7 and is recorded in Audit with
      old and new values.
- [ ] A new module can register new setting definitions without code changes to
      the Settings engine.
- [ ] The defined setting groups (general, security, AI, recruitment,
      notifications, storage) are all representable.
- [ ] Settings depends only on shared/foundation modules and introduces no cycle.

### Related Documents
`WORKSPACE_MODEL.md` · `MODULES.md` · `ARCHITECTURE.md` · `PERMISSION_MODEL.md` ·
`DOMAIN_MODEL.md` · `DATABASE_GUIDE.md` · `FEATURE_SPECIFICATIONS/Workspaces.md` ·
`FEATURE_SPECIFICATIONS/Audit.md`
