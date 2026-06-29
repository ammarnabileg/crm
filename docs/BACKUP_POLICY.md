# BACKUP POLICY — HaHireAI

> **Status:** Adopted (Phase 6) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `DATABASE_ARCHITECTURE.md`, `ARCHIVING_POLICY.md`. **Manager UI:** Phase 15 (`BACKUP_MANAGER.md`).

---

## 0. Purpose & Scope

This document is the **authoritative policy for protecting HaHireAI data against
loss**: **Backup, Restore, Export, Import, and Recovery**. It states *what is
backed up*, *how often*, *how long copies are kept*, *how they are verified and
encrypted*, *how a restore is performed and tested*, and *how tenant-aware
export/import works*. It is the policy that `DEPLOYMENT_GUIDE.md` §9 mandates exist
and that gates production readiness.

This is **design on paper** — no implementation, no canonical script. The data
model and the forward-only schema engine are `DATABASE_ARCHITECTURE.md`; the
lifecycle/retention of individual records (soft delete, archive, immutable trails,
GDPR erasure) is `ARCHIVING_POLICY.md`; the **operator-facing Backup Manager UI,
runbooks, scheduling internals, and storage drivers** are authored in **Phase 15**
(`BACKUP_MANAGER.md`) and owned by the **Observability** module (`MODULES.md` §
Operations). Where this document elaborates those, **they win**; where any disagrees
with `PROJECT_CONSTITUTION.md`, the **Constitution wins** (§11 Reliability, §10
Security). Interpretation keywords (**MUST**, **MUST NOT**, **SHOULD**, **SHOULD
NOT**, **MAY**) follow RFC 2119. A **MUST / MUST NOT** rule is binding; a violation
is a defect.

---

## 1. Principles

1. **Backups are mandatory.** Production data **MUST** be backed up on a defined
   schedule with **verified, rehearsed restore** (`DEPLOYMENT_GUIDE.md` §9;
   Constitution §11).
2. **A backup is not a backup until it restores.** An unverified backup is treated
   as **no backup** (§6).
3. **Restore is never automatic.** Restoring is always a deliberate,
   permission-gated, audited human decision — never triggered by an update, a
   failure, or a schedule (§5; `UPDATE_POLICY.md` §8).
4. **Backups are sensitive.** They contain tenant data and PII; they **MUST** be
   **encrypted** and access-controlled to the same standard as production
   (`SECURITY_GUIDE.md` §6, §10).
5. **Tenant-aware throughout.** Backup, restore, export, and import respect
   **workspace isolation** (`DATABASE_ARCHITECTURE.md` §8; Constitution §5);
   cross-workspace leakage via any of these paths is a critical security defect.
6. **Backup ≠ archive ≠ erasure.** A backup is a disaster-recovery copy; it is not
   the lifecycle mechanism for retention, archival, or right-to-erasure — those are
   `ARCHIVING_POLICY.md` (§7).

---

## 2. What Is Backed Up (scope)

A complete, restorable backup set covers the three stores that together constitute a
running installation. Anything **derived** (rebuildable from these) is **excluded**
to keep backups lean.

| Scope | Contents | Backed up? | Notes |
|---|---|---|---|
| **Database** | All MySQL schema + data: global tables (`users`, `permissions`, `plans`, `workspaces`, …) **and** every workspace-scoped table, plus immutable trails (`audit_logs`, `payments`, `ai_usage`, …) and the **migrations ledger**. | **MUST** | The system of record. Captured consistently (§3.3). Ledger ensures the restored DB's schema version is known. |
| **Uploaded files / storage** | Tenant files under `/storage` referenced by `files`/`application_documents` (uploads stored **outside** the web root — Constitution §10). | **MUST** | Must stay consistent with the DB rows that reference them (§3.3). |
| **Configuration** | `.env` (environment/secrets) and any non-default operator settings needed to bring the install back up. | **MUST** (secrets handled per §4) | Captured so a restored instance can boot. Secrets are encrypted; never stored in plaintext alongside data (Constitution §5, §10). |
| **Built code artifact** | Application code + `vendor/` + built assets. | **SHOULD NOT** (recoverable from the release artifact) | Recovered by re-deploying the immutable artifact (`BUILD_SYSTEM.md` §5), not from backup. |
| **Derived data** | `search_documents` (FULLTEXT projection), caches, compiled views. | **MAY skip** | Rebuildable from sources (`DATABASE_ARCHITECTURE.md` §6); re-derived after restore. |
| **OS / web server** | Host packages, server config. | Out of scope | Host-level concern; see `OBSERVABILITY.md`/Phase 16 ops. |

