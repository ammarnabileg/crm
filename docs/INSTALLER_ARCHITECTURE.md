# INSTALLER ARCHITECTURE — HaHireAI

> **Status:** Adopted (Phase 6) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `INSTALLATION.md`, `FEATURE_SPECIFICATIONS/Installer.md`.

---

## 0. About This Document

This document specifies the **architecture of the zero-touch installer** — the
Installer module (Foundation layer, `MODULES.md`) that turns an uploaded release
into a running platform entirely in the browser. It is the Phase-6 design that
`INSTALLATION.md` §2 promises and that `FEATURE_SPECIFICATIONS/Installer.md`
scopes; it is **implemented in Phase 8**.

This is **documentation, not implementation** — the design on paper. The
step-by-step wizard states (entry/exit criteria, per-step validation, the ASCII
state flow, resumability) live in the companion `INSTALLATION_FLOW.md`; this
document covers the **philosophy, bootstrap, streaming setup log, configuration
writing, migration/seed orchestration, first-System-Owner creation, post-install
locking, and installer security**.

Interpretation keywords (**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**,
**MAY**) follow RFC 2119, exactly as in `PROJECT_CONSTITUTION.md`. Where this
document and the Constitution ever disagree, the **Constitution wins**.

---

## 1. The Zero-Touch Philosophy

The headline promise of `INSTALLATION.md` §2 is binding architecture, not a
slogan: **upload the files, open the site, follow a wizard to a working
platform.** The end customer (Audience A, `INSTALLATION.md` §0) **MUST NOT** be
required to use any of the following at any point:

- **No SSH / no Terminal.** Nothing is run from a shell on the customer's host.
- **No Composer.** The release ships with `vendor/` already built; the customer
  never runs `composer install`. Composer is a developer-only tool
  (`INSTALLATION.md` §3; Constitution §5).
- **No Artisan / no framework CLI.** HaHireAI has no framework and therefore no
  framework console (`ARCHITECTURE.md` §1). Migrations and seeds are driven by
  the installer through the bespoke engine, not a CLI the customer invokes.
- **No manual SQL.** The customer never opens phpMyAdmin or a MySQL client to
  create tables; the migration engine builds the schema from zero (§6).
- **No manual config editing.** The customer never hand-edits `.env`,
  `config/*.php`, or `.htaccess`; the installer writes configuration for them
  (§5).

Everything in that list happens **in the browser**, driven by the front
controller and the installer's own minimal bootstrap. The customer's entire
contribution is: upload, point the web root at `/public`, and answer a wizard.

### 1.1 Design tenets

| # | Tenet | Consequence in this architecture |
|---|---|---|
| 1 | **Browser is the only interface** | Every privileged setup action is exposed as a wizard step over HTTP, never as a shell command. |
| 2 | **Fail safe, fail closed** | A failed step never leaves a half-written `.env` or a partial schema that masquerades as "installed" (§5.3, §6.3). |
| 3 | **One-time, self-disabling** | The installer is a single-use surface that **locks itself** and refuses re-entry once installed (§8). |
| 4 | **Minimal footprint** | The installer is the *orchestrator*; it borrows the migration engine, Health Checker, and hashing from their owning modules and adds almost no state of its own (`FEATURE_SPECIFICATIONS/Installer.md` §8). |
| 5 | **Leak nothing** | No secrets, stack traces, or internal detail ever reach the browser (Constitution §10; `SECURITY_GUIDE.md` §6.2). |

---

## 2. Install-State Detection & Routing

The platform must decide, on **every** request before the application is
configured, whether it is *installed* or *not installed*, and route accordingly.

### 2.1 The decision

The front controller (`public/index.php`) consults the install state as one of
its earliest actions (`ARCHITECTURE.md` §7). The state is determined by an
**install lock marker** (§8.1), not by guessing:

