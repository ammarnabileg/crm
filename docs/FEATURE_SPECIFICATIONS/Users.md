# FEATURE SPEC — Users

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Users · **Layer:** Identity & Access · **Implemented in:** Phase 8
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Users module owns the **single `User` identity** of HaHireAI and its
**profile lifecycle**. There is exactly one human account type — `User` — reused
for every context a person ever holds (`USER_MODEL.md` §1; Constitution §3.3). A
**System Owner is not a separate account type**: it is a `User` that holds
`system.*` permissions (`DOMAIN_MODEL.md` §2). This module is the authority for
person-level, **global** identity data (profile, locale, status, verification
flag) and the account state machine; it does **not** own credentials/sessions
(Authentication) or workspace-scoped data (Memberships/Applications/Candidate
Profiles).

## 2. Scope

**In scope**
- The canonical **`User` aggregate**: profile (name, avatar, locale/language, timezone, contact info) and global flags.
- **Account status lifecycle:** Registered → Active → Suspended → Active; Active → Deactivated (soft) (`USER_MODEL.md` §5).
- The **System Owner capability** as a *permission set on a `User`* (never a separate table or account type).
- Resolving a `User` identity for other modules via a **contract**.
- Recording the resume/CV as a **File owned by the user** (reference only; storage is the Files service).

**Out of scope**
- **Credentials, sessions, password reset, MFA** — owned by **Authentication**.
- **Authorization / permission evaluation** — owned by **Permissions**; this module only *reflects* whether a user holds `system.*`.
- **Workspace-specific data:** `Membership`, roles-in-a-workspace, `Application`, `CandidateProfile`, `Employee` — owned by their respective modules (`USER_MODEL.md` §3).
- **Candidate Profile** (a per-(User, Workspace) projection) — owned by Recruitment, never aggregated across workspaces (`DOMAIN_MODEL.md` Invariant 3).
- File storage internals — the **Files** shared service.

## 3. Inputs

- New-identity creation requests (from registration via Authentication, and from the Installer for the first System Owner).
- Profile update submissions from the authenticated user (name, avatar, locale, timezone, contact info).
- Account-status actions: suspend/reactivate (by a System Owner), deactivate (self or admin).
- System-permission grant/revoke signals from **Permissions** that determine System Owner capability.
- Avatar/resume file references from the **Files** service.

## 4. Outputs

- The persisted, canonical **`User`** record (global identity).
- Current **account status** (active / suspended / deactivated) and **email-verification** flag.
- A resolved identity (id, display name, locale, timezone, `is_system_owner` capability) exposed via the module's **contract** for consumers.
- The lifecycle events in §7.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Core Kernel** (Foundation) — routing, request/response, configuration, logger, events.
- **Database** (Foundation) — persistence; the `users` table is **global** (no `workspace_id`, `DATABASE_GUIDE.md` §6.3).
- **Permissions** (Identity & Access) — to determine the **System Owner** capability (presence of `system.*`); Users stores no role logic itself.
- **Files** (Platform Services) — avatar and resume/CV stored as user-owned Files (reference only).
- Consumed *by* **Authentication**, **Memberships**, **Recruitment**, and **System Administration** through the Users **contract** — they never read the `users` table directly (`DATABASE_GUIDE.md` §11).

## 6. Permissions (keys this module declares; resource.action grammar)

Self-service keys (a user managing their own identity) and System-Owner oversight
keys (managing other accounts in the Platform Context):

- `profile.view` — view one's own profile.
- `profile.update` — update one's own profile.
- `user.deactivate` — deactivate one's own account (self-service).
- `system.users.view` — view platform users (System Owner / Platform Context).
- `system.users.manage` — suspend/reactivate/manage user accounts (System Owner).
- `system.users.impersonate` — *(reserved, gated)* support impersonation, audited.

> System Owner capability derives from `system.*` permissions on a `User`, granted
> via **Permissions** — never from a flag interpreted as a role name
> (`USER_MODEL.md` §2; `PERMISSION_MODEL.md` §1).

## 7. Events (Published / Subscribed)

**Published**
- `users.user.created` — a new `User` identity was created.
- `users.user.profileUpdated` — profile fields changed.
- `users.user.suspended` — an account was suspended by a System Owner.
- `users.user.reactivated` — a suspended account was reactivated.
- `users.user.deactivated` — an account was (soft) deactivated.
- `users.systemOwner.granted` / `users.systemOwner.revoked` — system-level capability changed for a `User`.

**Subscribed**
- `authentication.user.registered` — create/complete the corresponding `User` identity record.
- `authentication.email.verified` — set the user's email-verification flag.

## 8. Data Owned (conceptual entities only — defer detail to DATABASE_ARCHITECTURE.md, Phase 3)

- **User** *(root, global)* — the single human identity: profile (name, avatar, locale/language, timezone, contact), email-verification flag, and account status (active/suspended/deactivated). Holds **only** global, person-level data (`USER_MODEL.md` §3).

This module owns **no** workspace-scoped tables. Credentials/sessions are owned by
**Authentication**; the System Owner capability is represented by `system.*`
permissions managed by **Permissions** — there is **no** separate System Owner
table (`USER_MODEL.md` Invariant 3).

## 9. Acceptance Criteria (checklist of testable outcomes)

- [ ] There is exactly **one** human account type, `User`; the same identity is reused across all contexts (`USER_MODEL.md` §1; Constitution §3.3).
- [ ] A person is **never duplicated** as separate "candidate"/"recruiter"/"employee" accounts (Invariant 1).
- [ ] The `User` aggregate holds **only global, person-level data**; no workspace-specific fields exist on it (`USER_MODEL.md` §3).
- [ ] **System Owner** is represented by `system.*` permissions on a `User`, with **no** separate account type or table (Invariant 3); the first System Owner is created by the Installer.
- [ ] The account lifecycle implements Registered → Active → Suspended → Active and Active → Deactivated (soft), with no other transitions (`USER_MODEL.md` §5).
- [ ] Suspension/deactivation **publishes events** that Authentication consumes to revoke sessions and block login.
- [ ] No business logic anywhere **branches on a role name**; only on permissions (Invariant 5).
- [ ] The resume/CV and avatar are modeled as **Files owned by the user**, reusable across applications — not copied into other records (`USER_MODEL.md` §3; `DATABASE_GUIDE.md` §10).
- [ ] Other modules resolve identity **only** through the Users contract; none read the `users` table directly (`DATABASE_GUIDE.md` §11).
- [ ] A user's data in one workspace is **never** exposed through the global identity (`USER_MODEL.md` Invariant 4).
- [ ] All profile reads/writes pass a deny-by-default permission check at the Application boundary (`PERMISSION_MODEL.md` §5).

### Related Documents
`USER_MODEL.md` · `DOMAIN_MODEL.md` · `PERMISSION_MODEL.md` · `WORKSPACE_MODEL.md` ·
`ARCHITECTURE.md` · `MODULES.md` · `DATABASE_GUIDE.md` · `Authentication.md` ·
`Permissions.md` · `Installer.md`
