# USER MODEL — HaHireAI

> **Status:** Adopted (Canon) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md`, `DOMAIN_MODEL.md`.

---

## 1. The One-Identity Rule

There is **one** human account type in HaHireAI: **`User`**. A human being has
exactly one `User`. That single identity is reused for every context the person
ever has on the platform — hiring, being hired, working, or operating the
platform itself.

There is **no** `Candidate` account, **no** `Recruiter` account, **no** `HR`
account, **no** `Employee` account. Those words describe *contexts*, not
accounts (see §4).

## 2. The Two System Levels

| Level | Definition |
|---|---|
| **System Owner** | A `User` that holds **system-level permissions** over the platform itself. |
| **User** | Any `User` without system-level permissions. |

`System Owner` is a capability, not a class. Internally it is represented by
system permissions attached to a `User` (e.g. `system.*`). The same person can
operate the platform *and* use it as an ordinary user with the same login.

## 3. What a User Owns (identity-level data)

A `User` aggregate holds only **global, person-level** data:

- Authentication: email, password hash (Argon2id), MFA settings, sessions.
- Profile: name, avatar, locale/language preference, timezone, contact info.
- Global flags: `is_system_owner` capability (via permissions), account status
  (active/suspended), email verification.
- A resume/CV is **a File owned by the user**, reusable across applications.

A `User` does **not** directly hold workspace-specific data. That lives in
`Membership`, `Application`, and `CandidateProfile` (workspace-scoped).

## 4. Contexts (not accounts)

The same `User` acquires contexts through relationships:

| Context | Acquired through | Scope |
|---|---|---|
| Workspace owner/admin | `Membership` + permissions | one workspace |
| Recruiter / HR / Hiring Manager / Interviewer / Viewer | `Membership` + a `Role` (permission bundle) | one workspace |
| Candidate | an `Application` to a job (and the resulting `CandidateProfile`) | one workspace's view |
| Employee | post-hire `Employee` record | one workspace |
| System Owner | system permissions | the platform |

A user can hold **different** contexts in **different** workspaces
simultaneously, with **different** permissions in each.

## 5. Account Lifecycle

```
Registered → (Email Verification, optional/configurable) → Active
Active → Suspended (by System Owner) → Active
Active → Deactivated (self or admin) [soft]
```

- First user created during installation becomes the **first System Owner**
  (see `INSTALLER_ARCHITECTURE.md`).
- All subsequent self-registered users are plain `User`s.
- A newly registered `User` with no workspace is offered exactly two paths:
  **Create Workspace** or **Join Workspace** (see `WORKSPACE_MODEL.md`).

## 6. Registration & Authentication (scope reference)

Authentication behavior (register, login, logout, forgot/reset password,
remember-me, optional email verification) is specified in the Authentication
module spec (`FEATURE_SPECIFICATIONS/`) and `SECURITY_GUIDE.md`. Social login is
explicitly out of scope for the initial release.

## 7. Invariants

1. One human ⇒ one `User`. Never duplicate a person as multiple accounts.
2. Identity data is global; all hiring/work data is workspace-scoped and reached
   via `Membership`/`Application`/`CandidateProfile`.
3. System Owner status is a permission set on a `User`, never a separate table.
4. A user's data in one workspace is **never** visible to another workspace
   (enforced by tenant isolation; see `WORKSPACE_MODEL.md`).
5. No business logic ever branches on a role name; only on permissions.

---

### Related Documents
`DOMAIN_MODEL.md` · `WORKSPACE_MODEL.md` · `PERMISSION_MODEL.md` ·
`SECURITY_GUIDE.md` · `PROJECT_CONSTITUTION.md`