```
┌────────────────────────┐
│  public/index.php       │
│  (single front control) │
└───────────┬────────────┘
            │  is the platform installed?  (check lock marker)
            ▼
   ┌────────────────┐         NOT installed         ┌────────────────────┐
   │  Lock marker?  │ ───────────────────────────▶  │  Installer Bootstrap │
   │                │                                │  → Setup wizard      │
   └───────┬────────┘                                └────────────────────┘
           │  installed
           ▼
   ┌────────────────────────────┐
   │  Normal Application Kernel   │
   │  boot → Router → Login etc.  │
   └────────────────────────────┘
```

- When **not installed**, the front controller **MUST** hand off to the
  installer bootstrap (§3) and route every request to **Setup**. Deep links to
  application routes (e.g. `/dashboard`) **MUST** redirect to Setup, not 404.
- When **installed**, the front controller boots the normal Application Kernel
  and the installer is **unreachable** — its routes resolve to **Login** (§8.3).

### 2.2 What "not installed" means

The platform is considered **not installed** when the install lock marker is
absent. The marker is written **only** at successful Finish (§8.1). A present
`.env`, a reachable database, or some applied migrations do **NOT** on their own
mean "installed"; the **lock marker is the single authority**. This makes
detection deterministic and lets a failed run resume (see
`INSTALLATION_FLOW.md`, "Resumability") rather than dead-end.

---

## 3. The Installer Bootstrap (runs before the app is configured)

The installer faces a chicken-and-egg problem: it must run **before** there is a
valid `.env`, a database connection, a session store, or registered business
modules. It therefore uses its **own minimal bootstrap**, distinct from the full
application boot in `ARCHITECTURE.md` §7.

### 3.1 What the installer bootstrap MAY rely on

- **Autoloading** and the error handler (so failures are caught, never leaked).
- The **Core Kernel** primitives it genuinely needs: Configuration/Environment
  loaders (to *write* and re-read `.env`), the Logger, the Router/Request/
  Response, and the Health Checker (`ARCHITECTURE.md` §6).
- The **Database** module's connection manager and **migration engine**, invoked
  only after the Connection Test step proves credentials (§6).
- The **Shared Kernel** ULID helper for identifier generation (`DATABASE_GUIDE.md`
  §3).

### 3.2 What the installer bootstrap MUST NOT assume

- **A valid `.env`.** Environment values may be empty or partial until the
  Environment step writes them (§5). The bootstrap MUST degrade gracefully and
  MUST NOT crash on missing keys.
- **A database connection.** No query runs until the Connection Test passes
  (§6.1); the bootstrap MUST NOT open a connection eagerly.
- **A session/authenticated user.** Installation runs **before any user or
  session exists** (`FEATURE_SPECIFICATIONS/Installer.md` §6), so the wizard is
  **not** gated by user permissions — it is gated by **install-state + lock**
  (§8) and a CSRF token (§9).
- **Registered business modules.** Only Foundation services are available; the
  full module registry is a property of the installed application.

### 3.3 Bootstrap responsibilities

1. Confirm the install state (§2) and refuse to run if already locked (§8.2).
2. Establish a minimal, hardened HTTP surface for the wizard (CSRF, security
   headers per `SECURITY_GUIDE.md` §8, no debug output).
3. Provide the step orchestrator that drives `INSTALLATION_FLOW.md`'s state
   machine and emits the live setup log (§4).
4. Expose only the installer routes; everything else routes to Setup.

---

## 4. The Live In-Browser Setup Log (terminal-like streaming)

A defining feature of the zero-touch experience is a **live setup log**: a
terminal-like panel in the browser that streams each step's progress as it runs,
so the customer sees real activity without ever opening a real terminal
(`FEATURE_SPECIFICATIONS/Installer.md` §2, §4).

### 4.1 What it shows

During the execution phases (migrations, seed, owner creation, configuration,
health check), the log streams human-readable lines in order, for example:

