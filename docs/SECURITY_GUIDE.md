# SECURITY GUIDE — HaHireAI

> **Status:** Draft (Phase 1) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md` (§10). **Access detail:** Phase 4 docs.

---

## 0. Purpose & Scope

This guide elaborates the binding security essentials of `PROJECT_CONSTITUTION.md`
§10 into actionable engineering rules for the whole platform — all modules, all
layers, all contributors (human or AI). It states *how*; the Constitution states
the *law*, and where any statement here conflicts, **the Constitution wins**.

Interpretation keywords (**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**,
**MAY**) follow RFC 2119. A **MUST / MUST NOT** rule is binding; a violation is a
security defect that blocks merge and release. Detailed access-control catalogs
(`SECURITY_MATRIX.md`, `ACCESS_POLICIES.md`, `AUDIT_EVENTS.md`,
`SYSTEM_PERMISSIONS.md`, `WORKSPACE_PERMISSIONS.md`) are authored in **Phase 4**
and referenced, not duplicated, here.

> Code snippets are **illustrative examples only** — not implementation and not
> normative. The normative content is the prose rules.

---

## 1. Security Principles

HaHireAI is built **security by design**: security is decided before the first
line of code, never bolted on afterward (Constitution §3.7). Four principles are
binding and govern every later decision in this guide.

1. **Security by design.** Threats are modeled per module *before* implementation,
   as part of the docs-first workflow. Every `FEATURE_SPECIFICATIONS/` entry MUST
   state its data sensitivity, required permissions, and tenant-scoping before
   code exists.
2. **Deny by default.** The absence of an explicit grant means *denied*. This
   applies to permissions, routes, file visibility, API scopes, and module
   capabilities. Nothing is open unless something deliberately opens it.
3. **Least privilege.** Every actor, token, role, service binding, and database
   credential receives the **minimum** authority required and no more. Broad or
   "admin everything" grants are a defect; permissions are fine-grained atoms
   (see `PERMISSION_MODEL.md`).
4. **Defense in depth.** No single control is trusted alone. UI hiding, route
   middleware, application-boundary permission checks, the repository tenant
   guard, prepared statements, output escaping, and database constraints are
   **layered** so that one failure does not become a breach.

Deny-by-default and least privilege shrink the attack surface; defense in depth
contains the damage when any one layer is bypassed.

---

## 2. Authentication

Authentication is provided **once** by the shared **Authentication** module
(`MODULES.md`, Identity & Access) and consumed via its contract. Modules MUST NOT
re-implement login, sessions, or password handling.

### 2.1 Password hashing
- Passwords **MUST** be hashed with **Argon2id** (Constitution §10). Plaintext,
  reversible storage, and legacy fast hashes (MD5/SHA-1/plain SHA-256) are
  forbidden for passwords.
- Hashing parameters (memory, time, threads) **MUST** be configurable via
  environment and tuned for the production host. Verification **MUST** transparently
  re-hash when parameters change (`password_needs_rehash` pattern).

```php
// EXAMPLE ONLY — illustrative, not implementation
$hash = password_hash($plain, PASSWORD_ARGON2ID, $argonOptions);
if (password_verify($plain, $hash) && password_needs_rehash($hash, PASSWORD_ARGON2ID, $argonOptions)) {
    // re-store upgraded hash
}
```

### 2.2 Sessions
- Session cookies **MUST** be `HttpOnly`, `Secure`, and `SameSite` (Lax minimum;
  Strict where UX allows) — Constitution §10.
- The session ID **MUST** be regenerated on privilege change (login, logout,
  owner elevation) to prevent fixation. Sessions **MUST** have idle + absolute
  lifetimes and be rejected server-side once expired.
- Server-side session storage **SHOULD** be used so sessions can be revoked
  centrally (e.g. on password change or forced logout).

### 2.3 Remember-me
- Remember-me **MUST** use a long-lived, random, **single-use rotating** token,
  stored hashed at rest, bound to one user, and revocable — never the password and
  never guessable. Reuse of a rotated token (theft signal) SHOULD invalidate the
  series.

### 2.4 Password policy
- A configurable policy **MUST** enforce a minimum length (≥ 12 recommended) and
  **SHOULD** screen against known-breached/common passwords.
- Password reset **MUST** use a short-lived, single-use, random token delivered
  out-of-band (email), and MUST invalidate existing sessions for that user.

### 2.5 MFA-ready
- The Authentication module is **MFA-ready** (`MODULES.md`): the data model and
  login flow MUST accommodate a second factor (TOTP first) without redesign, even
  when MFA is enabled only in a later phase.

### 2.6 Rate limiting & brute-force protection
- Authentication endpoints (login, reset, MFA, token exchange) **MUST** be rate
  limited per identifier and per IP. Repeated failures **MUST** trigger
  progressive backoff and/or temporary lockout; such events SHOULD be auditable.

### 2.7 Email verification (optional)
- Email verification **MAY** be required per deployment/workspace policy. When
  enabled, unverified accounts MUST have restricted capability until verified.

> **No social login initially.** External identity providers (OAuth/SSO/SCIM) are
> introduced via the **Integration Platform** (Phase 13), not in the initial
> authentication surface.

---

## 3. Authorization

Authorization is **permissions-based and deny-by-default** (Constitution §10,
`PERMISSION_MODEL.md`). This guide restates the security-critical rules:

- **Checks happen at the Application boundary.** Every state-changing or
  data-reading use case **MUST** verify the required permission *before*
  executing (Constitution §10; `ARCHITECTURE.md` §8). Presentation/UI is never
  the enforcement point.
- **Never branch on role names.** Code **MUST NOT** read, compare, or special-case
  a role's name anywhere. Checks reference **permission keys only**
  (`resource.action`, e.g. `job.create`; system keys `system.*`). Hard-coded
  roles are forbidden (Constitution §15).
- **UI hiding is never a substitute for a server check.** Hiding a sidebar item
  or button (`SIDEBAR_MODEL.md`) is a UX convenience; the server MUST independently
  deny the action. A hidden-but-reachable action MUST still be denied server-side.
- **Two account levels only.** `System Owner` (holds `system.*`) and `User`
  (Constitution §10, §15). System permissions are visible only in the **Platform
  Context**; workspace permissions only in the **Workspace Context** and are
  additionally gated by subscription + enabled modules.

```php
// EXAMPLE ONLY — application-boundary check by permission key, never role name
$policy->require($actor, 'candidate.export', $workspaceId); // throws AuthorizationException on deny
```

For each protected action, Phase 4 docs (`ACCESS_POLICIES.md`,
`SECURITY_MATRIX.md`) record **Purpose, Required Permission, Dependencies, Denied
Behaviour**.

---

## 4. Multi-Tenant Isolation — THE #1 CRITICAL INVARIANT

**Workspace is the tenant boundary, and isolation is absolute.** Cross-workspace
data leakage is the single most severe security defect in HaHireAI (Constitution
§3.5, §10, §15; `WORKSPACE_MODEL.md` §3). Every other control in this document is
secondary to this one.

### 4.1 The mandatory tenant guard
- Every workspace-scoped record carries a `workspace_id` and is **invisible** to
  every other workspace.
- Every workspace-scoped query — `SELECT`, `INSERT`, `UPDATE`, `DELETE` —
  **MUST** be filtered/stamped by the active `workspace_id`. There are **no
  exceptions**.
- Enforcement lives at the **data-access (repository) layer** via a mandatory
  tenant guard (`ARCHITECTURE.md` §8, `DATABASE_ARCHITECTURE.md`), not ad-hoc
  per-query discipline. The guard makes the safe path the default and an un-scoped
  workspace query fail closed.

```php
// EXAMPLE ONLY — the guard is applied centrally, not copy-pasted per call site
$rows = $tenantRepo->forWorkspace($workspaceId)->where('status', 'active')->get();
// A workspace-scoped query that omits the workspace context MUST fail, not run.
```

### 4.2 Everything workspace-scoped is isolated
The guard is not limited to recruitment rows. **Files, AI usage, billing, and
search are all isolated per workspace**:

- **Files** — storage, ownership, and visibility are workspace-scoped
  (`WORKSPACE_MODEL.md` §2; Files module); one workspace MUST NOT read another's
  files or guess their paths (see §7).
- **AI usage** — AI configuration, keys, prompts, limits, and usage records are
  per workspace (Phase 11); AI calls MUST be metered to the originating workspace
  and MUST NOT expose another's data or keys.
- **Billing & subscriptions** — plans, invoices, coupons, and entitlements are per
  workspace (Phase 14); financial data MUST NOT cross workspaces.
- **Search** — the index is **workspace-scoped**; queries MUST be filtered by
  `workspace_id` so results never surface another tenant's records.

### 4.3 Global vs scoped data
The only non-scoped data is the explicitly enumerated **global** set (users,
plans, the permission catalog, global AI provider definitions, system settings —
`WORKSPACE_MODEL.md` §3, `ENTITY_CATALOG.md`). Everything else is
workspace-scoped. Treating any business entity as global is a defect.

### 4.4 Testing the invariant
Tenant isolation **MUST** be tested explicitly with cross-tenant negative tests
(Workspace A actor attempting to read/modify Workspace B data MUST be denied) —
see `TESTING_GUIDE.md` §"Multi-tenant isolation".

---

## 5. Input Validation & Output Encoding

- **Validate all input.** Every external input (request body, query string,
  headers, uploaded file metadata, API payload, webhook body) **MUST** be
  validated against an explicit schema/whitelist at the Application boundary.
  Validation is allow-list based; reject unexpected fields.
- **Prepared statements only (SQLi).** All SQL **MUST** use parameterized
  prepared statements. String-concatenated SQL is forbidden (Constitution §6, §10).
  This is enforced by code review and static analysis.
- **Escape on render (XSS).** All dynamic output **MUST** be contextually escaped
  at render time (HTML, attribute, JS, URL contexts). Never trust stored data to
  be safe; escape on output, not only on input.
- **CSRF tokens on all state-changing requests.** Every `POST/PUT/PATCH/DELETE`
  (and any state-changing `GET`, which SHOULD be avoided) **MUST** carry and
  validate a per-session CSRF token (Constitution §10). Requests failing CSRF
  validation are rejected.

```php
// EXAMPLE ONLY — parameterized query; never interpolate values into SQL
$stmt = $pdo->prepare('SELECT id FROM jobs WHERE workspace_id = ? AND id = ?');
$stmt->execute([$workspaceId, $jobId]);
```

---

## 6. Sessions, Secrets, Keys & Encryption at Rest

### 6.1 Session & cookie hardening
- All session/cookie hardening from §2.2 is binding: `HttpOnly`, `Secure`,
  `SameSite`, ID regeneration on privilege change, idle + absolute lifetimes,
  server-side revocation.
- Cookies MUST be scoped narrowly (path/domain) and MUST NOT carry sensitive data
  in clear; the cookie holds an opaque identifier only.

### 6.2 Secrets in environment
- Secrets (DB credentials, app keys, AI provider keys, payment keys, signing
  secrets) **MUST** come from the **environment** and **MUST NOT** be committed to
  the repository (Constitution §5, §10). `.env` is git-ignored; `.env.example`
  documents required keys with **no real values**.
- Configuration is loaded via the Environment/Configuration loaders
  (`ARCHITECTURE.md` §6); no secrets in code, constants, or globals.

### 6.3 Key management
- Application encryption keys and signing secrets **MUST** be rotatable. Key
  rotation MUST be possible without data loss (support old + new during rollover).
- Different concerns use different keys (e.g. session signing ≠ field encryption ≠
  webhook signing). Least privilege applies to keys as to actors.

### 6.4 Encryption at rest for sensitive fields & API keys
- Sensitive fields and **API keys MUST be encrypted at rest**. Stored secrets
  (workspace AI keys, integration credentials, webhook secrets) MUST be encrypted
  with an application-managed key, not stored in plaintext.
- **API keys / secrets are never shown again after save.** On creation the secret
  is displayed once; thereafter only a masked reference/last-4 is shown
  (`WORKSPACE_MODEL.md` §2 Integrations; Phase 13). Reads MUST NOT return the raw
  secret.

---

## 7. File Upload Security

The shared **Files** module owns upload, storage, ownership, visibility, and
retention (`MODULES.md`). Binding controls:

- **Type validation.** Validate by allow-list of MIME types **and** by content
  sniffing — never trust the client-supplied extension or `Content-Type` alone.
- **Size validation.** Enforce a configurable maximum size (`MAX_UPLOAD_BYTES`
  pattern) and reject oversize uploads before processing.
- **Store outside the web root.** Uploaded files **MUST** be stored outside
  `/public` (the only web-exposed directory — Constitution §4) and served only
  through an authorized, tenant-guarded, permission-checked controller. Direct URL
  access to raw storage is forbidden.
- **Randomized names.** Stored filenames **MUST** be randomized (e.g. ULID/UUID)
  so they are non-guessable and decoupled from user input; the original name is
  metadata only. This prevents path traversal and enumeration.
- **AV scan hook.** The upload pipeline **MUST** expose an antivirus/malware
  **scan hook**; files MUST NOT be made available for download until scanning has
  passed (or the hook is explicitly disabled in non-production).
- **Tenant scoping.** Files are workspace-scoped (§4.2); access checks combine the
  tenant guard with file visibility and the relevant permission.

---

## 8. Transport & Security Headers

- **HTTPS/HSTS.** All traffic **MUST** be served over HTTPS in staging and
  production; **HSTS** MUST be enabled (Constitution §10). HTTP MUST redirect to
  HTTPS.
- **Single front controller.** Only `/public` is web-exposed, via the single
  front controller `/public/index.php` (Constitution §4; `ARCHITECTURE.md` §7).
  No other directory is reachable over the web.
- **Security headers** MUST be sent on responses:
  - **Content-Security-Policy (CSP):** restrictive default-src; allow only needed
    origins. Inline scripts SHOULD be avoided (Alpine.js usage must respect CSP).
  - **X-Content-Type-Options:** `nosniff`.
  - **X-Frame-Options / frame-ancestors:** deny framing except where explicitly
    required.
  - **Referrer-Policy:** `strict-origin-when-cross-origin` or stricter.
  - **Permissions-Policy:** disable unused browser features.

| Header | Required value (baseline) |
|---|---|
| `Strict-Transport-Security` | `max-age=…; includeSubDomains` (preload optional) |
| `Content-Security-Policy` | restrictive `default-src 'self'`; explicit allow-list |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` (or CSP `frame-ancestors`) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |

