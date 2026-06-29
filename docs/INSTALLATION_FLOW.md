# INSTALLATION FLOW — HaHireAI

> **Status:** Adopted (Phase 6) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `INSTALLER_ARCHITECTURE.md`.

---

## 0. About This Document

This document specifies the **installation wizard as a state machine**: the exact
ordered steps a customer is walked through in the browser, with each step's
**purpose, entry criteria, validation/checks, what it writes, success handling,
failure handling, and exit criteria**. It is the step-level companion to
`INSTALLER_ARCHITECTURE.md` (the philosophy, bootstrap, streaming log,
configuration writing, migration/seed orchestration, owner creation, and
locking).

This is **documentation, not implementation** — design on paper. The canonical
ordered step list is fixed by `INSTALLATION.md` §2.2 and
`FEATURE_SPECIFICATIONS/Installer.md` §2; this document elaborates it into states.

Interpretation keywords (**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**,
**MAY**) follow RFC 2119, exactly as in `PROJECT_CONSTITUTION.md`.

---

## 1. The Steps (canonical order)

The wizard presents exactly these states, in this order
(`FEATURE_SPECIFICATIONS/Installer.md` §2):

1. **Welcome**
2. **Server Requirements**
3. **PHP Extensions**
4. **Permissions**
5. **Environment**
6. **Database**
7. **Connection Test**
8. **Run Migrations**
9. **Seed Core Data**
10. **Create First System Owner**
11. **Platform Settings**
12. **Health Check**
13. **Finish**

> Steps 2–3 together realize the "Server & Extensions check" of
> `INSTALLATION.md` §2.2; this document splits them into two states for clearer
> diagnostics. The ordered set is otherwise identical to the canon.

---

## 2. ASCII State Flow

```
            ┌─────────────────────────────────────────────────────────────┐
            │  (front controller: NOT installed → route to Setup)          │
            │  INSTALLER_ARCHITECTURE.md §2                                 │
            └───────────────────────────────┬─────────────────────────────┘
                                             ▼
   ┌───────────┐   ┌──────────────────┐   ┌────────────────┐   ┌──────────────┐
   │ 1 Welcome │──▶│ 2 Server Reqs    │──▶│ 3 PHP Exts     │──▶│ 4 Permissions│
   └───────────┘   └──────────────────┘   └────────────────┘   └──────┬───────┘
                          ▲  fail: block & guide (re-check)            │ pass
                          └────────────────────────────────────────┐  │
                                                                    │  ▼
   ┌──────────────┐   ┌──────────────┐   ┌──────────────────┐   ┌──────────────┐
   │ 7 Conn. Test │◀──│ 6 Database   │◀──│ 5 Environment    │◀──┘             │
   └──────┬───────┘   └──────────────┘   └──────────────────┘                 │
          │ pass            ▲ fail: correct credentials & retry               │
          ▼                 └──────────────────────────────────────────────  │
   ┌──────────────────┐  EXECUTION PHASE (live setup log streams — §arch 4)   │
   │ 8 Run Migrations │───────┐                                               │
   └──────────────────┘       │ fail: report, resume (idempotent)            │
          │ pass              ▼                                               │
          ▼            ┌──────────────────┐                                  │
   ┌──────────────────┐│ 9 Seed Core Data │                                  │
   │ (ledgered, fwd-  │└────────┬─────────┘                                  │
   │  only, from zero)│         │ pass                                       │
   └──────────────────┘         ▼                                            │
   ┌────────────────────────┐  ┌────────────────────┐                       │
   │ 10 Create First System │  │ 11 Platform        │                       │
   │    Owner (Argon2id)    │─▶│    Settings        │                       │
   └────────────────────────┘  └─────────┬──────────┘                       │
                                          ▼                                  │
                                ┌──────────────────┐                        │
                                │ 12 Health Check  │                        │
                                └────────┬─────────┘                        │
                              fail: report│ pass                            │
                              & re-run     ▼                                │
                                ┌──────────────────┐                        │
                                │ 13 Finish        │  write LOCK MARKER     │
                                │  → lock & redirect│  (§arch 8)            │
                                └────────┬─────────┘                        │
                                         ▼                                  │
                                 ┌───────────────┐                         │
                                 │  LOGIN page    │ ◀───────────────────────┘
                                 │ (installer now │   (installed → any installer
                                 │  unreachable)  │    route resolves to Login)
                                 └───────────────┘
```

**Reading the diagram.** Steps 1–7 are **pre-flight + configuration** (no schema
writes). Steps 8–12 are the **execution phase**, narrated by the live setup log
(`INSTALLER_ARCHITECTURE.md` §4). Step 13 writes the **lock marker**, after which
the installer is unreachable and visitors land on **Login**.

