# FEATURE SPEC — Memberships

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Memberships · **Layer:** Identity & Access · **Implemented in:** Phase 9
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Memberships module owns the **link between a `User` and a `Workspace`** — the
`Membership` aggregate — together with the **invitation** flow that brings users
into a workspace. A Membership carries status, assigned roles, join metadata, and
last activity; it is the mechanism by which one identity gains a context inside a
tenant (`DOMAIN_MODEL.md` §4.1, `WORKSPACE_MODEL.md` §5). This module governs the
Membership and Invitation state machines (`STATE_DIAGRAMS.md` §7–§8) and is the
authoritative source for "who belongs to this workspace and in what status."

## 2. Scope

**In scope**
- Membership lifecycle: `Invited → Active → Suspended → Active`, plus terminal
  `Cancelled` and `Removed` (`STATE_DIAGRAMS.md` §7).
- Invitations by **email**, **link**, and **code**, with states
  `Pending → Accepted / Rejected / Expired / Cancelled` and `resend` (stays
  Pending with a new expiry) per `STATE_DIAGRAMS.md` §8.
- Invitation issuance, acceptance, rejection, expiry, resend, and cancellation.
- Assignment of workspace **roles** to a Membership (references Permissions; this
  module does not define roles).
- Join metadata: joined-at, invited-by, invitation reference, last-activity.
- Owner Membership creation on behalf of the Workspaces module at workspace
  creation, and reassignment on ownership transfer.

**Out of scope**
- The `User` identity and profile — owned by **Users**.
- Workspace lifecycle and the tenant root — owned by **Workspaces**.
- Role definitions, the permission catalog, and authorization checks — owned by
  **Permissions** (this module only *assigns* existing roles).
- Email delivery transport — performed via **Notifications** (shared service).

## 3. Inputs

- Invitation requests (email address, target roles, channel: email/link/code)
  from authorized members.
- Invitation acceptance/rejection from the invited `User` (or via link/code).
- Resend and cancel commands for pending invitations.
- Membership suspend, reactivate, and remove commands.
- Role-assignment changes for a Membership.
- Owner-Membership provisioning/transfer requests from the Workspaces module.

## 4. Outputs

- Persisted `Membership` and `Invitation` aggregates with ULID identities.
- Effective membership status and role assignments for use by Permissions when
  computing effective permissions.
- The member roster of a workspace (paginated) for presentation and Search.
- Invitation artifacts (tokens/links/codes) and their validity windows.
- Domain events in §7.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Workspaces** — to resolve the target workspace and its tenant context
  (consumed via contract). Memberships depends on Workspaces, not the reverse.
- **Users** — to resolve the invited/joining `User` identity (consumed via contract).
- **Permissions** — to validate and record role assignments and to inform
  effective-permission computation (consumed via contract).
- **Notifications** (shared service) — to deliver invitation emails and
  membership-change notices.
- **Audit** (shared service) — to record invitation and membership actions
  (including role changes and ownership-related membership reassignment).
- **Search** (shared service) — to index members and invitations for unified search.
- **Settings** (shared service) — to read invitation expiry windows and join policy.

## 6. Permissions (keys this module declares; resource.action grammar)

- `member.view` — view the member roster and a member's status/roles.
- `member.invite` — issue invitations (email/link/code).
- `member.invite.resend` — resend a pending invitation.
- `member.invite.cancel` — cancel a pending invitation.
- `member.update` — change a member's assigned roles / membership attributes.
- `member.suspend` — suspend an active membership.
- `member.reactivate` — reactivate a suspended membership.
- `member.remove` — remove a member (terminal `Removed`).

Accepting or rejecting one's **own** invitation is performed by the invited user
and is gated by invitation-token validity rather than a workspace permission.
Every administrative action is permission-checked server-side; deny by default
(`PERMISSION_MODEL.md` §5).

## 7. Events (Published / Subscribed)

**Published** (`module.entity.event`, past tense)
- `memberships.invitation.created`
- `memberships.invitation.resent`
- `memberships.invitation.cancelled`
- `memberships.invitation.accepted`
- `memberships.invitation.rejected`
- `memberships.invitation.expired`
- `memberships.membership.activated`
- `memberships.membership.suspended`
- `memberships.membership.reactivated`
- `memberships.membership.removed`
- `memberships.membership.roles_changed`

**Subscribed**
- `workspaces.workspace.created` — provision the owner Membership (when the
  Workspaces module delegates owner-membership creation via event).
- `workspaces.workspace.ownership_transferred` — reflect the reassigned owner
  Membership.
- `workspaces.workspace.deleted` / `workspaces.workspace.archived` — react to
  tenant lifecycle for roster visibility (no hard delete of membership data).

## 8. Data Owned (conceptual entities only — defer detail to DATABASE_ARCHITECTURE.md, Phase 3)

- **Membership** *(aggregate root, workspace-scoped)* — `user_id`,
  `workspace_id`, status, assigned-role references, invited-by, invitation
  reference, joined-at, last-activity, timestamps.
- **Invitation** *(workspace-scoped)* — `workspace_id`, target email/identity,
  channel (email/link/code), token/code, target roles, status, expiry,
  invited-by, timestamps.

Membership↔Role is an assignment relationship; Role records are owned by
Permissions. Soft-delete (`deleted_at`) and terminal status (Removed/Cancelled)
are distinct and MUST NOT be conflated (`DATABASE_GUIDE.md` §9).

## 9. Acceptance Criteria (checklist of testable outcomes)

- [ ] A `Membership` uniquely links one `User` to one `Workspace`; no duplicate
      active memberships for the same pair.
- [ ] Membership transitions follow `STATE_DIAGRAMS.md` §7 exactly; unlisted
      transitions are rejected.
- [ ] Invitation transitions follow `STATE_DIAGRAMS.md` §8 exactly; `resend`
      keeps state `Pending` and sets a new expiry.
- [ ] Invitations are supported via email, link, and code; each carries a validity
      window and is single-acceptance.
- [ ] Accepting a valid invitation creates or activates the corresponding
      Membership; expired/cancelled/rejected invitations cannot be accepted.
- [ ] Role assignment references existing workspace roles (Permissions) and never
      branches on a role name.
- [ ] Every administrative action is denied without its required permission and is
      recorded in the Audit trail.
- [ ] Each listed action emits its event in §7.
- [ ] All queries are workspace-scoped; a member of one workspace is never visible
      to another.
- [ ] The module depends on Workspaces/Users/Permissions only and introduces no
      dependency cycle.

### Related Documents
`WORKSPACE_MODEL.md` · `DOMAIN_MODEL.md` · `PERMISSION_MODEL.md` ·
`STATE_DIAGRAMS.md` · `MODULES.md` · `ARCHITECTURE.md` ·
`FEATURE_SPECIFICATIONS/Workspaces.md` · `FEATURE_SPECIFICATIONS/Notifications.md`
