# UPDATE POLICY — HaHireAI

> **Status:** Adopted (Phase 6) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `BUILD_SYSTEM.md`, `DATABASE_ARCHITECTURE.md`, `PROJECT_CONSTITUTION.md` (§14).

---

## 0. Purpose & Scope

This document is the **authoritative policy for updating a running HaHireAI
installation without breaking it**. It governs how each part of the system moves
from one version to the next — **database, modules, configuration, assets, and
documentation** — and the cross-cutting machinery that makes updates safe: SemVer,
update detection, **backup-before-update**, rollback, **zero-downtime
(expand/contract)**, and a **browser-based update flow** consistent with the
zero-touch product promise (`INSTALLATION.md`).

This is **design on paper** — no implementation, no canonical script. How an update
*artifact* is produced is `BUILD_SYSTEM.md`; how it is promoted across environments
is `DEPLOYMENT_GUIDE.md`; how the schema engine works is
`DATABASE_ARCHITECTURE.md`. Where this document elaborates those, they win; where
any disagrees with `PROJECT_CONSTITUTION.md`, the **Constitution wins** (§14
Release). Interpretation keywords (**MUST**, **MUST NOT**, **SHOULD**, **SHOULD
NOT**, **MAY**) follow RFC 2119. A **MUST / MUST NOT** rule is binding; a violation
is a defect.

---

## 1. Update Principles

1. **An update never destroys data.** Updating is additive and reversible by
   design; routine destruction of business/tenant data during an update is
   forbidden (`ARCHIVING_POLICY.md`; `DATABASE_ARCHITECTURE.md` §9).
2. **From-zero must always still work.** Every update adds **forward-only**
   migrations; the **full set MUST still build a correct schema from an empty
   database** (`DATABASE_ARCHITECTURE.md` §12). An update path and a clean install
   path converge on the same schema.
3. **Forward-only, never reverse.** There are **no down-migrations** in the update
   path; a mistake is corrected by a **new** forward migration (Constitution §14;
   `DEPLOYMENT_GUIDE.md` §6).
4. **Backup before you change.** A verified backup **MUST** exist immediately
   before any update that touches the database (`BACKUP_POLICY.md`).
5. **Additive configuration.** New config keys ship with **safe defaults**; an
   update **MUST NOT** require the operator to hand-edit files (§4).
6. **Zero-downtime where possible.** Updates **SHOULD** follow **expand → migrate →
   contract** so old and new code can briefly coexist (`DEPLOYMENT_GUIDE.md` §5).
7. **Same zero-touch surface.** End customers update **in the browser** — no SSH,
   terminal, Composer, or manual SQL (`INSTALLATION.md` §0, §2).

---

## 2. Versioning & Update Semantics (SemVer)

HaHireAI uses **Semantic Versioning** `MAJOR.MINOR.PATCH` (Constitution §14;
`DEPLOYMENT_GUIDE.md` §7). The version increment communicates the **nature and risk**
of an update:

| Increment | Meaning | Migration expectation | Operator action |
|---|---|---|---|
| **PATCH** (`x.y.Z`) | Backward-compatible bug/security fix. | Usually none; if any, additive only. | Apply promptly; low risk. |
| **MINOR** (`x.Y.0`) | Backward-compatible new capability/module. | **Additive** (expand) migrations; new config keys with defaults. | Review changelog; apply. |
| **MAJOR** (`X.0.0`) | Breaking change to a contract, schema shape, or behavior. | May include a **contract** phase; **MUST** be staged and rehearsed. | Read upgrade notes; stage first. |

- Every update **MUST** carry a `CHANGELOG.md` entry (*Keep a Changelog* + SemVer,
  Constitution §12, §14) describing what changed and any required action.
- **Security PATCH** releases SHOULD be applied promptly; the update flow surfaces
  them as recommended (§7).
- A release is only ever cut from a **green commit** and shipped as the **immutable
  artifact** of `BUILD_SYSTEM.md` §5.

---

## 3. Database Updates (forward-only, gated)

Schema evolves **only** through the **bespoke, forward-only migration engine** — the
Database module's runner (`DATABASE_ARCHITECTURE.md` §12; **no** framework/ORM,
Constitution §5).

