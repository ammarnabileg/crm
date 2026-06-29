# DEPLOYMENT GUIDE — HaHireAI

> **Status:** Draft (Phase 1) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md` (§14). **Ops detail:** Phase 15/16.

---

## 0. Purpose & Scope

This guide elaborates the binding release essentials of `PROJECT_CONSTITUTION.md`
§14 into a concrete deployment process for every environment and contributor
(human or AI). It states *how* we build, ship, and roll back; the Constitution
states the *law*. Where any statement here appears to conflict with the
Constitution, **the Constitution wins**.

Interpretation keywords (**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**,
**MAY**) follow RFC 2119. A **MUST / MUST NOT** rule is binding.

Deep operational detail (full backup/restore runbooks, monitoring stack, on-call,
the full production readiness checklist) is authored in **Phase 15/16** in
`BACKUP_POLICY.md`, `OBSERVABILITY.md`, and the Phase 16 ops docs. This guide sets
the process and references those, rather than duplicating them.

> Command snippets are **illustrative examples only** — not the canonical
> pipeline and not normative. The normative content is the prose rules.

---

## 1. Environments

HaHireAI flows through three environments in one direction (Constitution §14):

```
local  ──►  staging  ──►  production
(dev)       (pre-prod,    (live tenants)
            prod-like)
