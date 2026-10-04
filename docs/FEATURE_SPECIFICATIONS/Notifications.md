# FEATURE SPEC — Notifications

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Notifications · **Layer:** Platform Services · **Implemented in:** Phase 9
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Notifications module provides the **unified notification center** for
HaHireAI: a single, per-user, workspace-contextual inbox where users read,
manage, and act on notifications generated across the platform. It is a
cross-cutting **shared service** that exists once and is consumed via its contract;
modules MUST NOT build their own notification stores (`PROJECT_CONSTITUTION.md` §4,
`MODULES.md` §4). Notifications are **per-user** and carry workspace context
(`DOMAIN_MODEL.md` §4.4). The center is **realtime-ready** by design (delivery
channels and live push are layered on the same store without changing it).

## 2. Scope

**In scope**
- A per-user notification inbox with states: **unread → read → archived**, plus
  delete.
- Notification **categories** (e.g. membership, files, settings, security,
  system) for grouping and filtering.
- Creation of notifications by other modules via the contract and/or by
  subscribing to platform events.
- Bulk actions: mark-all-read, archive, delete; unread counts.
- Realtime-ready delivery hooks (the store is channel-agnostic; transport such as
  websockets/email is layered on top).

**Out of scope**
- Email/SMS/push **transport implementations** — requested as capabilities;
  Notifications orchestrates delivery but does not embed providers (those arrive
  with Integration, Phase 13).
- Per-workspace notification *preferences* schema — owned by **Settings**
  (Notifications reads them).
- The business meaning of each event — owned by the publishing module.
- Audit trail — owned by **Audit** (notifications are user-facing; audit is the
  immutable record).

## 3. Inputs

- Notification-create requests from other modules (recipient `User`, workspace
  context, category, payload/links) via the contract.
- Platform events the module subscribes to, mapped to notifications.
- User actions: read, unread, archive, delete, mark-all-read.
- Notification preferences (read from **Settings**).

## 4. Outputs

- Persisted `Notification` records per user with state and category.
- Unread counts and filtered inbox read models for presentation.
- Realtime delivery signals (for clients that subscribe).
- Domain events in §7.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Users** — to resolve recipients (contract).
- **Workspaces** — to attach workspace context to notifications (contract).
- **Permissions** — for authorization of management actions.
- **Settings** (shared service) — to read notification preferences.
- **Event Bus** (shared) — to subscribe to platform events that generate
  notifications.

Notifications is consumed by many modules and depends only on shared/foundation
modules; it MUST NOT depend on business modules, keeping the graph acyclic. It
reacts to other modules via **events**, not inbound dependencies
(`MODULES.md` §5).

## 6. Permissions (keys this module declares; resource.action grammar)

- `notifications.view` — view one's own notification center.
- `notifications.manage` — manage notifications (mark read/unread, archive,
  delete, bulk actions).

A user manages **their own** notifications; the inbox is per-user and never
exposes another user's notifications. System-wide notification administration, if
any, is governed by a system permission in the Platform Context. Deny by default;
enforcement is server-side (`PERMISSION_MODEL.md` §5).

## 7. Events (Published / Subscribed)

**Published** (`module.entity.event`, past tense)
- `notifications.notification.created`
- `notifications.notification.read`
- `notifications.notification.archived`
- `notifications.notification.deleted`

**Subscribed** (illustrative — the center maps platform events to notifications)
- `memberships.invitation.created` — notify the invited user.
- `memberships.membership.activated` / `memberships.membership.removed`.
- `files.file.retention_expired` — notify the file owner.
- `settings.setting.updated` — notify on security-relevant changes (optional).
- `workspaces.workspace.ownership_transferred` — notify affected members.

The subscription set is additive: new modules' events can generate notifications
without changing the Notifications engine.

## 8. Data Owned (conceptual entities only — defer detail to DATABASE_ARCHITECTURE.md, Phase 3)

- **Notification** *(per-user, workspace-contextual)* — recipient `user_id`,
  `workspace_id` (context), category, type, payload/links, state (unread / read /
  archived), read-at, timestamps.
- **Notification Category** *(reference)* — category key and label for grouping
  and filtering (display-only; never affects enforcement).

Notifications carry workspace context so the inbox can be scoped to the active
workspace; a user's notifications are isolated to that user.

## 9. Acceptance Criteria (checklist of testable outcomes)

- [ ] The inbox is strictly per-user; a user never sees another user's
      notifications.
- [ ] Notification state transitions cover unread → read → archived and delete;
      unread counts are accurate.
- [ ] Other modules can create notifications only through the contract or via
      subscribed events — never by writing this module's tables directly.
- [ ] Notifications carry workspace context and can be filtered by the active
      workspace and by category.
- [ ] `notifications.manage` is required for management actions; deny by default,
      enforced server-side.
- [ ] The store is channel-agnostic and realtime-ready: adding a delivery channel
      requires no change to the notification store.
- [ ] Each listed action emits its event in §7.
- [ ] A new module's events can generate notifications without modifying the
      Notifications engine.
- [ ] Notifications depends only on shared/foundation modules and introduces no
      cycle.

### Related Documents
`MODULES.md` · `ARCHITECTURE.md` · `PERMISSION_MODEL.md` · `DOMAIN_MODEL.md` ·
`WORKSPACE_MODEL.md` · `FEATURE_SPECIFICATIONS/Settings.md` ·
`FEATURE_SPECIFICATIONS/Memberships.md` · `FEATURE_SPECIFICATIONS/Audit.md`
