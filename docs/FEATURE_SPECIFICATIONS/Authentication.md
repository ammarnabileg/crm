# FEATURE SPEC — Authentication

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Authentication · **Layer:** Identity & Access · **Implemented in:** Phase 8
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Authentication module is the shared **identity-proving** capability of
HaHireAI. It establishes *who* a request belongs to: registration, login/logout,
password reset, remember-me, secure session management, optional email
verification, and an **MFA-ready** posture. It hardens these flows per
`SECURITY_GUIDE.md` (Argon2id, secure rotating sessions, CSRF, rate limiting) and
is the single place login, sessions, and password handling are implemented — no
module re-implements them (Constitution §4; `MODULES.md` §4). It proves identity;
it does not decide *what* an identity may do (that is **Permissions**) nor own the
person's profile (that is **Users**).

## 2. Scope

**In scope**
- **Register** a new `User` (email + Argon2id-hashed password).
- **Login / Logout** with session establishment and teardown.
- **Forgot / Reset password** via out-of-band (email) single-use tokens.
- **Remember-me** via a long-lived, random, single-use **rotating** token.
- **Session management:** secure cookies, ID regeneration on privilege change, idle + absolute timeouts, server-side revocation.
- **Optional email verification** (configurable per `USER_MODEL.md` §5).
- **MFA-ready** data model and flow hooks (enrol/challenge enabled in a later phase).
- **Rate limiting / brute-force protection** and lockout on auth endpoints.

**Out of scope**
- **Social login / external IdP / SSO** — explicitly out of scope for the initial release (`USER_MODEL.md` §6); SSO/OAuth/SCIM is the Integration Platform (Phase 13).
- The `User` **profile and account-status lifecycle** — owned by the **Users** module.
- **Authorization / permission checks** — owned by **Permissions** (deny-by-default at the Application boundary).
- **Workspace/Membership** selection and context — owned by Workspaces/Memberships (Phase 9).
- Notification delivery transport — the **Notifications** shared service sends verification/reset messages.

## 3. Inputs

- Registration submissions (email, password, profile minimum) from the public web surface.
- Login credentials and optional remember-me opt-in.
- Forgot/reset requests and reset tokens.
- Email-verification tokens.
- Session cookies and remember-me cookies on subsequent requests.
- (MFA-ready) enrolment and challenge inputs once enabled.

## 4. Outputs

- An authenticated **session** (secure, `HttpOnly`, `Secure`, `SameSite`) identifying the current `User`.
- Created `User` credentials (delegated to **Users** for the identity record; this module owns the secret material flow).
- Issued/rotated remember-me tokens.
- Issued, single-use, expiring password-reset and email-verification tokens.
- Authentication outcomes (success/failure, lockout, verification-required) and the events in §7.
- Rate-limit decisions on protected endpoints.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Core Kernel** (Foundation) — routing, request/response, configuration, logger, events.
- **Database** (Foundation) — persistence + tenant guard (note: credentials/sessions are **global**, not workspace-scoped — `DATABASE_GUIDE.md` §6.3).
- **Users** (Identity & Access) — resolves/creates the `User` identity and reads account status (active/suspended/verified) via its contract.
- **Notifications** (Platform Services) — delivers verification and password-reset messages.
- **Permissions** is **not** a dependency of Authentication (no cycle): Authentication proves identity; Permissions authorizes actions afterward.

## 6. Permissions (keys this module declares; resource.action grammar)

Authentication endpoints are **pre-authentication** (register, login, reset) or
**self-scoped** (a user manages their own sessions), so they are gated by identity
and rate limits rather than granted permissions. The module declares self-service
keys for an authenticated user acting on their own credentials:

- `session.view` — view one's own active sessions.
- `session.revoke` — revoke one's own session(s).
- `auth.mfa.manage` — manage one's own MFA settings (MFA-ready; active in a later phase).

> System-Owner oversight of accounts (suspend/force logout) is exercised through
> **Users** / System Administration `system.*` keys, not declared here. Checks
> reference permission **keys only**, never role names (`PERMISSION_MODEL.md` §5).

## 7. Events (Published / Subscribed)

**Published**
- `authentication.user.registered` — a new account was registered.
- `authentication.user.loggedIn` — a session was established.
- `authentication.user.loggedOut` — a session was ended.
- `authentication.password.resetRequested` — a reset token was issued.
- `authentication.password.reset` — a password was successfully reset.
- `authentication.email.verified` — an email address was verified.
- `authentication.login.failed` — a failed attempt (for rate limiting/Audit/Observability).

**Subscribed**
- `users.user.suspended` / `users.user.deactivated` — revoke active sessions and block login for the affected `User`.

## 8. Data Owned (conceptual entities only — defer detail to DATABASE_ARCHITECTURE.md, Phase 3)

These are **global** (not workspace-scoped) identity-security records:

- **Credential** — the password hash (Argon2id) and rehash metadata associated with a `User`.
- **Session** — server-side session record enabling revocation (idle/absolute lifetimes).
- **Remember-me Token** — long-lived, random, single-use rotating token.
- **Password Reset Token** — single-use, expiring token.
- **Email Verification Token** — single-use, expiring token.
- **MFA Factor** *(MFA-ready)* — enrolled factor/secret material, dormant until MFA is enabled.
- **Login Attempt** — attempt record supporting rate limiting and lockout.

The **profile** fields of a person live in the **Users** module, not here.

## 9. Acceptance Criteria (checklist of testable outcomes)

- [ ] Passwords are hashed with **Argon2id**; plaintext is never stored and rehash-on-verify is supported (`SECURITY_GUIDE.md` §2.1).
- [ ] Session cookies are `HttpOnly`, `Secure`, and `SameSite` (Lax minimum); the **session ID is regenerated** on login, logout, and privilege change (§2.2).
- [ ] Sessions enforce **idle + absolute timeouts** and can be **revoked server-side**.
- [ ] Remember-me uses a long-lived, random, **single-use rotating** token; theft of an old token does not grant access (§2.3).
- [ ] Forgot/reset uses **single-use, expiring** tokens delivered **out-of-band** (email) and **invalidates existing sessions** for that user on reset (§2.4).
- [ ] Email verification is **optional/configurable**; when required, an unverified account cannot reach gated areas (`USER_MODEL.md` §5).
- [ ] The data model is **MFA-ready** without enabling MFA prematurely (§2.5).
- [ ] Login, reset, MFA, and token-exchange endpoints are **rate-limited** with brute-force lockout (§2.6).
- [ ] All state-changing auth requests carry and validate a **per-session CSRF token** (§5; Constitution §10).
- [ ] **No social login** is present in the initial release (`USER_MODEL.md` §6).
- [ ] Login, sessions, and password handling exist **only** in this module; no other module re-implements them (`MODULES.md` §4).
- [ ] On `users.user.suspended`/`deactivated`, the user's active sessions are revoked and further login is blocked.
- [ ] The module proves identity only and performs **no** permission checks (those are at each module's Application boundary via **Permissions**).

### Related Documents
`SECURITY_GUIDE.md` · `USER_MODEL.md` · `PERMISSION_MODEL.md` · `ARCHITECTURE.md` ·
`MODULES.md` · `DATABASE_GUIDE.md` · `Users.md` · `Permissions.md` · `Core_Kernel.md`