---

## 3. Cross-Cutting Rules (apply to every step)

- **Server-side validation gates advance.** Each step **MUST** validate
  server-side before the wizard allows the next; the client is never trusted
  (`FEATURE_SPECIFICATIONS/Installer.md` §9; `SECURITY_GUIDE.md` §5).
- **Human-readable failures.** Any failure **MUST** show clear, actionable
  guidance, never a stack trace or secret (`UI_GUIDELINES.md` §8;
  `INSTALLER_ARCHITECTURE.md` §9).
- **No manual step.** No step ever asks the customer to use SSH, a terminal,
  Composer, Artisan, or manual SQL, or to hand-edit a file (§6 below;
  `INSTALLATION.md` §2; `INSTALLER_ARCHITECTURE.md` §1).
- **CSRF + prepared statements.** Every submission carries a CSRF token; all DB
  access uses prepared statements (`SECURITY_GUIDE.md` §5).
- **Fail closed.** No step writes the lock marker; only Finish does
  (`INSTALLER_ARCHITECTURE.md` §8.1). A failure never yields a state that falsely
  reads as installed.

---

## 4. Step Specifications

Each step is given as: **Purpose · Entry criteria · Checks/validation · Writes ·
Success → exit · Failure handling.**

### Step 1 — Welcome

- **Purpose.** Introduce the zero-touch process and prerequisites; set
  expectations (`INSTALLATION.md` §2.2 step 1).
- **Entry criteria.** Platform **not installed** (lock marker absent); the front
  controller routed the visitor here (`INSTALLER_ARCHITECTURE.md` §2).
- **Checks.** None beyond confirming not-installed.
- **Writes.** Nothing.
- **Success → exit.** Customer acknowledges and proceeds to **Server
  Requirements**.
- **Failure handling.** If the platform is already installed, Welcome is never
  shown — routing sends the visitor to **Login** (`INSTALLER_ARCHITECTURE.md`
  §8.3).

### Step 2 — Server Requirements

- **Purpose.** Verify the runtime baseline the platform requires.
- **Entry criteria.** Welcome acknowledged.
- **Checks.** **PHP 8.3+** (`INSTALLATION.md` §1.1); web server able to route all
  traffic to the single front controller; web root expected at `/public`
  (`PROJECT_STRUCTURE.md` §1). The step reports each check as pass/fail.
- **Writes.** Nothing (read-only inspection).
- **Success → exit.** All mandatory checks pass → **PHP Extensions**.
- **Failure handling.** Block advance; list exactly what is wrong (e.g. "PHP 8.1
  detected; 8.3+ required") with guidance. The step is **re-checkable** — the
  customer fixes the host and re-runs the check without losing progress.

### Step 3 — PHP Extensions

- **Purpose.** Confirm every required PHP extension is loaded.
- **Entry criteria.** Server Requirements passed.
- **Checks.** Presence of the **required** extensions (`INSTALLATION.md` §1.2):
  `pdo_mysql`, `mbstring`, `openssl`, `json`, `ctype`, `fileinfo`, `curl`,
  `tokenizer`, `pcre`. **SHOULD**-level extensions (`intl`, `zip`) are reported
  as recommendations, not hard blocks. The authoritative, version-pinned list is
  owned by the Installer module (`INSTALLATION.md` §1.2 note).
- **Writes.** Nothing.
- **Success → exit.** All **MUST** extensions present → **Permissions**.
- **Failure handling.** Block advance; name each missing extension and how it is
  used (e.g. "`pdo_mysql` missing — required for database access"). Re-checkable.

### Step 4 — Permissions

- **Purpose.** Confirm the file system is writable where the platform needs it,
  **before** anything is written (`INSTALLER_ARCHITECTURE.md` §5.2).
- **Entry criteria.** PHP Extensions passed.
- **Checks.** Writable target for **`.env`** (project root) and a writable
  **`/storage`** tree (logs, cache, compiled views, uploads —
  `PROJECT_STRUCTURE.md` §2). The step probes actual writability, not just
  existence.
- **Writes.** Nothing persistent (a probe write is cleaned up immediately).
- **Success → exit.** All paths writable → **Environment**.
- **Failure handling.** Block advance; list each non-writable path with the
  permission it needs. Re-checkable after the customer adjusts permissions via
  their host panel — **no terminal required**.

### Step 5 — Environment

- **Purpose.** Collect environment configuration; the installer will write `.env`
  on the customer's behalf (`INSTALLATION.md` §2.2 step 4;
  `INSTALLER_ARCHITECTURE.md` §5).