> A restore is only meaningful if **database, files, and config** are recovered to a
> **mutually consistent** point (§3.3, §5).

---

## 3. Backup Operation

### 3.1 Scheduled vs Manual

| Type | Trigger | Cadence (SHOULD; configurable) | Purpose |
|---|---|---|---|
| **Scheduled (automatic)** | The Observability module's scheduler (`MODULES.md`). | **Full** at least **daily**; more frequent **incremental** where supported. | Routine disaster-recovery baseline. |
| **Manual (on-demand)** | A System Owner via the Backup Manager (Phase 15), or the update flow. | Any time. | Ad-hoc safety (audits, migrations). |
| **Pre-update (mandatory)** | The browser update flow, **before** any DB-affecting step. | Every applicable update. | Safe point to restore to if an update fails (`UPDATE_POLICY.md` §8). |

- Cadence, retention, and destinations are **policy/plan configuration**, not
  hard-coded constants (consistent with `ARCHIVING_POLICY.md` §4); a plan tier MAY
  extend, but MUST NOT shorten, a legally mandated minimum.
- A `backups` record (global table — `DATABASE_ARCHITECTURE.md` §8) tracks each
  backup's status, scope, location reference, size, checksum, and verification
  result. It records **metadata only**, never backup contents.

### 3.2 Backup Destinations

- Backups **MUST** be stored **off the live web root** and **SHOULD** be replicated
  to **off-host** storage so a host failure does not also lose the backups.
- Destination credentials are **secrets** (environment only; Constitution §5).
- The set of supported destinations and drivers is specified with the Backup
  Manager (Phase 15, `BACKUP_MANAGER.md`).

### 3.3 Consistency

- The database backup **MUST** be **internally consistent** (a single transactional
  snapshot / consistent dump), so referential integrity holds on restore.
- **Files and DB must align.** The backup process **MUST** capture uploaded files
  and the database close enough in time (and reconcile dangling references) that a
  restored DB row's referenced file exists, and vice versa (§2).
- **Immutable trails are captured as-is.** Append-only tables (`audit_logs`,
  `payments`, `ai_usage`, …) are backed up unmodified (`DATABASE_ARCHITECTURE.md`
  §11; `ARCHIVING_POLICY.md` §3).

---

## 4. Encryption & Access Control

- **Encryption at rest.** Backup artifacts **MUST** be **encrypted at rest**
  (PII/tenant data — `SECURITY_GUIDE.md` §6, §10). An unencrypted backup of tenant
  data is a defect.
- **Encryption in transit.** Transfer to off-host destinations **MUST** use TLS.
- **Key management.** Encryption keys live in the **environment**, are **rotatable
  without code changes**, and are **never** stored beside the backup
  (`SECURITY_GUIDE.md` §6.3). Losing the key MUST be treated as losing the backup.
- **Least privilege.** Creating, listing, downloading, restoring, exporting, and
  importing are **distinct, permission-gated** `system.*` capabilities
  (deny-by-default — `PERMISSION_MODEL.md`; `SECURITY_GUIDE.md`). Downloading a
  backup is high-privilege and audited.
- **Audited.** Every backup, restore, export, and import is recorded in the
  platform trail (`system_audit_logs` — `AUDIT_POLICY.md`), including who and when;
  the **content** is never reproduced in the log.

---

## 5. Restore Operation (and the never-automatic rule)

**Restore brings the system (or a tenant) back to a known-good point.** It is the
counterpart to backup and the recovery action of last resort.

- **Restore is NEVER automatic.** No update, failure, schedule, or health event may
  trigger a restore. It is **always** an explicit, permission-gated, audited human
  decision (§1; `UPDATE_POLICY.md` §8; `DEPLOYMENT_GUIDE.md` §6). This rule is
  binding.