```
[ 09:31:02 ]  Running Migrations…
[ 09:31:02 ]    → applied 0001_create_users_table            OK
[ 09:31:03 ]    → applied 0002_create_permissions_catalog    OK
[ 09:31:04 ]  Creating Tables…                               OK
[ 09:31:05 ]  Seeding Core Data (Permission Catalog)…        OK
[ 09:31:06 ]  Creating System Owner…                         OK
[ 09:31:07 ]  Generating Configuration (.env)…               OK
[ 09:31:08 ]  Health Check…                                  OK
[ 09:31:08 ]  Completed.
```

These messages **MUST** be safe, high-level status lines. They **MUST NOT**
contain secrets (DB password, app keys), full stack traces, raw SQL, or file
system paths beyond what is necessary (Constitution §10; `SECURITY_GUIDE.md`
§6.2). A failure is surfaced as a clear, human-readable line plus remediation
guidance (`UI_GUIDELINES.md` §8) — never a leaked exception.

### 4.2 How it streams (design intent)

- The log is an **append-only** stream of **Setup Log Entry** records, one of the
  only two pieces of state the Installer owns (`FEATURE_SPECIFICATIONS/Installer.md`
  §8). Each entry records the step, an outcome (`OK` / `FAILED` / `SKIPPED`), a
  timestamp, and a safe message.
- The browser receives entries incrementally (e.g. server-sent streaming or
  short polling against an installer status endpoint). The transport is an
  implementation choice for Phase 8; this document fixes that the experience
  **MUST** be **incremental and ordered**, not a single blocking page that
  appears only after everything finishes.
- Each completed step emits the `installer.step.completed` event (carrying the
  step name); the run lifecycle emits `installer.installation.started`,
  `installer.installation.completed`, and `installer.installation.locked`
  (`FEATURE_SPECIFICATIONS/Installer.md` §7). The setup log is the customer-facing
  projection of those events.
- Because long operations can exceed a single request, the streaming model
  **SHOULD** allow the customer to reconnect and continue watching an in-progress
  run, and **MUST** survive a page refresh by replaying the persisted log
  entries.

---

## 5. Writing the Configuration (`.env`)

The customer never edits files by hand (§1). The **Environment** step collects
configuration and the installer **writes it on the customer's behalf**
(`INSTALLATION.md` §2.2 step 4; `FEATURE_SPECIFICATIONS/Installer.md` §4).

### 5.1 What is written, and where

- The installer writes a valid **`.env`** at the project root, populated from the
  Environment and Database steps (app name/URL/environment, DB host/port/name/
  credentials, generated application/signing keys, Argon2id parameters).
- Generated secrets (application key, signing secrets) are created with a
  cryptographically secure generator (`openssl`, `INSTALLATION.md` §1.2) and
  written **only** to `.env`.
- **Secrets live in the environment, never in the repository** (Constitution §5,
  §10; `SECURITY_GUIDE.md` §6.2). `.env` is git-ignored; `config/*.php` remains
  declarative and secret-free (`PROJECT_STRUCTURE.md` §5). The installer **MUST
  NOT** write secrets into `config/`, the database, or the setup log.

### 5.2 Pre-flight for writing

Before writing, the **Permissions** step (`INSTALLATION_FLOW.md`) confirms the
target paths are writable (e.g. project root for `.env`, `/storage` for logs,
cache, compiled views, uploads — `PROJECT_STRUCTURE.md` §2). If a path is not
writable, the installer **MUST** stop with clear guidance and **MUST NOT**
proceed to a partial write.

### 5.3 Atomicity & fail-safety

- Configuration writing **MUST** be **atomic**: write to a temporary file and
  rename into place, so an interrupted write never yields a corrupt `.env`.
- A failed write **MUST** be reported and **MUST NOT** advance the wizard; on
  retry the step is re-runnable (idempotent — overwriting the same target with
  the same intended content is safe).