- **Entry criteria.** Permissions passed.
- **Checks.** Validate collected values server-side (app name, app URL,
  environment = production/staging). Application/signing keys are **generated**,
  not requested. Argon2id parameters are captured/defaulted for the host
  (`SECURITY_GUIDE.md` §2.1).
- **Writes.** Holds the validated values for the atomic `.env` write performed
  with the Database step's values (`INSTALLER_ARCHITECTURE.md` §5.3). Secrets go
  to `.env` only, never the repository (`SECURITY_GUIDE.md` §6.2).
- **Success → exit.** Valid values captured → **Database**.
- **Failure handling.** Re-display the form with field-level messages on invalid
  input; nothing is written until values are valid.

### Step 6 — Database

- **Purpose.** Collect MySQL connection details (`INSTALLATION.md` §2.2 step 5).
- **Entry criteria.** Environment captured.
- **Checks.** Validate shape of host, port, database name, username, password
  (presence/format). MySQL **8+** is the required engine (`DATABASE_GUIDE.md` §2);
  actual connectivity is proven in the next step, not here.
- **Writes.** Held together with Environment values for the atomic `.env` write
  (`INSTALLER_ARCHITECTURE.md` §5.1).
- **Success → exit.** Well-formed credentials captured → **Connection Test**.
- **Failure handling.** Field-level validation messages; no connection attempt
  until the form is well-formed.

### Step 7 — Connection Test

- **Purpose.** Prove database connectivity **and privileges before any write**
  (`FEATURE_SPECIFICATIONS/Installer.md` §4; `INSTALLATION.md` §2.2 step 6).
- **Entry criteria.** Database credentials captured.
- **Checks.** Open a PDO connection with prepared statements; confirm the target
  database is reachable and the credentialed user has the privileges migrations
  require (`SECURITY_GUIDE.md` §5; `DATABASE_GUIDE.md` §6.2). This is the **last
  gate before writes begin**.
- **Writes.** On success, the validated configuration is committed to `.env`
  **atomically** (`INSTALLER_ARCHITECTURE.md` §5.3). (Writing `.env` does **not**
  mark the platform installed — `INSTALLER_ARCHITECTURE.md` §2.2.)
- **Success → exit.** Connection + privileges verified → **Run Migrations**;
  `installer.installation.started` may be emitted as the execution phase begins
  (`FEATURE_SPECIFICATIONS/Installer.md` §7).
