# FEATURE SPEC — Audit

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Audit · **Layer:** Platform Services · **Implemented in:** Phase 9
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Audit module provides the **immutable activity and audit trail** of HaHireAI —
the authoritative record of **who did what, when, where, and what changed**. It
operates at two scopes: **per workspace** (workspace activity) and **system-level**
(platform/System Owner actions), per `DOMAIN_MODEL.md` §4.4 and §3 ("Audit Log").
It is a cross-cutting **shared service** that exists once and is consumed via its
contract and via events; modules MUST NOT hand-roll auditing
(`PROJECT_CONSTITUTION.md` §4, `ARCHITECTURE.md` §8, `MODULES.md` §4). The trail is
**append-only and immutable**: entries are never updated or deleted in the course
of business, supporting the Constitution's principle of evidence and auditability
(`PROJECT_CONSTITUTION.md` §3.9).

## 2. Scope

**In scope**
- Recording audit entries for significant actions: the actor (`User` / System
  Owner), timestamp, workspace context (or system scope), action, target entity,
  and a before/after change summary where applicable.
- Capturing request context: source (IP/agent) where available, correlation id.
- Workspace-scoped and system-scoped trails.
- Querying/filtering the trail (by actor, entity, action, time range), paginated.
- Ingesting audit entries via the contract and via subscribed platform events.

**Out of scope**
- Application/diagnostic logging and metrics — owned by **Observability**
  (Phase 15); audit is the *business* record, not the technical log.
- User-facing notifications — owned by **Notifications**.
- Search indexing of audit entries — MAY be provided later via **Search**; not in
  this module's core scope.
- Retention/export tooling beyond recording — governed by policy
  (`DATABASE_GUIDE.md` retention) and Observability; the audit record itself is
  immutable.

## 3. Inputs

- Audit-record requests from any module via the contract (actor, action, target,
  before/after, scope).
- Platform events the module subscribes to, mapped to audit entries.
- Request-context metadata (actor, IP, agent, correlation id) from the Core Kernel.
- Audit-query requests from authorized members / System Owners.

## 4. Outputs

- Persisted, immutable `Audit Log` entries (workspace-scoped or system-scoped).
- Filtered, paginated audit read models for presentation and compliance.
- No mutation/deletion APIs for existing entries (append-only).

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Users** — to resolve the acting `User` / System Owner (contract).
- **Workspaces** — to attach workspace context / `workspace_id` (contract).
- **Permissions** — for authorization of audit-view actions.
- **Event Bus** (shared) — to subscribe to auditable platform events.
- **Core Kernel** — for request context (actor, source, correlation id).

Audit is consumed by every module yet depends only on shared/foundation modules;
it MUST NOT depend on business modules. Modules emit auditable facts via the
contract or events, so no inbound dependency or cycle is created
(`MODULES.md` §5).

## 6. Permissions (keys this module declares; resource.action grammar)

- `audit.view` — view the workspace audit trail.
- `audit.export` — export the workspace audit trail (where permitted by policy).

The system-level trail is governed by the system permission `system.audit.view`
(declared in the system permission set) and is visible only in the Platform
Context (`PERMISSION_MODEL.md` §6). There is **no** permission to edit or delete
audit entries — immutability is a design invariant, not a permission. Deny by
default; enforcement is server-side.

## 7. Events (Published / Subscribed)

**Published** (`module.entity.event`, past tense)
- `audit.entry.recorded`

(The Audit module is primarily a sink; it publishes minimally to avoid feedback
loops. `audit.entry.recorded` MAY be consumed by Observability/Reports.)

**Subscribed** (illustrative — Audit records auditable facts from across the
platform)
- `workspaces.workspace.created` / `workspaces.workspace.archived` /
  `workspaces.workspace.deleted` / `workspaces.workspace.ownership_transferred`
- `memberships.membership.roles_changed` / `memberships.membership.removed` /
  `memberships.invitation.created`
- `settings.setting.updated`
- `files.file.uploaded` / `files.file.deleted`
- permission/role lifecycle events from **Permissions** (role created/updated/
  deleted, permissions assigned/revoked — `PERMISSION_MODEL.md` §7)

The subscription set is additive: a new module's auditable events are recorded
without changing the Audit engine.

## 8. Data Owned (conceptual entities only — defer detail to DATABASE_ARCHITECTURE.md, Phase 3)

- **Audit Log Entry** *(workspace-scoped and system-scoped)* — `workspace_id`
  (nullable for system scope), actor `user_id`, action key, target entity type +
  reference, before/after change summary, source (IP/agent), correlation id,
  recorded-at. Entries are **append-only**: no `updated_at` mutation of recorded
  facts and **no `deleted_at`** soft-delete of audit history in normal operation.

Audit owns only its trail tables. It stores references to subject entities (by
ULID) plus a change summary; it does not duplicate authoritative records
(`DATABASE_GUIDE.md`).

## 9. Acceptance Criteria (checklist of testable outcomes)

- [ ] Every recorded entry captures actor, timestamp, scope (workspace or system),
      action, target, and a before/after summary where applicable.
- [ ] Workspace entries carry `workspace_id` and are isolated per workspace;
      system entries are scoped to the Platform Context.
- [ ] Entries are immutable: no API updates or deletes existing audit records.
- [ ] Modules record audit facts only via the contract or subscribed events —
      never by writing this module's tables directly, and never by re-implementing
      auditing.
- [ ] `audit.view` is required to view the workspace trail; `system.audit.view`
      gates the system trail; deny by default, enforced server-side.
- [ ] Audit queries are filterable (actor/entity/action/time) and paginated (no
      unbounded queries).
- [ ] Permission- and role-affecting actions are audited per
      `PERMISSION_MODEL.md` §7.
- [ ] A new module's auditable events are recorded without modifying the Audit
      engine.
- [ ] Audit depends only on shared/foundation modules and introduces no cycle.

### Related Documents
`MODULES.md` · `ARCHITECTURE.md` · `PERMISSION_MODEL.md` · `DOMAIN_MODEL.md` ·
`WORKSPACE_MODEL.md` · `DATABASE_GUIDE.md` · `SECURITY_GUIDE.md` ·
`FEATURE_SPECIFICATIONS/Settings.md`