```

| Environment | Purpose | Data | Config source |
|---|---|---|---|
| **Local** | Development & tests | Disposable/seeded | `.env` (developer) |
| **Staging** | Pre-production verification; prod-like | Sanitized/synthetic only | `.env` (staging secrets) |
| **Production** | Live, multi-tenant | Real tenant data | `.env` (production secrets) |

Rules:
- A change **MUST** reach production only after passing **staging** (Constitution
  §14). No direct-to-production for application changes.
- **Staging MUST mirror production** as closely as possible (PHP 8.3+, MySQL 8+,
  OPcache, headers) so verification is meaningful.
- **Production data MUST NOT** be copied to lower environments unsanitized — PII
  protection (`SECURITY_GUIDE.md` §10). Staging uses synthetic or sanitized data.

---

## 2. Build Steps

A release artifact is built deterministically from a green commit (§7). The build
**MUST**:

1. **Install production dependencies only**

   ```bash
   # EXAMPLE ONLY — illustrative build commands, not the canonical pipeline
   composer install --no-dev --optimize-autoloader
   ```
   `--no-dev` ensures dev tooling (PHPUnit, PHPStan, PHP_CodeSniffer) never ships
   (`TESTING_GUIDE.md`); `--optimize-autoloader` (`-o`) produces an optimized
   PSR-4 classmap (Constitution §11 performance).

2. **Enable & warm OPcache.** Production **MUST** run with **OPcache** enabled
   (Constitution §11). Builds SHOULD validate OPcache configuration; cache is
   warmed/reset as part of release so the new code is served.

3. **Build front-end assets.** Compile **TailwindCSS** (and bundle Vanilla
   JS/Alpine.js) into production assets emitted under `/public` (the only
   web-exposed directory — Constitution §4, §8).

   ```bash
   # EXAMPLE ONLY
   npm ci && npm run build   # Tailwind + JS production build → /public
   ```

4. **Produce an immutable artifact** tied to a version (§7) and a commit SHA, so
   the exact same artifact is promoted local→staging→production.

The build **MUST** be reproducible and MUST NOT depend on developer-machine state.

---

## 3. Configuration & Secrets

- Configuration comes from the **environment**, loaded by the Environment/
  Configuration loaders (`ARCHITECTURE.md` §6). Code MUST NOT contain
  environment-specific constants.
- **Secrets MUST NOT be committed** (Constitution §5, §10). `.env` is git-ignored;
  `.env.example` enumerates required keys with **no real values**
  (`SECURITY_GUIDE.md` §6).
- Each environment supplies its own secrets (DB credentials, app/signing keys, AI
  provider keys, payment keys, webhook secrets). Keys are rotatable; rotation MUST
  NOT require code changes (`SECURITY_GUIDE.md` §6.3).
- Deploys **SHOULD** validate that all required env keys are present **before**
  cutting over (fail fast on missing config).

---

## 4. Database Migrations (forward-only, gated)

- Schema changes ship as **migrations** run by the **bespoke migration runner**
  (the Database module's migration engine — `MODULES.md`; no ORM, no third-party
  migration framework).
- Migrations are **forward-only and gated** (Constitution §14): there are **no
  down-migrations** in the deployment path. A correction is a **new** forward
  migration, never an in-place rewrite of an applied one.
- Migrations **MUST** run as a discrete, gated step in the deploy (after build,
  before/at cutover per the zero-downtime rules in §5), and MUST be idempotent/safe
  to re-run where possible.
- Migrations MUST preserve **tenant isolation** invariants — e.g. a new
  workspace-scoped table includes `workspace_id` and supporting indexes
  (`SECURITY_GUIDE.md` §4; `INDEXING_GUIDE.md`).

```bash
# EXAMPLE ONLY — invoking the bespoke runner via the project CLI (illustrative)
php bin/console migrate --no-interaction
```

---

## 5. Zero-Downtime Deployment

The deploy approach **SHOULD** achieve zero downtime:

- **Expand → migrate → contract.** Make schema changes backward-compatible first
  (expand), deploy code that works with old *and* new schema, then remove the old
  shape in a later forward migration (contract). This lets old and new code
  coexist briefly during cutover.
- **Atomic release switch.** Build the new release out-of-band and switch traffic
  atomically (e.g. symlink/slot swap), so requests are served by exactly one
  consistent version at a time.
- **Drain & warm.** Allow in-flight requests to drain; warm OPcache/caches before
  the new version takes full traffic (Constitution §11).
- **Async work stays responsive.** Heavy/AI work runs via the queue
  (Constitution §11; `BACKGROUND_JOBS.md`); deploys MUST account for in-flight
  background jobs (drain or version-tolerant handlers).

Full zero-downtime runbook detail is finalized in **Phase 15/16**.

---

## 6. Rollback Strategy

- **Code rollback.** Because releases are immutable, versioned artifacts (§2),
  rollback is switching the release pointer back to the previous known-good
  artifact. This MUST be fast and rehearsed.
- **Database rollback.** Migrations are **forward-only** (§4): you **MUST NOT**
  reverse a migration to roll back. Recovery from a bad schema change is a **new
  forward migration** plus, if data was harmed, restore from backup
  (`BACKUP_POLICY.md`, Phase 15).
- **Compatibility window.** The expand/contract approach (§5) keeps the previous
  code version compatible with the current schema during a release, so a code-only
  rollback is safe without touching the database.
- Every rollback **SHOULD** be recorded (what, why, when) and trigger a follow-up
  fix forward.

---

## 7. Versioning, Commits & Changelog

- **Semantic Versioning** `MAJOR.MINOR.PATCH` (Constitution §14, §16).
- **Conventional Commits** for every commit message (Constitution §14); this also
  drives changelog generation.
- **Trunk-based development:** short-lived feature branches → pull request → green
  CI → review → merge (Constitution §14; gates in `TESTING_GUIDE.md` §8).
- **`CHANGELOG.md`** follows *Keep a Changelog* + SemVer and **MUST** be updated
  every release (Constitution §12, §14).
- A release is cut **only** from a commit with green CI (tests, static analysis,
  coding standard, `composer audit` — `TESTING_GUIDE.md`).

---

## 8. Progressive Rollout (feature flags / enabled modules)

- Risky changes **SHOULD** roll out progressively via **feature flags / enabled
  modules** (Constitution §14).
- **Enabled modules are per workspace** (`WORKSPACE_MODEL.md` §8;
  Licensing/Subscriptions — `MODULES.md`). A module/capability can be enabled for
  a subset of workspaces before general availability.
- Feature flags allow shipping code **dark** and activating it independently of
  deployment, enabling fast disable without a rollback.
- A new module follows additive registration (manifest, permissions, events,
  routes — `ARCHITECTURE.md` §9); enabling it MUST NOT require schema/nav/permission
  engine changes, only registration + flag.

---

## 9. Backups & Restore

- Production data **MUST** be backed up on a defined schedule with verified,
  rehearsed **restore** (Constitution §11 reliability posture; owned by the
  **Observability** module — `MODULES.md`).
- Backups MUST respect tenant data sensitivity and encryption-at-rest expectations
  (`SECURITY_GUIDE.md` §6, §10).
- The authoritative backup cadence, retention, encryption, and restore runbook
  live in **`BACKUP_POLICY.md` (Phase 15)** — this guide only mandates that they
  exist and gate production readiness (§11).

---

## 10. Monitoring, Logging & Alerting

- Production **MUST** emit health, metrics, logs, and errors via the shared
  **Observability** module and the PSR-3 **Logger** (`ARCHITECTURE.md` §6, §8;
  `MODULES.md`) — never hand-rolled per module.
- A **Health Checker** with pluggable probes (`ARCHITECTURE.md` §6) MUST back a
  deploy health check and ongoing monitoring.
- The **Error Handler** MUST never leak stack traces in production
  (`ARCHITECTURE.md` §6; `SECURITY_GUIDE.md`).
- Alerting hooks on health/error/SLA breaches (performance budgets — Constitution
  §11) are wired here. Full monitoring/alerting stack detail lives in
  **`OBSERVABILITY.md` (Phase 15)**.

---

## 11. Production Readiness Checklist (brief)

A release to production **MUST** satisfy at least the following before cutover
(the **full** checklist is authored in **Phase 16**):

- [ ] CI green on the release commit — tests, PHPStan/Psalm, PHP_CodeSniffer,
      `composer audit` (`TESTING_GUIDE.md` §8).
- [ ] Built with `composer install --no-dev -o`; **no dev dependencies** shipped.
- [ ] **OPcache** enabled; **Tailwind/JS assets** built into `/public` (§2).
- [ ] All required **env secrets** present and validated; nothing secret committed
      (§3; `SECURITY_GUIDE.md` §6).
- [ ] **HTTPS/HSTS** and security headers enforced; only `/public` web-exposed
      (`SECURITY_GUIDE.md` §8).
- [ ] **Forward-only migrations** reviewed and ready via the bespoke runner (§4).
- [ ] **Tenant isolation** unaffected — no un-scoped workspace tables/queries
      introduced (`SECURITY_GUIDE.md` §4; `TESTING_GUIDE.md` §10).
- [ ] **Backups** current and restore verified (`BACKUP_POLICY.md`, §9).
- [ ] **Monitoring/alerting** live; health check green (`OBSERVABILITY.md`, §10).
- [ ] **Rollback** path confirmed (previous artifact available; §6).
- [ ] **`CHANGELOG.md`** updated; version bumped per SemVer (§7).
- [ ] Staging verification passed (§1).

---

### Related Documents
`PROJECT_CONSTITUTION.md` (§14) · `ARCHITECTURE.md` · `MODULES.md` ·
`WORKSPACE_MODEL.md` · `SECURITY_GUIDE.md` · `TESTING_GUIDE.md` ·
`DATABASE_ARCHITECTURE.md` · `INDEXING_GUIDE.md` · `BACKGROUND_JOBS.md` (Phase 12) ·
`BACKUP_POLICY.md` (Phase 15) · `OBSERVABILITY.md` (Phase 15) · `CHANGELOG.md`