- **Restore the consistent set.** A full restore recovers **database + files +
  config** to the **same point** (§2, §3.3); restoring one without the others risks
  dangling references and is only acceptable for a deliberate, scoped recovery.
- **Schema/version awareness.** The restored database's **migrations ledger** tells
  the system its schema version; after restore, the platform applies any **pending
  forward migrations** to reach the running code's expected schema
  (`DATABASE_ARCHITECTURE.md` §12; `UPDATE_POLICY.md` §3). Restore **does not**
  reverse migrations (forward-only).
- **Destructive by nature — confirmed explicitly.** A full restore overwrites
  current state; the operator MUST confirm, and a **fresh pre-restore backup
  SHOULD** be taken first so the pre-restore state is itself recoverable.
- **Tenant-aware restore.** A **single-workspace** restore (recovering one tenant
  from a backup) **MUST** write only into that `workspace_id` and **MUST NOT** touch
  other tenants' rows (§1; `DATABASE_ARCHITECTURE.md` §8).
- **Secrets on restore.** Restoring config re-establishes environment values via the
  secure mechanism (§4); secrets are never surfaced in the browser
  (`INSTALLATION.md` §2.3).
- **Operator UI.** The guided restore experience (selection, confirmation,
  progress, post-restore health check) is the Backup Manager, **Phase 15**
  (`BACKUP_MANAGER.md`).

---

## 6. Verification & Disaster-Recovery Testing

A backup is only trustworthy if it has been **verified** and a restore has been
**rehearsed**.

- **Per-backup verification.** Each backup **MUST** be verified for integrity
  (e.g. checksum and a structural/restorability check); the result is recorded on
  the `backups` row (§3.1). A backup that fails verification **MUST** be flagged and
  **MUST NOT** be counted toward retention (§7) or relied on by the update flow
  (`UPDATE_POLICY.md` §8).
- **Periodic restore drills.** A **test restore into an isolated environment**
  **MUST** be performed on a defined cadence to prove the backups actually restore
  and to measure recovery time — disaster-recovery rehearsal
  (`DEPLOYMENT_GUIDE.md` §9). Production data used in a drill is sanitized/handled
  per `SECURITY_GUIDE.md` §10 and **never** restored into a lower environment
  unsanitized.
- **Recovery objectives.** The installation SHOULD define and track **RPO**
  (acceptable data loss, bounded by backup frequency — §3.1) and **RTO**
  (acceptable time to restore, validated by drills). Concrete targets and the full
  runbook live in `BACKUP_MANAGER.md` / `OBSERVABILITY.md` (Phase 15).
- **Production readiness gate.** Backups current **and** restore verified is a
  checklist item before any production cutover (`DEPLOYMENT_GUIDE.md` §11).

---

## 7. Retention

- **Retention is policy/plan configuration**, not hard-coded
  (`ARCHIVING_POLICY.md` §4). A typical scheme SHOULD keep a tiered window (e.g.
  recent dailies, then sparser weeklies/monthlies); the exact schedule is
  configured, not fixed here.
- **Verified-only counts.** Only **verified** backups (§6) satisfy a retention
  requirement.
- **Expired backups are purged in a bounded, logged process** (`backups` rows track
  status; purge is audited — §4). Purging a backup destroys a recovery point and is
  therefore deliberate.
- **Backups vs record lifecycle.** Backup retention is **independent** of record
  retention. A backup **MUST NOT** be used to resurrect data that was **lawfully
  erased** (GDPR/right-to-erasure, `ARCHIVING_POLICY.md` §5): erasure procedures
  account for backups (e.g. by documented exclusion windows / re-application of
  erasure after restore), so a restore can never silently reintroduce erased PII.
- **Legal holds override** retention and purge: held data MUST NOT be purged while a
  hold is active (`ARCHIVING_POLICY.md` §4).

---

## 8. Export & Import (data portability, tenant-aware)

Export/import is **logical, portable data movement** — distinct from a
disaster-recovery backup (which is a physical/consistent system copy).

**Export:**

- A **workspace export** produces a portable package of **one tenant's** data
  (its workspace-scoped rows and referenced files) for **data portability** /
  customer-owned copies. It **MUST** include only that `workspace_id`'s data and
  **MUST NOT** include other tenants' data or platform-global secrets
  (`DATABASE_ARCHITECTURE.md` §8; Constitution §5).