- Writing `.env` does **NOT** mark the platform installed; only the Finish lock
  marker does (§2.2, §8.1). This is what allows a resume after a later failure.

---

## 6. Running Migrations + Seed via the Bespoke Engine

The installer **drives** schema creation and baseline data; it does **not**
contain a migration engine of its own. The engine is owned by the **Database**
module (`FEATURE_SPECIFICATIONS/Database.md`; `DATABASE_GUIDE.md` §12).

### 6.1 Connection test first (no write before proof)

The **Connection Test** step verifies database connectivity **and privileges
before any write** (`FEATURE_SPECIFICATIONS/Installer.md` §4; `INSTALLATION.md`
§2.2 step 6). All access uses PDO prepared statements (`SECURITY_GUIDE.md` §5;
`DATABASE_GUIDE.md` §6.2). Only after this passes does the installer open a
working connection for migrations.

### 6.2 Migrations (build-from-zero)

- The installer invokes the bespoke **migration engine** to apply the full,
  **forward-only** migration set against the (empty) database. This is the
  scenario the engine is explicitly built for: **"runs cleanly from zero"**
  (`DATABASE_GUIDE.md` §12; `FEATURE_SPECIFICATIONS/Database.md` acceptance
  criteria), exercised by the Installer and CI.
- Application is **ordered, idempotent, and ledgered**: each module ships its
  migrations under its `Database/` folder; a global runner orchestrates them in
  dependency order; applied migrations are recorded in the **migrations ledger**
  (identifier, applied-at, batch/version, checksum) so re-running applies only
  what is pending (`DATABASE_GUIDE.md` §12; `FEATURE_SPECIFICATIONS/Database.md`
  §8). The privileged operation is `system.migrations.run` (Platform Context /
  Installer — `FEATURE_SPECIFICATIONS/Database.md` §6).
- The migration engine — and not the installer — performs `create/alter/drop`;
  the installer issues the **run pending** command and streams progress to the
  setup log (§4).

### 6.3 Seed core data

- The **Seed** step installs required baseline data — principally the
  **Permission Catalog** (`PERMISSION_CATALOG.md`; `PERMISSION_MODEL.md` §2).
- The product ships **zero default roles** (`PERMISSION_MODEL.md` §3); the
  installer **MUST NOT** create example roles, demo workspaces, or sample data.
- Seeding **MUST** be idempotent and **SHOULD** run within the transaction
  semantics provided by the Database module's transaction manager, so a failure
  rolls back cleanly rather than leaving a partial catalog.

### 6.4 Failure handling

If migrations or seed fail, the step is reported `FAILED` in the setup log with
safe guidance, the lock marker is **not** written, and the run is **resumable**:
because application is idempotent and ledgered, re-running re-applies only the
pending remainder (`INSTALLATION_FLOW.md`, "Resumability").

---

## 7. Creating the FIRST System Owner (and only that)

The installer creates **exactly one** account: the **first System Owner**. It
creates **no workspace and no other users** (`INSTALLATION.md` §2.2 step 9;
`FEATURE_SPECIFICATIONS/Installer.md` §4).

### 7.1 What is created

- A single **`User`** — the one-and-only human account type (`USER_MODEL.md` §1)
  — from the name, email, and password captured in the **Create First System
  Owner** step.
- The password is hashed with **Argon2id** (Constitution §10; `SECURITY_GUIDE.md`
  §2.1; `USER_MODEL.md` §3), using the Argon2id parameters written to `.env`
  (§5.1). The plaintext password is never logged, echoed to the setup log, or
  persisted anywhere but as its Argon2id hash.
- This `User` is granted **system-level permissions** (`system.*`) via the
  **Permissions** module, which is what *makes* a `User` a System Owner —
  **System Owner is a capability (a permission set on a `User`), not a separate
  table or account type** (`USER_MODEL.md` §2, §5; Invariant 3).

### 7.2 What is deliberately NOT created

