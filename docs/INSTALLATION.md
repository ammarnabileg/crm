# INSTALLATION — HaHireAI

> **Status:** Draft (Phase 1) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Zero-touch installer:** Phase 6 (`INSTALLER_ARCHITECTURE.md`) & Phase 8.

---

## 0. About This Document

This document describes **how HaHireAI is installed**, for **two clearly separated
audiences**:

- **Audience A — End customers** who install the product. Their experience is a
  **zero-touch, browser-based install**: no SSH, no terminal, no Composer, no
  manual SQL. This is the **product goal** (§2).
- **Audience B — Developers/contributors** who set up the repository to build and
  modify HaHireAI. Their setup uses a terminal and standard tooling (§3).

This document **defers** to `PROJECT_CONSTITUTION.md` and is the Phase-1 overview.
The full design of the zero-touch installer (the **Installer** module — Foundation
layer, `MODULES.md`) is specified in **Phase 6** (`INSTALLER_ARCHITECTURE.md`) and
implemented in **Phase 8**. Interpretation keywords (**MUST**, **MUST NOT**,
**SHOULD**, **SHOULD NOT**, **MAY**) follow RFC 2119, exactly as in the
Constitution.

> **The dividing line.** End users **never** need a terminal. The developer steps
> in §3 are for working on the **repository only** and MUST NOT be presented to end
> customers.

---

## 1. System Requirements

These requirements apply to **both** audiences (the runtime is the same). They
follow the mandatory stack in Constitution §5 and the performance posture in §11.

### 1.1 Runtime

| Component | Requirement |
|---|---|
| **PHP** | **8.3+**, with the extensions in §1.2. `declare(strict_types=1)` codebase (Constitution §5). |
| **MySQL** | **8.0+** (Constitution §5). A dedicated database and credentialed user. |
| **Web server** | Apache or Nginx (or any server able to route all traffic to a single front controller). |
| **Web root** | MUST point at `/public` only — the **single** web-exposed directory, via `/public/index.php` (Constitution §4). Nothing else is web-accessible. |
| **HTTPS** | Required in production; HTTPS/HSTS enforced (Constitution §10). |
| **OPcache** | SHOULD be enabled in production (Constitution §11). |

### 1.2 Required PHP Extensions

The following PHP extensions **MUST** be present. The installer (§2) and the
developer setup (§3) both verify them.

- `pdo_mysql` — database access via PDO + prepared statements (Constitution §6, §10)
- `mbstring` — multibyte/Unicode handling (required for AR/EN; `UI_GUIDELINES.md` §6)
- `openssl` — encryption, secure tokens, TLS (Constitution §10)
- `json` — JSON encoding/decoding
- `ctype` — character-class checks used in validation
- `fileinfo` — MIME detection for validated file uploads (Constitution §10)
- `curl` — outbound HTTP for the Integration Platform / providers (egress is via
  the Integration Platform — `MODULES.md` §5)
- `tokenizer` — required by tooling and autoloading
- `pcre` — regular expressions (typically bundled with PHP)
- `intl` *(SHOULD)* — locale-aware date/number/currency formatting for AR/EN
  (`UI_GUIDELINES.md` §6)
- `zip` *(SHOULD)* — archive handling (imports/exports, Composer operations)

> The authoritative, version-pinned list is maintained with the **Installer**
> module in Phase 6/8. If this list and `INSTALLER_ARCHITECTURE.md` ever differ,
> the installer spec governs and this file is corrected.

---

## 2. Audience A — End-Customer Zero-Touch Installation (Product Goal)

The headline promise: **upload the files, open the Setup page in a browser, and
follow a wizard to a working platform.** The end customer **MUST NOT** be required
to use SSH, a terminal, Composer, or manual SQL at any point.

### 2.1 The Experience

1. **Upload** the release package to the hosting account (e.g. via the host's file
   manager or FTP). No build step is required on the customer's side — the release
   ships ready to run.
2. **Point the web root** at `/public` (host-dependent, typically a one-time panel
   setting).
3. **Open the site** in a browser. Because the platform is not yet installed, the
   front controller routes the visitor to the **Setup** page automatically.
4. **Follow the wizard** (§2.2) to completion.
5. On finish, **setup locks itself** and the user is redirected to **Login**
   (§2.3).

### 2.2 The Setup Wizard (Steps)

The browser wizard walks the customer through these steps, in order. Each step
validates before allowing the next; the wizard MUST give clear, human-readable
guidance on any failure (consistent with the screen-state rules in
`UI_GUIDELINES.md` §8).

1. **Welcome** — introduces the process and prerequisites.
2. **Server & Extensions check** — verifies PHP **8.3+** and every required
   extension (§1.2); reports anything missing.
3. **Permissions** — checks that the writable paths exist and are writable (e.g.
   `/storage` for logs, cache, compiled views, uploads — Constitution §8).
4. **Environment** — collects environment configuration and writes it to `.env`
   (the customer never edits files by hand). Secrets live in the environment, never
   in the repository (Constitution §5, §10).
5. **Database** — collects MySQL host, port, database name, and credentials.
6. **Connection test** — verifies the database connection and privileges before
   any write.
7. **Run migrations** — executes the platform's forward-only migrations via the
   bespoke migration runner (the **Database** module — `MODULES.md`); no manual SQL.
8. **Seed** — installs required baseline data (e.g. the **Permission Catalog** —
   `PERMISSION_MODEL.md` §2). The product ships **zero** default roles
   (`PERMISSION_MODEL.md` §3).
9. **Create first System Owner** — creates the first `User` and grants
   system-level permissions; this user becomes the **first System Owner**
   (`USER_MODEL.md` §5; `DOMAIN_MODEL.md` §2). Password hashed with **Argon2id**
   (Constitution §10).