---

## 9. API & Webhook Security

> **Set here, implemented in Phase 13** via the **Integration Platform** (API
> Gateway, Webhooks, Connectors, SSO). These rules are binding on that
> implementation.

- **Token auth.** API access **MUST** use scoped, revocable tokens (or OAuth where
  applicable). Tokens are stored hashed at rest and tied to a workspace +
  permissions. Deny-by-default applies: a token grants only its explicit scopes.
- **Scopes.** Each token carries the **minimum** scopes required (least
  privilege). API actions are still subject to the same permission checks as the
  UI — the API is another caller of the same Application use cases
  (`MODULES.md` §5).
- **Signature validation.** Inbound webhooks **MUST** be verified via HMAC
  signature over the raw body using a per-endpoint secret; unsigned or
  mismatched-signature requests are rejected.
- **Replay protection.** Webhooks/API requests **MUST** include a timestamp and/or
  nonce; stale (outside an allowed window) or already-seen requests are rejected.
- **Rotation.** Tokens and webhook signing secrets **MUST** be rotatable, with the
  raw value shown once on creation only (§6.4).
- **Tenant scoping.** Every API/webhook operation is bound to one workspace and
  passes the tenant guard (§4); a token MUST NOT reach another workspace's data.

---

## 10. PII Handling, Privacy & Audit of Sensitive Access

