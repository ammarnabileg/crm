# BUILD SYSTEM — HaHireAI

> **Status:** Adopted (Phase 6) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `DEPLOYMENT_GUIDE.md`, `PROJECT_STRUCTURE.md`.

---

## 0. Purpose & Scope

This document is the **authoritative design of how a HaHireAI artifact is built**,
across the full lifecycle: **Development → Testing → Production → Updates →
Rollback**. It defines *what runs at each stage*, *what artifact is produced*, *how
environments differ*, and *which gates a build must clear* before it may be
promoted. It is **design on paper** — there is **no implementation, no canonical
script, and no runnable pipeline** here. The runnable scripts live in
`composer.json`, `bin/`, and the asset toolchain; the operational promotion process
is `DEPLOYMENT_GUIDE.md`.

Where this document elaborates `DEPLOYMENT_GUIDE.md`, that guide wins; where either
disagrees with `PROJECT_CONSTITUTION.md`, the **Constitution wins** (§14 Release,
§11 Performance, §5 Development). Interpretation keywords (**MUST**, **MUST NOT**,
**SHOULD**, **SHOULD NOT**, **MAY**) follow RFC 2119. A **MUST / MUST NOT** rule is
binding; a violation is a defect.

> Command snippets are **illustrative examples only** — not the canonical pipeline
> and not normative. The normative content is the prose rules. The stack is fixed
> by Constitution §5: **Native PHP 8.3+, MySQL 8+, TailwindCSS, Alpine.js (UI
> only), Vanilla JS, Composer (dependencies/dev-tooling only)** — **no framework**.

---

## 1. Build Philosophy

- **One artifact, promoted unchanged.** A release is built **once** from a green
  commit and the **same immutable artifact** flows `local → staging → production`
  (`DEPLOYMENT_GUIDE.md` §1, §2). Production is never rebuilt from source on the
  customer's host.
- **Reproducible & host-independent.** A build **MUST** be deterministic and **MUST
  NOT** depend on developer-machine state (`DEPLOYMENT_GUIDE.md` §2). The same
  commit yields an equivalent artifact anywhere.
- **No dev tooling in production.** PHPUnit, PHPStan/Psalm, and PHP_CodeSniffer are
  dev dependencies (Constitution §13) and **MUST NOT** ship (§4 below).
- **Only `/public` is web-exposed.** Built assets land under `/public/assets`; the
  single front controller is `/public/index.php` (`PROJECT_STRUCTURE.md` §2;
  Constitution §4).
- **Build does not migrate.** Compiling code and assets is separate from applying
  **forward-only migrations**; migration is a discrete, gated deploy step
  (`DEPLOYMENT_GUIDE.md` §4; `DATABASE_ARCHITECTURE.md` §12).

---

## 2. The Build Pipeline (overview)

```
   ┌───────────────┐   ┌───────────────┐   ┌──────────────────┐
   │  DEVELOPMENT  │   │    TESTING    │   │    PRODUCTION    │
   │  (local)      │──►│  (CI on PR)   │──►│  (release build) │
   └──────┬────────┘   └──────┬────────┘   └────────┬─────────┘
          │ composer install  │ CI GATES:           │ composer install --no-dev -o
          │ npm install       │  • PHPUnit           │ npm ci && npm run build (minify)
          │ npm run dev/watch │  • PHPStan/Psalm     │ OPcache validated + warmed
          │ unoptimized       │  • PHP_CodeSniffer   │ optimized PSR-4 classmap
          │ autoloader        │  • composer audit    │ env keys validated (fail fast)
          │                   │  • migrate-from-zero │ → IMMUTABLE ARTIFACT (vX.Y.Z + SHA)
          └───────────────────┴──────────────────────┘            │
                                                                   ▼
                        ┌──────────────────────────────────────────────────┐
                        │  PROMOTE the SAME artifact: staging ► production   │
                        │  cutover = atomic release switch + migrate (gated) │
                        └───────────────────────┬──────────────────────────┘
                                                 │
                          ┌──────────────────────┴───────────────────────┐
                          │  UPDATES  (new version → new artifact)        │
                          │  ROLLBACK (switch pointer to previous artifact)│
                          └───────────────────────────────────────────────┘
```