10. **Platform settings** — captures global defaults (e.g. platform name, default
    language for the bilingual AR/EN UI, timezone). Workspace-level settings remain
    per workspace (`WORKSPACE_MODEL.md` §4).
11. **Health check** — runs system health probes (Core Kernel **Health Checker** —
    `ARCHITECTURE.md` §6) to confirm the install is sound.
12. **Finish** — completes installation, **locks setup** (§2.3), and redirects to
    **Login**.

### 2.3 Self-Locking & Safety

- On successful **Finish**, the installer MUST **lock itself** so the wizard cannot
  be re-run against an installed system. Re-running setup on an installed platform
  MUST be refused.
- After locking, visiting the app routes to **Login**, not Setup.
- The installer MUST NOT expose secrets, stack traces, or internal detail in the
  browser (Constitution §10; `ARCHITECTURE.md` §6).
- All inputs are validated server-side; database access uses prepared statements
  (Constitution §6, §10).

> **Reference.** The complete state machine, idempotency/resumability, file-system
> and pre-flight checks, locking mechanism, and failure recovery are specified in
> `INSTALLER_ARCHITECTURE.md` (Phase 6) and delivered in Phase 8. This section is
> the Phase-1 overview of intent.

---

## 3. Audience B — Developer Setup (Contributors)

This setup is for working on the **repository**. It uses a terminal and standard
tooling. **End users never perform these steps** (§0).

### 3.1 Developer Prerequisites

- **PHP 8.3+** with the extensions in §1.2.
- **MySQL 8+** with a local database and user for development.
- **Composer** — dependency management and dev tooling **only** (Constitution §5).
- **Node.js + npm** — to run the **TailwindCSS** build for assets. Node is a
  **build-time tool for CSS/JS assets only**; it is **not** a runtime dependency and
  **not** a SPA framework (Constitution §5; `UI_GUIDELINES.md` §4).
- **Git**.

### 3.2 Steps

These commands are **illustrative** of the developer workflow; the authoritative,
runnable scripts live in the repository (`composer.json` scripts, `bin/`, and the
`/database` runner — Constitution §8).

```bash
# EXAMPLE ONLY — developer (repository) setup, not the end-user flow.

# 1) Clone the repository
git clone <repo-url> hahireai
cd hahireai

# 2) Install PHP dependencies (Composer is for deps/dev-tooling only)
composer install

# 3) Create your environment file from the template, then fill in DB credentials
cp .env.example .env          # secrets come from the environment, never committed

# 4) Install front-end build tooling and build Tailwind assets
npm install
npm run build                 # compiles/purges TailwindCSS into /public built assets

# 5) Run the bespoke migration runner (NOT a framework migrator; no manual SQL)
php bin/console migrate       # forward-only migrations via the Database module

# 6) (Optional) seed baseline/development data
php bin/console db:seed       # e.g. Permission Catalog; ships zero default roles

# 7) Run the test suite and quality gates before pushing
composer test                 # PHPUnit (a dev dependency, not a framework)
composer check                # static analysis + coding standard + audit (see below)
```

Notes:

- The **migration runner is bespoke** (the Database module — `MODULES.md`), not a
  framework tool; migrations are **forward-only** (Constitution §14).
- During Tailwind builds, output MUST be **purged** to the classes actually used
  (`UI_GUIDELINES.md` §10).
- For local development you MAY run the wizard in §2 against a fresh database to
  exercise the end-user path, but routine schema changes use the migration runner.

### 3.3 Quality Gates (must pass before merge)

Per Constitution §13 and §14, CI runs — and contributors SHOULD run locally:

- **PHPUnit** tests; domain-layer logic at **≥ 80%** coverage (Constitution §13).
- **Static analysis** (PHPStan/Psalm).
- **Coding standard** (PHP_CodeSniffer; **PSR-12**).
- **`composer audit`** for dependency vulnerabilities.

A red gate blocks merge. Exact commands are defined in `composer.json` and
documented in `TESTING_GUIDE.md` / `CODING_STANDARD.md` (Phase 6).

---

## 4. Environments & Deployment (Pointer)

Environments flow **local → staging → production** (Constitution §14). Production
hardening — OPcache, optimized autoloader, HTTPS/HSTS, asset
bundling/minification, backups, and operational runbooks — is detailed in
`DEPLOYMENT_GUIDE.md` and `OBSERVABILITY.md` (Phase 15). This installation
document covers **getting installed**, not ongoing operations.

---

## 5. Summary — Two Paths, One Platform

| | **Audience A — End customer** | **Audience B — Developer** |
|---|---|---|
| **Goal** | Install and run the product | Build/modify the repository |
| **Interface** | Browser wizard (zero-touch) | Terminal + standard tooling |
| **Terminal / SSH** | **Never required** | Required |
| **Composer / npm** | Not used by the customer | Used (deps + asset build) |
| **Database setup** | Wizard: connection test → migrations → seed | `php bin/console migrate` (+ optional seed) |
| **First System Owner** | Created in the wizard | Created via wizard or seed in dev |
| **Outcome** | Setup locks; redirect to Login | Working dev environment + green gates |

---

### Related Documents

`PROJECT_CONSTITUTION.md` · `INSTALLER_ARCHITECTURE.md` · `ARCHITECTURE.md` ·
`MODULES.md` · `USER_MODEL.md` · `WORKSPACE_MODEL.md` · `PERMISSION_MODEL.md` ·
`UI_GUIDELINES.md` · `SECURITY_GUIDE.md` · `CODING_STANDARD.md` ·
`TESTING_GUIDE.md` · `DEPLOYMENT_GUIDE.md`