- **Forward-only & ordered.** New migrations are uniquely identified, applied in
  deterministic order, and tracked in the **migrations ledger** table. There is
  **no rollback migration** (`DATABASE_ARCHITECTURE.md` §12).
- **Idempotent / pending-only.** Re-running applies only what is pending, so an
  interrupted or resumed update converges safely.
- **From-zero invariant preserved.** The accumulated migration set **MUST** still
  produce the correct schema on an **empty** database — exercised by the installer
  and by CI's migrate-from-zero gate (`BUILD_SYSTEM.md` §4;
  `DATABASE_ARCHITECTURE.md` §12, §13).
- **Tenant-safe.** A migration **MUST** preserve tenancy invariants: never drop
  `workspace_id`, never silently widen scope; new workspace-scoped tables carry
  `workspace_id NOT NULL` + index (`DATABASE_ARCHITECTURE.md` §8;
  `SECURITY_GUIDE.md` §4).
- **Reviewed & gated.** Migrations are reviewed and CI-gated like code; destructive
  changes get extra scrutiny and an ADR where warranted (`DATABASE_ARCHITECTURE.md`
  §12).
- **Module-owned, globally orchestrated.** Each module ships migrations under its
  `Database/` folder; the global runner orchestrates them in **dependency order**
  (`PROJECT_STRUCTURE.md` §4; `MODULES.md` §5) so a module's update can rely on its
  dependencies' schema being present first.
- **Backup precedes migration.** The update flow takes (or verifies) a backup
  **before** running migrations (§5, §8; `BACKUP_POLICY.md`).

> Correcting a bad migration is a **new forward migration** plus, if data was
> harmed, **restore from backup** (`DEPLOYMENT_GUIDE.md` §6) — never an in-place
> rewrite of an applied migration.

---

## 4. Module Updates

Modules are autonomous and update **additively** (Constitution §9; `MODULES.md`).

- **Additive registration.** A new or updated module registers via its `module.php`
  manifest — permissions, events, routes, migrations, enabled-by — **without**
  changing the database design, the navigation engine, or the permission engine;
  it only **adds** to them (Constitution §9; `DEPLOYMENT_GUIDE.md` §8).
- **Declared dependencies & order.** A module declares its dependencies in
  `module.php`; updates apply migrations and registration in **dependency order**
  (§3). Undeclared dependencies are forbidden (Constitution §9).
- **Contract compatibility.** A module exposes behavior only through `Contracts/`
  and events (Constitution §9). Within a MAJOR-free line, a contract change **MUST**
  be backward-compatible (add methods/optional params; do not break existing
  signatures). A breaking contract change is a **MAJOR** update (§2) and follows
  expand/contract (§6).
- **New modules ship dark.** A new module/capability MAY ship disabled and be
  activated via **feature flags / enabled-modules per workspace**
  (`DEPLOYMENT_GUIDE.md` §8; `WORKSPACE_MODEL.md` §8), enabling progressive rollout
  and instant disable without a code rollback.
- **Graceful degradation.** A module **MUST** remain removable/disable-able without
  breaking unrelated modules (Constitution §9); an update MUST NOT make an optional
  dependency mandatory without a MAJOR bump.

---

## 5. Configuration Updates (additive; defaults for new keys)

- **Defaults for new keys.** When an update introduces a configuration key, it
  **MUST** supply a **safe default** so an existing installation keeps working with
  **no operator action** (Constitution §8: declarative config, no logic, no
  secrets).
- **No hand-editing.** Consistent with zero-touch, the operator is **never** asked
  to edit files by hand; new environment keys are surfaced and captured through the
  browser update flow (§7), and `.env.example` is updated to enumerate them (with
  **no real values** — `DEPLOYMENT_GUIDE.md` §3; `SECURITY_GUIDE.md` §6).
- **Backward-compatible by default.** Renames/removals of config keys are treated
  as **breaking** (MAJOR, §2): the old key is honored through a deprecation window
  with a logged warning before removal.
- **Behavior is data, not redeploy.** Workspace-level settings, roles, prompts, and
  workflows evolve as **data** at runtime (`VERSIONING_POLICY.md` §0;
  `WORKSPACE_MODEL.md`) and are **not** part of a code update — an update MUST NOT
  silently overwrite tenant-authored configuration.