Each lifecycle stage is specified below. A build that has not cleared the **Testing
gates** (§4) **MUST NOT** be promoted toward production.

---

## 3. Stage 1 — Development (local)

**Goal:** fast inner loop for a contributor working on the repository
(`INSTALLATION.md` §3). This stage favors speed and debuggability over
optimization.

**What runs (illustrative):**

```bash
# EXAMPLE ONLY — local developer build, not the canonical pipeline
composer install            # all deps INCLUDING dev tooling (PHPUnit, PHPStan, …)
npm install                 # front-end build tooling (Tailwind/JS)
npm run dev                 # or `npm run watch` — Tailwind + JS, unminified, source-mapped
```

**Characteristics:**

- **Dev dependencies present.** `composer install` (no `--no-dev`) so the test and
  quality tooling is available locally (Constitution §13).
- **Autoloader is *not* optimized.** A non-optimized PSR-4 autoloader is acceptable
  locally; the optimized classmap is a production concern (§5).
- **Assets unminified, with source maps** and (optionally) a Tailwind **watch** for
  live rebuilds; Tailwind output SHOULD still be **purged/JIT** to used classes
  (`UI_GUIDELINES.md` §10).
- **OPcache MAY be off** (or in revalidating mode) so code edits take effect
  immediately; production OPcache settings are not required here.
- **Secrets from the developer's `.env`** (git-ignored), filled from `.env.example`
  (`DEPLOYMENT_GUIDE.md` §3; Constitution §5).

**Artifacts produced:** a working tree plus locally built assets under
`/public/assets`. **No release artifact** is produced locally — local builds are
disposable and **MUST NOT** be promoted (`DEPLOYMENT_GUIDE.md` §1).

---

## 4. Stage 2 — Testing (CI on pull request)

**Goal:** prove a commit is releasable. CI is the **gate**, run on every pull
request under **trunk-based development** (Constitution §14; `TESTING_GUIDE.md` §8).
A red gate blocks merge (Constitution §13).

**What runs (illustrative):**

```bash
# EXAMPLE ONLY — CI quality gates, not the canonical pipeline
composer install                 # dev tooling required to RUN the gates
composer test                    # PHPUnit — unit > integration > feature
composer check                   # static analysis + coding standard + audit (see below)
npm ci && npm run build          # assets must build cleanly (no broken Tailwind/JS)
# migrate-from-zero smoke: build the full schema on an EMPTY database
php bin/console migrate --no-interaction
```

**CI gates (all MUST pass — Constitution §13, §14):**

| Gate | Tool | Enforces |
|---|---|---|
| **Tests** | PHPUnit | Test pyramid; domain logic **≥ 80%** coverage. |
| **Static analysis** | PHPStan / Psalm | Type safety; `declare(strict_types=1)` everywhere (Constitution §5, §6). |
| **Coding standard** | PHP_CodeSniffer | **PSR-12**, PSR-4 layout (`CODING_STANDARD.md`). |
| **Dependency audit** | `composer audit` | No known-vulnerable dependencies. |
| **Migrate-from-zero** | bespoke runner | The full migration set builds a correct schema from an **empty** database (`DATABASE_ARCHITECTURE.md` §12). |
| **Tenant-isolation checks** | tests | No un-scoped workspace tables/queries introduced (`SECURITY_GUIDE.md` §4; `TESTING_GUIDE.md` §10). |
| **Asset build** | Tailwind/JS toolchain | Assets compile cleanly and purge to used classes. |

**Environment differences from Development:**

- CI installs dev dependencies (to *run* the gates) but **never produces a
  production artifact from a dev install**; the production artifact is built
  separately with `--no-dev` (§5).
- CI uses a **disposable/seeded** database and **synthetic** data only — never
  production data (`DEPLOYMENT_GUIDE.md` §1; `SECURITY_GUIDE.md` §10).

**Artifacts produced:** test reports, coverage, and static-analysis results
(pass/fail signal). CI's output is **the green/red verdict**, not the shippable
artifact. A release is cut **only** from a commit whose CI is green
(`DEPLOYMENT_GUIDE.md` §7).

---

## 5. Stage 3 — Production (release build)