- **No workspace.** Multi-tenant workspace provisioning is a post-install,
  in-product action (`FEATURE_SPECIFICATIONS/Installer.md` §2 "Out of scope"). A
  newly registered ordinary user is later offered Create/Join Workspace
  (`USER_MODEL.md` §5; `WORKSPACE_MODEL.md`), but the installer itself creates
  none.
- **No second user, no default roles, no sample data.** Only the first System
  Owner exists when installation finishes; all subsequent users self-register as
  plain `User`s (`USER_MODEL.md` §5).

### 7.3 Idempotency guard

The installer **MUST** guard against creating a duplicate first owner on a
resumed or replayed run (one human ⇒ one `User`, `USER_MODEL.md` Invariant 1): if
a System Owner already exists, the step is treated as already satisfied rather
than creating a second account.

---

## 8. Post-Install: Locking, Re-Run Prevention, Redirect

On successful **Finish** the installer **locks itself**, becomes unreachable, and
sends the customer to **Login** (`INSTALLATION.md` §2.3;
`FEATURE_SPECIFICATIONS/Installer.md` §2).

### 8.1 The install lock marker

- The Installer owns minimal state, including the **Install State** — the
  self-lock marker that gates re-runs (`FEATURE_SPECIFICATIONS/Installer.md` §8).
- The lock marker is written **only** as the final action of a fully successful
  run, **after** migrations, seed, the first System Owner, platform settings, and
  the health check have all succeeded. Writing it is what flips install-state
  detection (§2) from "not installed" to "installed".
- The marker **SHOULD** be robust to a single mechanism's loss: a deployment that
  can detect "schema present + System Owner present + lock recorded" treats the
  platform as installed. The **lock marker remains the authoritative signal**
  (§2.2); a present database alone never re-opens the installer.

### 8.2 Re-run prevention

- Once locked, **re-running setup on an installed platform MUST be refused**
  (`INSTALLATION.md` §2.3). The installer bootstrap (§3.3) checks the lock at the
  very start and aborts before doing any work.
- The installer **MUST NOT** expose any unauthenticated path to privileged
  operations once installed (`FEATURE_SPECIFICATIONS/Installer.md` §6). After
  lock, **all installer routes resolve to Login**, not Setup (§8.3).
- The only post-install installer surface is read-only visibility of the
  installation result, gated by the declared operational permission
  `system.install.view` in the Platform Context
  (`FEATURE_SPECIFICATIONS/Installer.md` §6).

### 8.3 Redirect to Login

On lock, the front controller's routing (§2.1) sends visitors to the normal
application: the post-install destination is **Login**, and the first System
Owner signs in with the credentials created in §7.

### 8.4 Lifecycle events at finish

Finish emits `installer.installation.completed` and then
`installer.installation.locked` (`FEATURE_SPECIFICATIONS/Installer.md` §7); the
setup log's terminal `Completed.` line (§4.1) corresponds to these events.

---

## 9. Security of the Installer

The installer runs **before** authentication exists, so it is a uniquely
sensitive surface. It inherits every relevant rule from `SECURITY_GUIDE.md` and
adds installer-specific protections.