- Export is **permission-gated and audited** (§4); it contains PII and **SHOULD** be
  **encrypted** or delivered over a secure, access-controlled channel
  (`SECURITY_GUIDE.md` §10).
- Identifiers stay **ULID `CHAR(26)`** (`DATABASE_ARCHITECTURE.md` §4) so an export
  is internally consistent and re-importable without re-keying.

**Import:**

- Import ingests an export package into a target workspace. It **MUST** validate the
  package, enforce tenant scoping (**all imported rows land under the target
  `workspace_id`**), and run through the **normal domain/validation layer** — never
  a raw, unscoped bulk write (`DATABASE_ARCHITECTURE.md` §8;
  `SECURITY_GUIDE.md` §4).
- Import **MUST NOT** violate invariants (e.g. uniqueness like
  `(workspace_id, user_id)` on memberships — `DATABASE_ARCHITECTURE.md` §6) and
  **MUST NOT** overwrite another tenant's data.
- Import is **permission-gated, audited, and ideally previewable** before commit;
  prepared statements only (Constitution §6).

> Export/import is also how a tenant can leave with its data and how data can be
> moved between installations; it complements, but does **not** replace, scheduled
> backups (§3).

---

## 9. Backup-Scope Quick Reference

| Item | In a full backup? | Recovery method | Tenant-aware? |
|---|---|---|---|
| Database (global + workspace + immutable trails + ledger) | **Yes (MUST)** | Restore (§5) | Yes — single-workspace restore scopes by `workspace_id` |
| Uploaded files / `/storage` | **Yes (MUST)** | Restore alongside DB (§3.3) | Yes |
| `.env` / config / secrets | **Yes (MUST; encrypted)** | Restore config (§4, §5) | Platform-level |
| Migrations ledger | **Yes (MUST)** | Restored with DB; pending forward migrations applied after (§5) | n/a |
| Built code + `vendor/` + assets | **No (SHOULD NOT)** | Re-deploy immutable artifact (`BUILD_SYSTEM.md` §5) | n/a |
| Derived (`search_documents`, caches, compiled views) | **MAY skip** | Re-derive after restore (`DATABASE_ARCHITECTURE.md` §6) | Yes (rebuilt per workspace) |
| Single-tenant logical copy | **Export** (not the DR backup) | Import into target workspace (§8) | **Yes** |

---

## 10. Conformance Checklist

A backup posture is policy-conformant only if **all** hold:

- [ ] Scheduled backups exist (full ≥ daily); **pre-update** backup is mandatory
      (§3.1; `UPDATE_POLICY.md` §8).
- [ ] Backup set covers **database + files + config**, consistently captured
      (§2, §3.3).
- [ ] Backups are **encrypted at rest and in transit**; keys live in the
      environment and are rotatable (§4).
- [ ] Backup access is **least-privilege**, and every backup/restore/export/import
      is **audited** (§4).
- [ ] Each backup is **verified**; only verified backups count toward retention
      (§6, §7).
- [ ] **Restore drills** are performed on a cadence; **RPO/RTO** defined (§6).
- [ ] **Restore is never automatic** — always deliberate, permission-gated, audited
      (§5).
- [ ] Restore recovers a **consistent set** and applies **pending forward
      migrations** (never reverses them) (§5).
- [ ] Export/import is **tenant-scoped**, validated, audited, and never crosses
      `workspace_id` boundaries (§8).
- [ ] Retention is **configurable**, purge is **bounded/logged**, lawful **erasure**
      and **legal holds** are honored across backups (§7;
      `ARCHIVING_POLICY.md` §5).

---

### Related Documents
`DATABASE_ARCHITECTURE.md` · `ARCHIVING_POLICY.md` · `BACKUP_MANAGER.md` (Phase 15) ·
`OBSERVABILITY.md` (Phase 15) · `DEPLOYMENT_GUIDE.md` · `UPDATE_POLICY.md` ·
`BUILD_SYSTEM.md` · `SECURITY_GUIDE.md` · `AUDIT_POLICY.md` · `PERMISSION_MODEL.md` ·
`WORKSPACE_MODEL.md` · `INSTALLATION.md` · `PROJECT_CONSTITUTION.md` (§10, §11)
