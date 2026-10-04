# FEATURE SPEC — System Administration

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** System Administration · **Layer:** Administration · **Implemented in:** Phase 8–15
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

System Administration is the **Platform Context console** — the operating surface
for **System Owners** to run HaHireAI as a product across all tenants. Where the
Workspace Context is the daily work environment for one workspace, the Platform
Context is the platform-wide view: managing **workspaces**, **users**,
**subscriptions/plans**, **global AI providers**, **global settings**, and the
**platform audit trail** (`SYSTEM_BLUEPRINT.md` §8; `WORKSPACE_MODEL.md` §7).

A System Owner is **not a separate account type** — it is a `User` granted
**system-level permissions** (`DOMAIN_MODEL.md` §2). This module is therefore
governed **exclusively by `system.*` permissions**; it declares no workspace
permissions and grants no platform power to ordinary members. It is an
**aggregating console**: it manages platform-level concerns directly and delegates
into the specialist modules (Subscriptions, Licensing, AI Engine, Observability,
Settings, Audit) through their contracts — it never reaches into their internals or
tables.

## 2. Scope

**In scope**

- **Workspace administration** — list, inspect, suspend/reinstate, archive, and
  transfer ownership of any workspace (lifecycle actions defined in
  `STATE_DIAGRAMS.md` §10, executed via the Workspaces contract).
- **User administration** — inspect users, grant/revoke **system** permissions,
  and perform platform-level account actions (lock/unlock) via the Users and
  Permissions contracts.
- **Subscription & plan oversight** — author the global **Plan** catalog and
  override any workspace's subscription, via the Subscriptions/Licensing contracts.
- **Global AI providers** — manage global provider definitions and platform AI keys
  via the AI Engine (`system.ai.manage`).
- **Global settings** — manage system-level defaults in the shared Settings
  registry (workspace settings remain per workspace and are never overwritten
  silently).
- **Platform audit** — read the system-level audit trail via the Audit contract.
- **Platform Context navigation** — the System-Owner console surface, generated for
  the Platform Context from `system.*` permissions only.

**Out of scope**

- **Workspace business work** — no hiring, no per-workspace member work is done
  here; that is the Workspace Context.
- **Owning specialist data** — subscriptions, entitlements, AI config, telemetry,
  and audit records are owned by their modules; this console reads/commands them
  via contracts.
- **Operational telemetry/dashboards** — health, metrics, logs, backups, and
  alerts are **Observability's** surface (`system.diagnostics.*`); this console
  links to it but does not reimplement it.
- **Direct external connectivity** — any outbound integration goes through the
  Integration Platform.
- **Bypassing tenant isolation** — even a System Owner reaches workspace business
  data only through audited, permission-checked actions; this console is **not** a
  backdoor around the tenant guard.

## 3. Inputs

- **System Owner actions** — workspace lifecycle commands, user/system-permission
  grants, plan-catalog edits, subscription overrides, global-setting and
  global-AI-provider changes.
- **Platform queries** — cross-tenant listings and inspections (workspaces, users,
  subscriptions) presented read-only with pagination.
- **First System Owner bootstrap** — the initial System Owner established by the
  Installer (Phase 8) seeds this console's access.
- **Module signals** — platform-relevant events (e.g.
  `workspaces.workspace.created`, `subscriptions.subscription.suspended`) surfaced
  for situational awareness.

## 4. Outputs

- **Administrative commands** — issued to Workspaces, Users, Permissions,
  Subscriptions, Licensing, AI Engine, and Settings via their contracts.
- **Platform listings & detail views** — cross-tenant read views for System
  Owners (paginated, never unbounded).
- **Global configuration changes** — committed plan catalog, global settings, and
  global AI provider definitions.
- **Audit entries** — **every** administrative action (suspend workspace, grant
  `system.*`, change plan, edit global AI provider) is audited via the shared Audit
  service — who/when/where/what changed.
- **System-permission changes** — grants/revocations of `system.*` to users,
  recorded and audited.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Core Kernel** — container, router, configuration, Event Dispatcher, Logger.
- **Permissions** — declares and enforces `system.*`; gates every console action
  (deny-by-default, by permission key).
- **Workspaces / Memberships** — workspace lifecycle and ownership transfer; tenant
  administration.
- **Users** — platform-level user inspection and account actions.
- **Subscriptions / Licensing** — plan-catalog authoring and subscription/
  entitlement overrides (the commercial Platform-Context surface).
- **AI Engine** — global provider definitions and platform AI keys
  (`system.ai.manage`).