| Control | Requirement |
|---|---|
| **Gated by state + lock** | The wizard is reachable **only** while not installed; the lock marker (§8.1) is the gate, not user permissions (`FEATURE_SPECIFICATIONS/Installer.md` §6). |
| **No re-entry** | After lock, the installer is refused and all its routes resolve to Login (§8.2; `INSTALLATION.md` §2.3). |
| **No leakage** | The installer **MUST NOT** expose secrets, stack traces, or internal detail in the browser or the setup log (Constitution §10; `ARCHITECTURE.md` §6; `SECURITY_GUIDE.md` §6.2). The error handler renders safe messages only. |
| **CSRF** | Every state-changing wizard submission (`POST`) **MUST** carry and validate a CSRF token (`SECURITY_GUIDE.md` §5). |
| **Server-side validation** | All inputs are validated server-side against an allow-list before use (`SECURITY_GUIDE.md` §5); the client is never trusted. |
| **Prepared statements** | All database access uses parameterized prepared statements (`SECURITY_GUIDE.md` §5; `DATABASE_GUIDE.md` §6.2). |
| **Secrets to env only** | Collected/generated secrets are written to `.env` only, never to the repository, `config/`, the database, or logs (§5.1; `SECURITY_GUIDE.md` §6.2). |
| **Security headers / HTTPS** | The installer surface sends the baseline security headers and is served over HTTPS in production like any response (`SECURITY_GUIDE.md` §8). |
| **Single front controller** | The installer is reached only through `public/index.php`; no other directory is web-exposed (`PROJECT_STRUCTURE.md` §1; `SECURITY_GUIDE.md` §8). |
| **Fail closed** | A failed step never produces a state that falsely reads as installed; the lock is written only on full success (§8.1). |

> **Operational note.** Because the pre-install window is the moment of maximum
> exposure (no users, privileged setup actions reachable), customers SHOULD
> complete installation promptly after upload, and the deployment SHOULD serve the
> site over HTTPS from the first request (`INSTALLATION.md` §1.1).

---

## 10. Boundaries — What the Installer Is and Is Not

| The Installer **is** | The Installer **is not** |
|---|---|
| An **orchestrator** of a one-time setup flow | The migration engine (owned by **Database**) |
| The **driver** of build-from-zero migrations + seed | The Permission Catalog or System Owner identity model (owned by **Permissions** / **Users**) |
| The **writer** of `.env` on the customer's behalf | The Health Checker (owned by the **Core Kernel**) |
| The **creator** of the first (and only) System Owner | A creator of workspaces, extra users, default roles, or sample data |
| **Self-locking** and single-use | A re-runnable admin tool after install |

The records the installer *causes* (the Permission Catalog, the first System
Owner `User`, platform settings, the migrations ledger) are owned by
**Permissions**, **Users**, **Settings**, and **Database** respectively — not by
the Installer (`FEATURE_SPECIFICATIONS/Installer.md` §8).

---

## 11. Self-Review (Phase 6 gate)

- [ ] Install-state detection routes uninstalled visitors to Setup and installed
      visitors to Login, keyed on the **lock marker** (§2).
- [ ] The installer bootstrap runs with **no valid `.env`, no DB, no session, no
      business modules** assumed (§3).
- [ ] The **live setup log** streams ordered, safe status lines and survives a
      refresh (§4).
- [ ] `.env` is written **atomically**, secrets to env only, never to the repo
      (§5).
- [ ] Migrations run **forward-only, idempotent, ledgered, from zero** via the
      Database engine; seed installs the Permission Catalog with **zero default
      roles** (§6).
- [ ] **Exactly one** account — the first System Owner — is created (Argon2id,
      `system.*`); **no** workspace/other users/sample data (§7).
- [ ] On Finish the installer **locks**, **refuses re-run**, and **redirects to
      Login** (§8).
- [ ] The installer **leaks nothing**, validates server-side, uses prepared
      statements + CSRF, and **fails closed** (§9).

---

### Related Documents
`INSTALLATION.md` · `INSTALLATION_FLOW.md` · `FEATURE_SPECIFICATIONS/Installer.md` ·
`ARCHITECTURE.md` · `PROJECT_STRUCTURE.md` · `MODULES.md` · `USER_MODEL.md` ·
`PERMISSION_MODEL.md` · `PERMISSION_CATALOG.md` · `DATABASE_GUIDE.md` ·
`FEATURE_SPECIFICATIONS/Database.md` · `FEATURE_SPECIFICATIONS/Core_Kernel.md` ·
`SECURITY_GUIDE.md` · `UI_GUIDELINES.md` · `PROJECT_CONSTITUTION.md`