**Goal:** turn a **green commit** into the **immutable, versioned artifact** that
will be promoted and, for end customers, shipped as the zero-touch release package.

**What runs (illustrative):**

```bash
# EXAMPLE ONLY — production artifact build, not the canonical pipeline
composer install --no-dev --optimize-autoloader   # prod deps only + optimized classmap
npm ci && npm run build                            # Tailwind + JS, MINIFIED → /public/assets
# validate OPcache config; assets fingerprinted/cache-busted; env keys validated
```

**What the production build MUST do (`DEPLOYMENT_GUIDE.md` §2; Constitution §11):**

1. **Install production dependencies only** — `composer install --no-dev` so dev
   tooling never ships, plus `--optimize-autoloader` (`-o`) to emit an **optimized
   PSR-4 classmap** for fast autoloading.
2. **Build & minify front-end assets** — compile **TailwindCSS** (purged to used
   classes) and bundle Vanilla JS / Alpine.js into **minified, fingerprinted**
   production assets under `/public/assets` (cache-busting hashes — see
   `UPDATE_POLICY.md` for the asset cache-bust contract).
3. **Validate & warm OPcache** — production **MUST** run with **OPcache** enabled
   (Constitution §11); the build validates OPcache configuration, and cutover warms
   / resets the cache so the new bytecode is served (`DEPLOYMENT_GUIDE.md` §5).
4. **Validate required env keys** — fail fast if any required key is missing
   (`DEPLOYMENT_GUIDE.md` §3); the artifact carries **no secrets** (Constitution
   §5, §10).
5. **Stamp & seal an immutable artifact** — tag with the SemVer version
   (`MAJOR.MINOR.PATCH`) and commit SHA so the **exact same artifact** is promoted
   local→staging→production (`DEPLOYMENT_GUIDE.md` §2, §7).

**Artifacts produced:**

| Artifact | Contents |
|---|---|
| **Release package** | Application code + `vendor/` (no-dev) + built `/public/assets`, **excluding** dev tooling, tests, and `node_modules`. Ready to run with **no build step on the host**. |
| **Version stamp** | SemVer `vX.Y.Z` + commit SHA + `CHANGELOG.md` entry (Constitution §12, §14). |
| **Asset manifest** | Fingerprint map for cache-busted assets (consumed at render; see `UPDATE_POLICY.md`). |

**Environment differences (summary):**

| Concern | Development | Testing (CI) | Production build |
|---|---|---|---|
| Composer deps | all (`install`) | all (to run gates) | **`--no-dev -o`** |
| Autoloader | unoptimized | unoptimized | **optimized classmap** |
| Assets | unminified + maps | built (verify) | **minified + fingerprinted** |
| OPcache | MAY be off | n/a | **enabled, validated, warmed** |
| Data | seeded/disposable | synthetic | none in artifact (env-driven) |
| Output | working tree | pass/fail verdict | **immutable artifact** |

### 5.1 How the Zero-Touch Installer Fits Production

The end customer **never builds**. The release package ships **ready to run**, so
the customer's flow is *upload → point web root at `/public` → open browser →
wizard* (`INSTALLATION.md` §2; `INSTALLER_ARCHITECTURE.md`, Phase 6/8). The build
system's job is to make that possible:

- The artifact contains the **no-dev `vendor/`**, the **optimized classmap**, and
  **pre-built minified assets**, so **no Composer/npm runs on the customer host**
  (`INSTALLATION.md` §0, §2).
- The installer wizard then performs the **runtime** install on first boot:
  server/extension checks, writable-path checks, `.env` write, DB connection test,
  **forward-only migrations**, seed (permission catalog), and **first System
  Owner** — all in the browser (`INSTALLATION.md` §2.2;
  `DATABASE_ARCHITECTURE.md` §12).
- Migrations are **not** part of the build artifact's execution; they run as the
  installer's gated step on the target database (§1; `DEPLOYMENT_GUIDE.md` §4).

---

## 6. Stage 4 — Updates (build side)

An update is **a new version → a new immutable artifact**, built exactly as §5. The
build system's responsibilities for an update are:

- **Version bump** per SemVer and a `CHANGELOG.md` entry on the release commit
  (`DEPLOYMENT_GUIDE.md` §7).
