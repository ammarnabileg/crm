# FEATURE SPEC — Installer

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Installer · **Layer:** Foundation · **Implemented in:** Phase 8
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Installer delivers HaHireAI's **zero-touch, browser-based installation**: an
end customer uploads the release, points the web root at `/public`, opens the
site, and is walked through a wizard to a working platform — **with no SSH,
terminal, Composer, or manual SQL** (`INSTALLATION.md` §2). It verifies the
environment, writes configuration, tests the database, runs migrations, seeds
baseline data (the Permission Catalog), creates the **first System Owner**,
captures platform settings, runs a health check, then **self-locks** and
redirects to login. It is the customer-facing realization of the developer setup
in `INSTALLATION.md` §3.

## 2. Scope

**In scope**
- A guided, ordered wizard with per-step server-side validation and human-readable failure guidance.
- The steps: **Welcome → Server & Extensions check → Permissions → Environment → Database → Connection test → Run migrations → Seed → Create first System Owner → Platform settings → Health check → Finish**.
- Writing the `.env` file on the customer's behalf (customer never edits files by hand).
- A **live setup log** surfaced in the browser as the install progresses.
- **Self-lock** on successful finish; refusal to re-run against an installed system; redirect to **Login**.
- Pre-install routing: when the platform is not installed, the front controller routes visitors to **Setup**.

**Out of scope**
- The migration engine itself (provided by the **Database** module) — the Installer *drives* it.
- The Permission Catalog content and System Owner identity model (declared by **Permissions** / **Users**) — the Installer *seeds and creates* them.
- Health probes themselves (provided by the Core Kernel **Health Checker**) — the Installer *invokes* them.
- Developer/terminal setup, ongoing operations, backups, upgrades — `INSTALLATION.md` §3 and `DEPLOYMENT_GUIDE.md` / `OBSERVABILITY.md` (Phase 15).
- Multi-tenant provisioning of workspaces (a post-install, in-product action).

## 3. Inputs

- Customer-provided wizard input: writable-path confirmation, environment values, MySQL host/port/database/credentials, first System Owner details (name, email, password), and platform settings (platform name, default AR/EN language, timezone).
- The runtime environment: PHP version and the required extensions list (`INSTALLATION.md` §1.2).
- The platform's forward-only migration set and required seed data.
- The current install state (installed vs not-installed; lock marker).

## 4. Outputs

- A written, valid `.env` (secrets in environment, never in the repository — Constitution §5, §10).
- A migrated, current database schema (built from zero via the Database module).
- Seeded baseline data: the **Permission Catalog**; **zero** default roles (`PERMISSION_MODEL.md` §3).
- The **first System Owner** `User`, password hashed with **Argon2id**, granted `system.*` permissions.
- Captured platform/global settings.
- A health-check result confirming a sound install.
- An **install lock** that disables the wizard, and a redirect to **Login**.
- A live, append-only setup log of each step's outcome.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Core Kernel** (Foundation) — boot, configuration/environment writing surface, logger, **Health Checker**.
- **Database** (Foundation) — connection test, transaction manager, and the bespoke **migration engine** (`system.migrations.run`).
- **Permissions** (Identity & Access) — seeds the **Permission Catalog**; grants `system.*` to the first owner.
- **Users** (Identity & Access) — creates the first `User` (the first System Owner) with Argon2id hashing.
- **Settings** (Platform Services, where available) — persists platform/global settings; the Installer captures initial values.
- Consumes capabilities via **contracts** only; it does not reach into other modules' internals or tables.

## 6. Permissions (keys this module declares; resource.action grammar)

Installation runs **before** any user or session exists, so it is gated by the
**install-state and lock**, not by user permissions. The privileged operations it
performs are exercised against System-Owner-scoped keys it consumes from other
modules; the Installer **declares** only operational keys for post-install
visibility:

- `system.install.view` — view installation status/result after the fact (Platform Context).

> The Installer MUST NOT expose an unauthenticated path to privileged operations
> once installed; after lock, all routes resolve to Login (`INSTALLATION.md` §2.3).

## 7. Events (Published / Subscribed)

**Published**
- `installer.installation.started` — the wizard began against an uninstalled system.
- `installer.step.completed` — a wizard step validated and completed (carries step name).
- `installer.installation.completed` — installation finished successfully.
- `installer.installation.locked` — the installer self-locked post-install.

**Subscribed**
- None. The Installer orchestrates a one-time setup flow and does not react to other modules' domain events.

## 8. Data Owned (conceptual entities only — defer detail to DATABASE_ARCHITECTURE.md, Phase 3)

The Installer is primarily an orchestrator and owns minimal state:

- **Install State** — whether the platform is installed and **locked** (the self-lock marker that gates re-runs).
- **Setup Log Entry** — append-only records of each step's outcome for the live log.

The records it *causes* (the Permission Catalog, the first System Owner `User`,
platform settings, the migration ledger) are owned by **Permissions**, **Users**,
**Settings**, and **Database** respectively — not by the Installer.

## 9. Acceptance Criteria (checklist of testable outcomes)

- [ ] An end customer completes installation **entirely in the browser** with no SSH, terminal, Composer, or manual SQL (`INSTALLATION.md` §2).
- [ ] When the platform is not installed, the front controller routes visitors to **Setup** automatically.
- [ ] The wizard presents the steps in the exact order: Welcome → Server & Extensions → Permissions → Environment → Database → Connection test → Migrations → Seed → First System Owner → Platform settings → Health check → Finish.
- [ ] Each step **validates server-side before advancing** and shows clear, human-readable guidance on failure (`UI_GUIDELINES.md` §8).
- [ ] The Server & Extensions step verifies **PHP 8.3+** and every required extension; the Permissions step confirms writable paths (e.g. `/storage`).
- [ ] The Environment step writes a valid `.env`; the customer never edits files by hand and **no secrets are written to the repository**.
- [ ] The Connection test verifies database connectivity and privileges **before any write**.
- [ ] Migrations run via the **bespoke Database migration engine** and build the schema from zero; Seed installs the **Permission Catalog** with **zero default roles**.
- [ ] The first `User` is created and granted **system-level permissions** (`system.*`), becoming the **first System Owner**, with the password hashed using **Argon2id**.
- [ ] Platform/global settings (platform name, default AR/EN language, timezone) are captured; workspace-level settings remain per workspace.
- [ ] The Health check invokes the Core Kernel **Health Checker** and reports a sound install.
- [ ] A **live setup log** reflects each step's outcome in the browser.
- [ ] On Finish the installer **self-locks**, **refuses re-running** against an installed system, and **redirects to Login**.
- [ ] The installer **never exposes secrets, stack traces, or internal detail** in the browser (Constitution §10; `ARCHITECTURE.md` §6); all inputs are validated and use prepared statements.

### Related Documents
`INSTALLATION.md` · `INSTALLER_ARCHITECTURE.md` · `ARCHITECTURE.md` · `MODULES.md` ·
`USER_MODEL.md` · `PERMISSION_MODEL.md` · `UI_GUIDELINES.md` · `SECURITY_GUIDE.md` ·
`Core_Kernel.md` · `Database.md` · `Users.md` · `Permissions.md`