- **Settings** — global/system settings registry.
- **Audit** — reads the system-level audit trail and writes administrative-action
  audit entries.
- **Observability** — links to the operations console (`system.diagnostics.*`);
  System Administration does not duplicate monitoring.
- **Integration Platform** — any outbound connectivity is brokered here, never
  direct.

## 6. Permissions (keys this module declares)

System-scoped only (Platform Context; held by System Owners — `PERMISSION_MODEL.md`
§6):

- `system.workspaces.manage` — administer any workspace (suspend/reinstate/archive,
  ownership transfer).
- `system.users.manage` — platform-level user administration.
- `system.permissions.manage` — grant/revoke **system** permissions to users.
- `system.settings.manage` — manage global/system settings.
- `system.audit.view` — read the system-level audit trail.

This module **consumes** (does not redeclare) other system permissions surfaced in
its console: `system.subscriptions.manage` (Subscriptions/Billing/Licensing),
`system.ai.manage` (AI Engine), `system.integrations.manage` (Integration
Platform), and `system.diagnostics.run` / `system.diagnostics.manage`
(Observability). It declares **no workspace (`resource.action`) permissions.**

## 7. Events (Published / Subscribed)

**Published** (`system.<entity>.<event>`, past tense):

- `system.workspace.suspended`
- `system.workspace.reinstated`
- `system.systempermission.granted`
- `system.systempermission.revoked`
- `system.plan.published`
- `system.globalsetting.changed`

**Subscribed:**

- `workspaces.workspace.created` / `workspaces.workspace.archived` — platform
  situational awareness and lifecycle oversight.
- `subscriptions.subscription.suspended` / `.expired` — surface commercial state
  changes needing System-Owner attention.
- Authentication security signals (e.g. repeated lockouts) — surfaced for
  platform oversight (delivery/alerting itself is Observability + Integration
  Platform).

Events that concern a specific workspace carry `workspace_id`; platform-wide
actions are scoped to the Platform Context.

## 8. Data Owned (conceptual entities only — defer to DATABASE_ARCHITECTURE.md)

- **System Permission Grant** *(global)* — assignment of a `system.*` permission to
  a `User` (the mechanism that *makes* a System Owner).
- **Platform Setting (reference)** *(global)* — System Administration commands the
  shared Settings registry; it does not own a parallel store.
- **Administrative Action Record (reference)** *(system-level)* — administrative
  actions are persisted in the shared **Audit** trail, not a private log.

This module is deliberately **thin on owned data**: it is a console that commands
specialist modules through contracts. Plans, subscriptions, AI providers, settings,
telemetry, and audit records are owned elsewhere; identifiers are ULIDs and
global-vs-workspace scoping follows `ENTITY_CATALOG.md`.

## 9. Acceptance Criteria (testable checklist)

- [ ] The console is reachable only in the **Platform Context** with `system.*`
  permissions; it is invisible and inaccessible to ordinary members.
- [ ] A System Owner is a `User` with system permissions — no separate account type
  exists or is created.
- [ ] Every console action enforces the required `system.*` permission key
  (deny-by-default); the module declares **no** workspace permissions.
- [ ] Workspace lifecycle actions (suspend/reinstate/archive/transfer) follow
  `STATE_DIAGRAMS.md` §10 and run via the Workspaces contract — never direct table
  access.
- [ ] Plan-catalog authoring and subscription overrides go through Subscriptions/
  Licensing contracts; global AI providers/keys through the AI Engine; global
  settings through the shared Settings registry — no local logic.
- [ ] Granting/revoking `system.*` to a user needs no code change and is audited.
- [ ] **Every** administrative action is recorded in the shared Audit trail
  (who/when/where/what changed); the trail is readable with `system.audit.view`.
- [ ] The console does not bypass the tenant guard: workspace business data is
  reached only via audited, permission-checked administrative actions.
- [ ] Cross-tenant listings are paginated; no unbounded platform queries exist.
- [ ] Monitoring is delegated to Observability and outbound connectivity to the
  Integration Platform; the console reimplements neither and makes no direct calls.

### Related Documents

`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `MODULES.md` ·
`DOMAIN_MODEL.md` · `WORKSPACE_MODEL.md` · `PERMISSION_MODEL.md` ·
`SYSTEM_BLUEPRINT.md` · `STATE_DIAGRAMS.md` · `Subscriptions.md` · `Licensing.md` ·
`Observability.md` · `Integration_Platform.md` · `SYSTEM_PERMISSIONS.md` ·
`DATABASE_ARCHITECTURE.md`