---

## 6. Zero-Downtime Updates (expand / contract)

Updates **SHOULD** achieve zero downtime via the **expand → migrate → contract**
discipline (`DEPLOYMENT_GUIDE.md` §5):

```
  EXPAND            DEPLOY (coexist)          CONTRACT (later release)
  ──────            ────────────────          ───────────────────────
  add new, backward-   code reads/writes        remove the old shape in a
  compatible schema    BOTH old and new shape   NEW forward migration once
  (nullable cols,      during atomic cutover;   no running code needs it
  new tables/indexes)  previous version stays
                       compatible (safe rollback window)
```

- **Expand first.** Make schema changes backward-compatible (add columns/tables/
  indexes; keep old columns) so the **previous code version still runs** against
  the new schema — preserving a safe code-only rollback window.
- **Deploy with an atomic release switch.** Build the new release out-of-band and
  switch traffic atomically (e.g. symlink/slot swap) so exactly one consistent
  version serves at a time (`DEPLOYMENT_GUIDE.md` §5).
- **Drain & warm.** Let in-flight requests drain; warm OPcache/caches before the
  new version takes full traffic (Constitution §11).
- **Contract later.** Remove the old shape only in a **subsequent** forward
  migration, once no running code references it (§3).
- **Async work stays version-tolerant.** Heavy/AI work runs via the queue
  (Constitution §11; `BACKGROUND_JOBS.md`); updates **MUST** account for in-flight
  background jobs (drain or version-tolerant handlers — `DEPLOYMENT_GUIDE.md` §5).

---

## 7. Update Detection & the Browser-Based Update Flow

Consistent with the zero-touch promise, **end customers update in the browser** —
**no SSH, terminal, Composer, or manual SQL** (`INSTALLATION.md` §0;
`INSTALLER_ARCHITECTURE.md`, Phase 6/8).

**Update detection.** The platform SHOULD detect that a newer release is available
(version compared against the running version; PATCH/MINOR/MAJOR classified per §2)
and surface it to authorized **System Owners** (`system.*`, deny-by-default —
`PERMISSION_MODEL.md`; `SECURITY_GUIDE.md`). Security PATCH releases are flagged as
recommended.

**The browser update flow (ordered; each step validates before the next):**

```
 1 Detect & review ──► 2 Pre-flight ──► 3 BACKUP ──► 4 Maintenance/expand ──►
 5 Apply code (atomic switch) ──► 6 Migrate (forward-only, gated) ──►
 7 Health check ──► 8 Resume traffic ──► 9 Record (CHANGELOG/audit)
        │ on any failure before/at step 6 → halt, keep old version live,
        └─ surface guidance; RESTORE is a separate, manual decision (§8, BACKUP_POLICY.md)
```

1. **Detect & review** — show the target version and its `CHANGELOG.md` notes and
   required actions (§2).