- **Failure handling.** Report a **safe** connection error (e.g. "could not
  connect / access denied for user") with guidance; return the customer to the
  Database step to correct credentials and retry. Raw driver errors and secrets
  are never shown (`INSTALLER_ARCHITECTURE.md` §9).

### Step 8 — Run Migrations

- **Purpose.** Build the schema from zero via the bespoke migration engine
  (`INSTALLER_ARCHITECTURE.md` §6.2; `DATABASE_GUIDE.md` §12).
- **Entry criteria.** Connection Test passed; `.env` written.
- **Checks.** Invoke the Database engine's **run pending** operation
  (`system.migrations.run`, Platform Context / Installer —
  `FEATURE_SPECIFICATIONS/Database.md` §6). Application is **forward-only,
  ordered, idempotent, ledgered** (identifier, applied-at, batch/version,
  checksum — `FEATURE_SPECIFICATIONS/Database.md` §8). The full set **MUST build a
  correct schema from an empty database** (`DATABASE_GUIDE.md` §12).
- **Writes.** Database schema + migrations ledger rows (owned by the **Database**
  module, not the installer — `FEATURE_SPECIFICATIONS/Installer.md` §8).
- **Live log.** Streams `Running Migrations… / Creating Tables…` lines and per-
  migration `OK` results (`INSTALLER_ARCHITECTURE.md` §4.1); emits
  `installer.step.completed`.
- **Success → exit.** All pending migrations applied → **Seed Core Data**.
- **Failure handling.** Report `FAILED` with safe guidance; the lock marker is
  **not** written. **Resumable** (§5): because application is idempotent and
  ledgered, re-running applies only the pending remainder.

### Step 9 — Seed Core Data

- **Purpose.** Install required baseline data — principally the **Permission
  Catalog** (`INSTALLATION.md` §2.2 step 8; `PERMISSION_CATALOG.md`).
- **Entry criteria.** Migrations applied.
- **Checks.** Seed the Permission Catalog (`PERMISSION_MODEL.md` §2). The product
  ships **zero default roles** (`PERMISSION_MODEL.md` §3); the installer creates
  **no** roles, demo data, or sample workspaces. Seeding is idempotent and SHOULD
  run within transaction semantics (`INSTALLER_ARCHITECTURE.md` §6.3).
- **Writes.** Permission Catalog rows (owned by the **Permissions** module).
- **Live log.** Streams `Seeding Core Data (Permission Catalog)… OK`.
- **Success → exit.** Catalog seeded → **Create First System Owner**.
- **Failure handling.** Report `FAILED`, no lock; resumable (idempotent re-seed).

### Step 10 — Create First System Owner

- **Purpose.** Create the first (and only) account — the **first System Owner**
  (`INSTALLATION.md` §2.2 step 9; `INSTALLER_ARCHITECTURE.md` §7).
- **Entry criteria.** Permission Catalog seeded (so `system.*` keys exist to
  grant).
- **Checks.** Collect and validate name, email, password (password meets the
  configured policy — `SECURITY_GUIDE.md` §2.4). Create a single **`User`**
  (`USER_MODEL.md` §1), hash the password with **Argon2id**
  (`SECURITY_GUIDE.md` §2.1), and grant **`system.*`**, which is what *makes* the
  user a System Owner (`USER_MODEL.md` §2, §5; Invariant 3). **Idempotency
  guard:** if a System Owner already exists (resumed run), do not create a second
  (`INSTALLER_ARCHITECTURE.md` §7.3; `USER_MODEL.md` Invariant 1).
- **Writes.** One `User` + the `system.*` grant (owned by **Users** /
  **Permissions**). **No** workspace, **no** other users
  (`INSTALLER_ARCHITECTURE.md` §7.2).
- **Live log.** Streams `Creating System Owner… OK`. The plaintext password is
  never logged or echoed.
- **Success → exit.** First System Owner created → **Platform Settings**.
- **Failure handling.** Field-level messages on invalid input; on a partial
  failure the step is re-runnable under the idempotency guard.

### Step 11 — Platform Settings

- **Purpose.** Capture global defaults for the platform (`INSTALLATION.md` §2.2
  step 10).
- **Entry criteria.** First System Owner created.
- **Checks.** Validate platform name, **default language** for the bilingual
  AR/EN UI (`UI_GUIDELINES.md` §6), and timezone. These are **global** settings;
  **workspace-level settings remain per workspace** (`WORKSPACE_MODEL.md` §4) and
  are **not** set here.
- **Writes.** Global platform/settings values (persisted via the **Settings**
  module where available — `FEATURE_SPECIFICATIONS/Installer.md` §5).
- **Success → exit.** Settings captured → **Health Check**.
- **Failure handling.** Re-display with field-level messages; nothing partial is
  committed.

### Step 12 — Health Check

- **Purpose.** Confirm the installation is sound before declaring success
  (`INSTALLATION.md` §2.2 step 11).
- **Entry criteria.** Platform settings captured.
- **Checks.** Invoke the Core Kernel **Health Checker**, running all registered
  probes (e.g. the Database connection/migration probe —
  `FEATURE_SPECIFICATIONS/Database.md`; `FEATURE_SPECIFICATIONS/Core_Kernel.md`)
  and aggregate the result. The installer **invokes** the probes; it does not
  implement them (`FEATURE_SPECIFICATIONS/Installer.md` §5).
- **Writes.** Nothing persistent (a Setup Log Entry records the outcome).
- **Live log.** Streams `Health Check… OK`.
- **Success → exit.** Aggregate health is sound → **Finish**.
- **Failure handling.** Report which probe failed (safely) with guidance; block
  Finish until healthy. The step is **re-runnable** without redoing earlier
  steps; the lock marker is still not written.

### Step 13 — Finish

- **Purpose.** Complete installation, **self-lock**, and redirect to **Login**
  (`INSTALLATION.md` §2.3; `INSTALLER_ARCHITECTURE.md` §8).
- **Entry criteria.** Health Check passed (and thus every prior step succeeded).
- **Checks.** Confirm the full success precondition (schema present, Permission
  Catalog seeded, first System Owner present, settings captured, health sound).
- **Writes.** The **install lock marker** — written **only here**, as the final
  action (`INSTALLER_ARCHITECTURE.md` §8.1). This flips install-state detection
  to "installed".
- **Live log.** Streams the terminal `Completed.` line
  (`INSTALLER_ARCHITECTURE.md` §4.1).
- **Events.** Emits `installer.installation.completed` then
  `installer.installation.locked` (`FEATURE_SPECIFICATIONS/Installer.md` §7).
- **Success → exit.** Installer **locks itself**, **refuses any re-run**, and the
  customer is **redirected to Login** (`INSTALLER_ARCHITECTURE.md` §8.2–§8.3).
- **Failure handling.** If the final precondition check fails, the lock is **not**
  written and the customer is returned to the failing step; the installer remains
  in the not-installed state and resumable.

---

## 5. Resumability on Failure

The flow is designed so a failure in the execution phase (steps 8–12) is
**recoverable without starting over** (`INSTALLER_ARCHITECTURE.md` §6.4):

- **The lock marker is written only at Finish** (`INSTALLER_ARCHITECTURE.md`
  §8.1). Until then the platform stays **not installed**, so re-opening the site
  routes back to the wizard rather than to Login.
- **Idempotent, ledgered execution.** Migrations re-apply only the pending
  remainder (the ledger records what is done — `DATABASE_GUIDE.md` §12); seeding
  re-runs safely; the first-System-Owner step is guarded so no duplicate account
  is created (`INSTALLER_ARCHITECTURE.md` §7.3).
- **Atomic configuration.** `.env` is written atomically (temp-file + rename), so
  an interrupted write never leaves a corrupt file to recover from
  (`INSTALLER_ARCHITECTURE.md` §5.3).
- **Persisted setup log.** The append-only Setup Log Entries survive a refresh,
  so the customer can see where the run stopped and resume the watch
  (`INSTALLER_ARCHITECTURE.md` §4.2).
- **Pre-flight steps are pure re-checks.** Steps 2–4 inspect only; re-running them
  after fixing the host is free and loses no progress.

> A failed run therefore resumes from the failing step on the next visit; it does
> **not** require deleting the database or files, and it never requires a terminal.

---

## 6. The "No Manual Step" Guarantee (per step)

Every step is reachable and completable **in the browser**; none delegates to a
shell or hand-editing (`INSTALLATION.md` §2; `INSTALLER_ARCHITECTURE.md` §1):

| Step | What the customer does | What is automated for them |
|---|---|---|
| 1 Welcome | Click continue | — |
| 2 Server Reqs | Read results, fix host via panel, re-check | PHP/server inspection |
| 3 PHP Extensions | Read results, enable via panel, re-check | Extension detection |
| 4 Permissions | Adjust folder permissions via panel, re-check | Writability probing |
| 5 Environment | Fill a form | Key generation; `.env` authoring |
| 6 Database | Fill a form | Credential validation |
| 7 Connection Test | Click test | PDO connect + privilege check + atomic `.env` write |
| 8 Run Migrations | Watch the log | Bespoke engine builds schema from zero |
| 9 Seed Core Data | Watch the log | Permission Catalog seeded |
| 10 First System Owner | Fill a form | `User` created, Argon2id hash, `system.*` granted |
| 11 Platform Settings | Fill a form | Global settings persisted |
| 12 Health Check | Watch the log | Health Checker probes invoked |
| 13 Finish | See "Completed", land on Login | Lock written, installer disabled, redirect |

No row requires SSH, Terminal, Composer, Artisan, or manual SQL.

---

## 7. Self-Review (Phase 6 gate)

- [ ] Steps appear in the exact canonical order (§1) and match
      `FEATURE_SPECIFICATIONS/Installer.md` §2.
- [ ] Each step **validates server-side before advancing** and shows
      human-readable failures (§3, §4).
- [ ] No write occurs before **Connection Test** passes; `.env` is written
      atomically there (§4 step 7).
- [ ] Migrations are **forward-only, idempotent, ledgered, from zero**; seed
      installs the Permission Catalog with **zero default roles** (§4 steps 8–9).
- [ ] **Exactly one** account (first System Owner, Argon2id, `system.*`) is
      created; **no** workspace/other users (§4 step 10).
- [ ] The **lock marker is written only at Finish**, which then **refuses re-run**
      and **redirects to Login** (§4 step 13).
- [ ] The flow is **resumable** after a failure with no terminal and no data
      wipe (§5).
- [ ] Every step honors the **no-manual-step** guarantee (§6).

---

### Related Documents
`INSTALLER_ARCHITECTURE.md` · `INSTALLATION.md` · `FEATURE_SPECIFICATIONS/Installer.md` ·
`FEATURE_SPECIFICATIONS/Database.md` · `FEATURE_SPECIFICATIONS/Core_Kernel.md` ·
`DATABASE_GUIDE.md` · `USER_MODEL.md` · `PERMISSION_MODEL.md` ·
`PERMISSION_CATALOG.md` · `WORKSPACE_MODEL.md` · `SECURITY_GUIDE.md` ·
`UI_GUIDELINES.md` · `PROJECT_STRUCTURE.md` · `PROJECT_CONSTITUTION.md`