HaHireAI processes candidate and employee personal data; privacy is a first-class
concern (Constitution §3.7).

- **Permission-gated PII.** Access to PII (candidate profiles, contact details,
  documents) **MUST** be permission-gated and tenant-scoped. No broad read access.
- **Audited sensitive access.** Sensitive access and changes (PII reads where
  required, exports, permission/role changes, ownership transfer) **MUST** be
  audited — who, when, where, what changed — via the shared **Audit** module. The
  authoritative list of audited events is `AUDIT_EVENTS.md` (Phase 4).
- **Data minimization & retention.** Collect only what is needed; honor retention
  policy (Files retention; `ARCHIVING_POLICY.md`). Soft delete retains business
  data per policy but MUST still respect access controls.
- **Encryption.** PII flagged sensitive is encrypted at rest (§6.4) and always in
  transit (§8).
- **Export discipline.** Bulk export of PII is a high-risk action: permission-
  gated, rate-limited, and audited.

---

## 11. Dependency Security & Threat-Model Summary

### 11.1 Dependency security
- **`composer audit` MUST run in CI** and a known vulnerability in a dependency
  blocks merge/release (Constitution §13, §14; `TESTING_GUIDE.md`,
  `DEPLOYMENT_GUIDE.md`).
- Dependencies are minimized (no framework — Constitution §5) and pinned;
  production installs use `composer install --no-dev` so dev tooling never ships.