2. **Pre-flight** — re-verify PHP **8.3+**, extensions, writable paths, and DB
   connectivity (mirrors the installer's checks — `INSTALLATION.md` §2.2).
3. **Backup** — take/verify a backup **before** any change (§8; `BACKUP_POLICY.md`).
4. **Maintenance / expand** — apply backward-compatible (expand) schema changes;
   enter a brief maintenance posture only if a step genuinely requires it.
5. **Apply code** — switch to the new immutable artifact atomically (§6;
   `BUILD_SYSTEM.md` §5).
6. **Migrate** — run pending **forward-only** migrations via the bespoke runner as
   a discrete, gated step (§3).
7. **Health check** — run Core Kernel health probes to confirm soundness
   (`ARCHITECTURE.md` §6; `INSTALLATION.md` §2.2).
8. **Resume** — warm caches/OPcache and return to normal traffic (§6).
9. **Record** — update `CHANGELOG.md`/version and write an audit entry
   (`AUDIT_POLICY.md`).

- The update UI **MUST NOT** expose secrets, stack traces, or internal detail in
  the browser (`INSTALLATION.md` §2.3; `SECURITY_GUIDE.md`).
- The flow **SHOULD** be **resumable/idempotent**: re-running applies only what is
  pending (§3), mirroring the installer's idempotency.
- A developer/CI path MAY drive the same steps via the project CLI
  (`INSTALLATION.md` §3); the *end-customer* path is the browser flow above.

---

## 8. Backup-Before-Update & Rollback

- **Backup is mandatory before any DB-affecting update.** The flow (§7 step 3)
  takes or verifies a backup first; if a current verified backup cannot be
  obtained, the update **MUST NOT** proceed (`BACKUP_POLICY.md`;
  `DEPLOYMENT_GUIDE.md` §11).
- **Code rollback is fast and build-free.** Because artifacts are immutable and
  versioned, rollback repoints to the **previous known-good artifact** — no rebuild
  (`BUILD_SYSTEM.md` §7; `DEPLOYMENT_GUIDE.md` §6).
- **The expand/contract window makes code rollback safe** without touching the
  database, because the previous code is still compatible with the current
  (expanded) schema (§6).
- **Database is never reverse-migrated.** Recovery from a harmful schema/data change
  is a **new forward migration** and, if data was harmed, a **restore from backup**
  (`DATABASE_ARCHITECTURE.md` §12; `BACKUP_POLICY.md`).
- **Restore is never automatic.** An update failure halts and keeps the old version
  live; deciding to **restore** is a separate, deliberate, permission-gated action
  (`BACKUP_POLICY.md`).
- **Every update and rollback is recorded** (what, why, when) in `CHANGELOG.md` and
  the audit trail, and a rollback **SHOULD** trigger a fix-forward
  (`DEPLOYMENT_GUIDE.md` §6; `AUDIT_POLICY.md`).

---

## 9. Asset & Documentation Updates

**Assets (rebuild / cache-bust):**

- Updated CSS/JS are **rebuilt and minified** as part of the new artifact and
  carry **new fingerprints**, so browsers fetch fresh assets and never serve stale
  cached files (`BUILD_SYSTEM.md` §5, §6).
- Asset updates ride the **atomic release switch** with the code (§6), so markup and
  its referenced assets change together (no half-updated UI).

**Documentation:**

- `/docs` is the **single source of truth** and **MUST** be updated in the **same
  pull request** as the change it describes; stale docs are defects (Constitution
  §12). Each doc's `Version:` / `Last updated:` header is bumped accordingly.
- A change touching a contract, schema shape, or rule updates **every** affected
  document in the same PR so the docs stay internally consistent (Constitution §16).

---

## 10. Conformance Checklist

An update is policy-conformant only if **all** hold:

- [ ] Versioned per **SemVer**; `CHANGELOG.md` entry with required actions (§2).
- [ ] Schema changes are **forward-only**, ordered, idempotent, tenant-safe, and
      preserve **from-zero** (§3; `DATABASE_ARCHITECTURE.md` §12).
- [ ] Module changes are **additive**; contracts backward-compatible unless MAJOR
      (§4).
- [ ] New config keys ship **safe defaults**; no hand-editing required (§5).
- [ ] **Backup taken/verified before** any DB-affecting step (§7, §8;
      `BACKUP_POLICY.md`).
- [ ] **Expand → migrate → contract** followed for zero-downtime where applicable
      (§6).
- [ ] End-customer update runs **in the browser** (no terminal/SSH/SQL); flow is
      resumable and leaks no internals (§7).
- [ ] **Rollback** path confirmed (previous artifact retained); **restore is never
      automatic** (§8).
- [ ] Assets **rebuilt and cache-busted**; docs updated in the same PR (§9).

---

### Related Documents
`BUILD_SYSTEM.md` · `DATABASE_ARCHITECTURE.md` · `PROJECT_CONSTITUTION.md` (§14) ·
`DEPLOYMENT_GUIDE.md` · `BACKUP_POLICY.md` · `ARCHIVING_POLICY.md` ·
`VERSIONING_POLICY.md` · `INSTALLATION.md` · `INSTALLER_ARCHITECTURE.md` (Phase 6) ·
`MODULES.md` · `WORKSPACE_MODEL.md` · `SECURITY_GUIDE.md` · `BACKGROUND_JOBS.md` (Phase 12) ·
`AUDIT_POLICY.md` · `CHANGELOG.md`