- **Asset cache-busting** — rebuilt assets get **new fingerprints** so browsers
  fetch fresh CSS/JS and never serve stale cached assets.
- **Carry any new forward migrations** so the installer/updater can apply them as a
  gated step.

The **policy** governing how updates are applied without breaking a running system
(expand/contract, backup-before-update, browser-based update flow) is owned by
**`UPDATE_POLICY.md`**. This document only states how the *artifact* for an update
is produced.

---

## 7. Stage 5 — Rollback (build side)

Because releases are **immutable, versioned artifacts** (§5), rollback is a
**build-free** operation:

- **Code rollback = repoint to the previous known-good artifact.** No rebuild, no
  recompile; the prior artifact is retained and re-selected
  (`DEPLOYMENT_GUIDE.md` §6). This is fast and rehearsed.
- **No asset rebuild on rollback** — the previous artifact already carries its own
  fingerprinted assets and manifest, so reverting code reverts assets atomically.
- **Database is *not* rolled back by the build.** Migrations are **forward-only**
  (`DATABASE_ARCHITECTURE.md` §12; `DEPLOYMENT_GUIDE.md` §6): a bad schema change is
  corrected by a **new forward migration**, and data harm is recovered from backup
  (`BACKUP_POLICY.md`). The **expand/contract** discipline keeps the previous code
  version compatible with the current schema so a code-only rollback is safe
  (`UPDATE_POLICY.md`; `DEPLOYMENT_GUIDE.md` §5).

Retaining **N previous artifacts** (so rollback always has a target) is an
operational requirement of `DEPLOYMENT_GUIDE.md` §6; the build system's contribution
is ensuring every artifact is self-contained and immutable.

---

## 8. CI Gates Summary (release readiness)

A commit is **build-releasable** only if **all** hold (consolidating Constitution
§13/§14 and `DEPLOYMENT_GUIDE.md` §11):

- [ ] CI green: **PHPUnit**, **PHPStan/Psalm**, **PHP_CodeSniffer**,
      **`composer audit`** (§4).
- [ ] Production artifact built with **`composer install --no-dev -o`** — **no dev
      dependencies, tests, or `node_modules` shipped** (§5).
- [ ] **Tailwind/JS** assets built, **minified, purged, and fingerprinted** into
      `/public/assets` (§5).
- [ ] **OPcache** configuration validated; warm/reset wired into cutover (§5).
- [ ] **Migrate-from-zero** smoke passes — schema builds from an empty database
      (`DATABASE_ARCHITECTURE.md` §12).
- [ ] **Tenant isolation** unaffected — no un-scoped workspace tables/queries
      (`SECURITY_GUIDE.md` §4).
- [ ] **Env keys** validated at build/cutover; **no secrets** in the artifact (§5).
- [ ] Artifact **stamped** with SemVer + SHA; **`CHANGELOG.md`** updated (§5, §6).
- [ ] Previous artifact retained for **rollback** (§7).

---

## 9. Self-Review (Phase 6 gate)

- [ ] Every lifecycle stage (Development → Testing → Production → Updates →
      Rollback) states what runs, its artifacts, and its environment differences.
- [ ] The production build is reproducible, host-independent, and ships **no** dev
      tooling (§1, §5).
- [ ] The zero-touch installer needs **no build step on the customer host** (§5.1).
- [ ] Build is cleanly separated from **forward-only migration** application (§1).
- [ ] Rollback requires **no rebuild** (§7).
- [ ] This document defers correctly to `DEPLOYMENT_GUIDE.md`, `UPDATE_POLICY.md`,
      `BACKUP_POLICY.md`, and `PROJECT_STRUCTURE.md`.

---

### Related Documents
`DEPLOYMENT_GUIDE.md` · `PROJECT_STRUCTURE.md` · `PROJECT_CONSTITUTION.md` (§5, §11, §14) ·
`UPDATE_POLICY.md` · `BACKUP_POLICY.md` · `DATABASE_ARCHITECTURE.md` ·
`INSTALLATION.md` · `INSTALLER_ARCHITECTURE.md` (Phase 6) · `TESTING_GUIDE.md` ·
`CODING_STANDARD.md` · `UI_GUIDELINES.md` · `CHANGELOG.md`