- Updates are deliberate and reviewed; transitive vulnerabilities are tracked.

### 11.2 Threat-model summary (threat → mitigation)

| Threat | Primary mitigation(s) |
|---|---|
| **Cross-tenant data leak** (#1) | Mandatory `workspace_id` tenant guard at repository layer; cross-tenant negative tests; fail-closed on missing scope (§4) |
| Broken authentication | Argon2id; secure/rotating sessions; MFA-ready; rate limiting & lockout (§2) |
| Broken authorization / privilege escalation | Deny-by-default permission checks at Application boundary; never branch on role names; UI hiding ≠ enforcement (§3) |
| SQL injection | Prepared statements only; static analysis; code review (§5) |
| Cross-site scripting (XSS) | Contextual output escaping on render; CSP (§5, §8) |
| Cross-site request forgery (CSRF) | Per-session CSRF tokens on all state-changing requests (§5) |
| Session hijacking/fixation | HttpOnly/Secure/SameSite cookies; ID regeneration on privilege change; server-side revocation (§2, §6) |
| Sensitive data exposure | Encryption at rest for sensitive fields & API keys; secrets in env; keys shown once (§6) |
| Malicious file upload | Type/size validation; store outside web root; randomized names; AV scan hook (§7) |
| Insecure transport | HTTPS/HSTS; security headers; single front controller (§8) |
| API/webhook abuse & replay | Scoped token auth; HMAC signature validation; replay/nonce protection; rotation (§9) |
| PII misuse | Permission-gated, audited, minimized, encrypted PII; audited exports (§10) |
| Vulnerable dependency | `composer audit` gate; minimal, pinned deps; `--no-dev` in prod (§11.1) |

---

### Related Documents
`PROJECT_CONSTITUTION.md` (§10) · `PERMISSION_MODEL.md` · `WORKSPACE_MODEL.md` ·
`ARCHITECTURE.md` · `DATABASE_ARCHITECTURE.md` · `MODULES.md` ·
`SECURITY_MATRIX.md` (Phase 4) · `ACCESS_POLICIES.md` (Phase 4) ·
`SYSTEM_PERMISSIONS.md` · `WORKSPACE_PERMISSIONS.md` · `AUDIT_EVENTS.md` (Phase 4) ·
`TESTING_GUIDE.md` · `DEPLOYMENT_GUIDE.md` · `OBSERVABILITY.md` (Phase 15)
