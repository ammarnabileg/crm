# HEALTH CHECK SYSTEM — HaHireAI

> **Status:** Adopted (Phase 6) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `ARCHITECTURE.md` (§6). **Operations console:** Phase 15 (`HEALTH_CENTER.md`).

---

## 0. Purpose & Scope

The **Health Checker** is the Core Kernel component (`ARCHITECTURE.md` §6) that
answers one operational question — *is this installation sound, right now?* — by
running a set of independent **probes** and aggregating their results into a
single, machine-readable status. It is **pluggable, not a monolithic check**: each
concern (PHP version, database, mail, AI providers, disk) is a self-contained
probe that **registers itself**, exactly as modules register routes and
permissions. This document is the **design on paper** — the probe contract,
registration, aggregation, usage, and the authoritative **probe catalog**; code
fragments are **illustrative only** and are not source.

**Supremacy.** This guide defers to `ARCHITECTURE.md` §6 and the
`PROJECT_CONSTITUTION.md`; where any statement here conflicts, those win.
Interpretation keywords (**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**,
**MAY**) follow [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119); a
**MUST / MUST NOT** rule is binding. **Boundary:** the Health Checker *produces*
and *aggregates* probe results; it does **not** own dashboards, alerting,
time-series retention, or incident lifecycle — those belong to the
**Observability** module in Phase 15 (`OBSERVABILITY.md`, `HEALTH_CENTER.md`). The
kernel publishes the probe surface; Phase 15 consumes it.

---

## 1. Design Principles

1. **Probes, not a procedure.** Health is the **aggregate of many small,
   independent checks**, each owning one concern with its own severity and
   remediation. A single `isHealthy()` god-method is a defect.
2. **Pluggable like modules.** A probe is **registered**, never discovered by
   filesystem guessing (`ARCHITECTURE.md` §6; mirrors the Module Registry). Adding
   a probe is additive and MUST require no change to the checker, the aggregator,
   or other probes (`ARCHITECTURE.md` §9).
3. **No business logic, read-only.** Probes live in the Core Kernel layer with
   **no business behavior** (`PROJECT_STRUCTURE.md` §5) and **MUST** be read-only —
   they observe; they never mutate state, write tenant data, or send outbound.
4. **Fail safe, never fail loud.** A probe that itself throws is recorded as a
   failed result — it **MUST NOT** crash the checker or the page that invoked it.
   Every probe is **time-boxed**, so a slow dependency surfaces as a degraded
   result, never a hung request.
5. **Safe output.** Probe output is operator-facing and **MUST NOT** leak secrets,
   credentials, connection strings, or PII (`SECURITY_GUIDE.md` §6;
   `CODING_STANDARD.md` §7) — "MySQL reachable", never the password it used.
6. **Tenant-agnostic.** Probes assess **platform** health in the Platform Context;
   they do not read workspace rows. Per-workspace usage views are a separate Phase
   15 concern (`OBSERVABILITY.md` §2).

---

## 2. Anatomy of a Probe

Every probe is a small object exposing a uniform shape: it declares its identity
and severity, performs one bounded check, and returns a typed result.

| Field | Meaning |
|---|---|
| `key` | Stable, unique identifier in `resource.action` style — e.g. `php.version`, `db.connection`. Used for aggregation, filtering, and Phase 15 fingerprinting. Stable across releases. |
| `name` | Human-readable label for operators — e.g. "PHP Version". |
| `severity` | One of **`critical`**, **`warning`**, **`info`** — the *weight* of a failure (see §3). |
| `group` | Category for display/grouping (e.g. `runtime`, `datastore`, `messaging`, `providers`, `resources`). |
| `description` | What the probe checks and why it matters. |
| `remediation` | Operator-facing hint on how to fix a failure (the most valuable field during install/incident). |
| `timeout` | Per-probe upper bound; exceeding it yields a `degraded` result with a timeout reason. |

A probe's **check** returns a **Probe Result**, not a bare boolean:

| Result field | Meaning |
|---|---|
| `status` | **`pass`** / **`warn`** / **`fail`** / **`skip`** (skip = not applicable in this environment). |
| `summary` | One-line, safe outcome — "PHP 8.3.7 detected", "Mail transport not configured". |
| `detail` | Optional safe, structured context (measured value, threshold) — never secrets/PII. |
| `remediation` | The hint, echoed when `warn`/`fail` so the operator sees the fix inline. |
| `measuredAt` | Timestamp of the check (UTC), so Phase 15 can trend it. |
| `duration` | How long the probe took (feeds the bounded-run guarantee). |

> **EXAMPLE — illustrative probe contract, not implementation:**
> ```php
> // declare(strict_types=1); namespace HaHireAI\Core\Health\Contracts;
> interface HealthProbe
> {
>     public function key(): string;        // 'db.connection'
>     public function name(): string;       // 'MySQL Connection'
>     public function severity(): Severity;  // Severity::Critical
>     public function group(): string;      // 'datastore'
>     public function check(): ProbeResult; // pass | warn | fail | skip
> }
> ```

Probes follow the kernel coding rules (`CODING_STANDARD.md`): `strict_types=1`,
PSR-12, `final` where possible, **constructor injection** of any collaborator
(e.g. the PDO connection manager, the config loader), **no service locator**, and
**no static state**. A probe depends on **contracts**, never another module's
internals.

---

## 3. Severity Model

Severity expresses *how bad it is if this probe fails* and drives both the
overall status (§4) and the operator's triage order.

| Severity | Meaning | A failure means… |
|---|---|---|
| **`critical`** | The platform **cannot function correctly** without this. | Install MUST NOT complete; production is **unhealthy**; page/alert. |
| **`warning`** | Degraded or risky, but the platform still runs. | Install MAY proceed with a clear warning; status is **degraded**. |
| **`info`** | Advisory / best-practice signal. | Informational only; does not by itself change overall status. |

Severity is a property of the **probe**, not the result. A `critical` probe that
returns `fail` produces a critical failure; the same probe returning `warn`
(e.g. a deprecated-but-working config) produces a warning. A `skip` never
contributes to the overall status (the concern is not applicable here).

---

## 4. Aggregation & Overall Status

The checker runs the registered probe set, collects each **Probe Result**, and
folds them into one **Health Report**. The fold rule is deterministic:

```
overall = HEALTHY     if no probe failed
        = DEGRADED    if ≥1 warning-severity probe failed, but no critical failed
        = UNHEALTHY   if ≥1 critical-severity probe failed
```

- The overall status is the **worst** contributing result; `info` and `skip`
  never raise it. The Health Report carries the overall status, a counts summary
  (pass/warn/fail/skip), the full ordered probe results, total run duration, and a
  generation timestamp — **machine-readable first** (so deploy tooling and Phase 15
  can consume it), rendered for humans second.
- **Fail-closed for install:** the Installer treats *anything other than*
  `HEALTHY`/`DEGRADED-with-acknowledgement` as a stop (`Installer.md` §9); the
  deploy readiness check treats `UNHEALTHY` as a blocker (`DEPLOYMENT_GUIDE.md`
  §10–§11).

> **EXAMPLE — illustrative aggregate shape, not a wire contract:**
> ```json
> {
>   "status": "degraded",
>   "summary": { "pass": 12, "warn": 1, "fail": 0, "skip": 2 },
>   "duration_ms": 184,
>   "generated_at": "2026-06-27T10:15:30Z",
>   "probes": [
>     { "key": "php.version",  "status": "pass", "summary": "PHP 8.3.7 detected" },
>     { "key": "mail.transport", "status": "warn", "summary": "Mail not configured",
>       "remediation": "Set MAIL_* in the environment to enable notifications." }
>   ]
> }
> ```

The report **MUST NOT** include stack traces, secrets, or connection strings even
when probes fail (`SECURITY_GUIDE.md` §6.2; `CODING_STANDARD.md` §7).

---

## 5. Registration & Discovery

Probes register through the same explicit mechanism as the rest of the kernel — no
auto-scan, no filesystem reflection (`ARCHITECTURE.md` §6).

- **Kernel-provided probes** (runtime, storage) register via the Core Kernel's
  health service provider at boot; **module-contributed probes** register via the
  owning module's **service provider** in the register/boot phase
  (`ARCHITECTURE.md` §7; `Core_Kernel.md` §2) — Mail contributes the mail probe,
  the AI Engine the AI-providers probe, the Integration Platform the API-providers
  and SSL probes, the Database module the MySQL probes.
- Registration is **additive**: a new probe touches **no** existing probe and
  **no** aggregator code (`ARCHITECTURE.md` §9). Probe `key`s **MUST** be unique; a
  duplicate is a defect.
- A probe **MAY** be **conditional** (e.g. the Queue probe `skip`s when no driver
  is configured) so optional capabilities degrade gracefully rather than reporting
  a false failure (`ARCHITECTURE.md` §5).

> **EXAMPLE — illustrative registration in a module provider:**
> ```php
> $registry->register(new MailTransportProbe($mailConfig)); // ServiceProvider::boot()
> ```

The **Health Registry** is the single in-memory collection of registered probes
(the kernel's "Health Probe" runtime construct — `Core_Kernel.md` §8). It owns no
database tables; snapshot persistence over time is a Phase 15 concern
(`OBSERVABILITY.md` §8, *Health Snapshot*).

---

## 6. Where the Health Checker Is Used

The same checker, the same probes, three consumers:

1. **Installer — final step.** The wizard's **Health check** step
   (`Installer.md` §2, step 11; `INSTALLATION.md` §2.2) invokes the checker to
   confirm a sound install before **Finish** and self-lock. Critical failures
   stop the install with the probe's **remediation** shown inline in the live
   setup log; the customer never sees a stack trace (`Installer.md` §9).
2. **"Run Full Diagnostics" button.** An on-demand run from the System
   Administration / operations surface, gated by **`system.diagnostics.run`**
   (`Core_Kernel.md` §6; `Observability.md` §6) in the **Platform Context**. It
   re-runs every probe and renders the full report for the operator.
3. **Operations dashboard (Phase 15).** The **Health Center** in the
   **Observability** module consumes the probe surface continuously, snapshots
   results as time-series, groups by module, and drives alerting
   (`Observability.md` §2; `HEALTH_CENTER.md`, `OBSERVABILITY.md`). Viewing the
   aggregated readout is gated by **`system.health.view`**; running diagnostics by
   **`system.diagnostics.run`** (`Core_Kernel.md` §6).
4. **Deploy / readiness check.** A release MUST satisfy a **green health check**
   before cutover; the machine-readable report is the source for that gate
   (`DEPLOYMENT_GUIDE.md` §10, §11).

All operator-facing invocations are **permission-gated** (deny-by-default,
`SECURITY_GUIDE.md` §3) and surface only in the **Platform Context**
(`PERMISSION_MODEL.md` §6). Health endpoints **MUST NOT** be unauthenticated; the
only pre-auth use is *inside* the install flow, itself gated by install-state and
the lock (`Installer.md` §6).

---

## 7. Execution Model

- **On-demand, synchronous** for the Installer and "Run Full Diagnostics": run all
  probes, aggregate, return one report. **Selective runs** are supported — a caller
  MAY run one probe or a `group` (e.g. re-checking only `db.*` after a connection
  change).
- **Bounded & isolated:** each probe is time-boxed (§1) and runs independently; a
  timeout or thrown exception becomes a single `warn`/`fail` result and the run
  continues — never a hang or a crash.
- **Cache-aware (Phase 15):** continuous monitoring MAY cache recent results;
  the Installer and explicit diagnostics always run **fresh**. Caching policy is
  owned by Observability, not the kernel.
- Probe activity SHOULD be logged via the PSR-3 **Logger** with safe context
  (`ARCHITECTURE.md` §6); failures feed Phase 15 Error Tracking and alerting
  (`OBSERVABILITY.md`, `ERROR_TRACKING.md`).

---

## 8. Probe Catalog

The authoritative initial probe set. `key`s are stable; severities are the
default for a clean failure. Module-contributed probes are registered by the
owning module's provider (§5).

| # | Probe (`key`) | Severity | Group | Checks | Remediation hint |
|---|---|---|---|---|---|
| 1 | `php.version` | critical | runtime | PHP **≥ 8.3** (`INSTALLATION.md` §1.1). | Upgrade PHP to 8.3+ on the host. |
| 2 | `php.extensions` | critical | runtime | All required extensions present: `pdo_mysql`, `mbstring`, `openssl`, `json`, `ctype`, `fileinfo`, `curl`, `tokenizer`, `pcre`; `intl`/`zip` as `warn` (`INSTALLATION.md` §1.2). | Enable the missing extension(s) in `php.ini` and restart PHP. |
| 3 | `db.connection` | critical | datastore | MySQL **8+** reachable via PDO with the configured credentials (`INSTALLATION.md` §1.1). | Verify DB host/port/credentials in the environment; confirm MySQL is running. |
| 4 | `db.privileges` | critical | datastore | Connected user can run required DDL/DML (pre-write check used by the Installer connection test — `Installer.md` §9). | Grant the app user the required privileges on its database. |
| 5 | `db.migrations` | warning | datastore | Migration ledger present and **up to date** (no pending forward migrations — `DEPLOYMENT_GUIDE.md` §4). | Run the bespoke migration runner to apply pending migrations. |
| 6 | `storage.writable` | critical | resources | `/storage` (logs, cache, compiled views, uploads) exists and is **writable** (`PROJECT_STRUCTURE.md` §2; `Installer.md` step Permissions). | Make `/storage` and its subdirectories writable by the web user. |
| 7 | `file.permissions` | warning | resources | Sensitive paths are **not world-writable**; `/public` is the only web-exposed dir; `.env` not web-reachable (`SECURITY_GUIDE.md` §8). | Tighten file modes; ensure web root points only at `/public`. |
| 8 | `mail.transport` | warning | messaging | Mail transport configured and reachable (notifications/password reset depend on it — `SECURITY_GUIDE.md` §2.4). | Configure `MAIL_*` in the environment; verify SMTP/transport reachability. |
| 9 | `queue.driver` | warning | messaging | Queue/background-job driver configured and reachable; `skip` if none configured (`BACKGROUND_JOBS.md`, Phase 12). | Configure the queue driver; ensure a worker is running for async/AI work. |
| 10 | `scheduler.cron` | warning | messaging | Scheduler/cron heartbeat is recent (scheduled tasks are running). | Configure the system cron to invoke the scheduler entrypoint. |
| 11 | `cache.driver` | warning | runtime | Cache driver reachable and read/write round-trips (`app/Infrastructure` cache driver — `PROJECT_STRUCTURE.md` §2). | Configure/repair the cache driver; verify connectivity. |
| 12 | `ai.providers` | warning | providers | Configured **AI providers** are reachable and credentials valid; metered per workspace (`AI_ENGINE.md`; `SECURITY_GUIDE.md` §4.2). Egress only via the central engine. | Verify AI provider keys/endpoints; check provider status and quota. |
| 13 | `api.providers` | warning | providers | Outbound **integration/API connectors** reachable via the Integration Platform (egress is centralized — `MODULES.md` §5; Phase 13). | Verify connector configuration/credentials; check the external service. |
| 14 | `ssl.certificate` | warning | providers | HTTPS in effect and the TLS certificate is **valid and not near expiry**; HSTS posture (`SECURITY_GUIDE.md` §8). | Renew/replace the certificate; enforce HTTPS + HSTS at the web server. |
| 15 | `disk.space` | warning | resources | Free disk on the storage volume above a configurable threshold. | Free space or expand the volume; rotate/clean logs and temp files. |
| 16 | `memory.limit` | warning | runtime | PHP `memory_limit` meets a sane minimum for the workload. | Raise `memory_limit` in `php.ini` to the recommended value. |
| 17 | `timezone.config` | info | runtime | A valid timezone is configured (UTC storage; AR/EN locale formatting — `INSTALLATION.md` §2.2 step Platform settings). | Set a valid `date.timezone` / platform timezone setting. |
| 18 | `opcache.enabled` | warning | runtime | **OPcache** enabled in production (`DEPLOYMENT_GUIDE.md` §2, §11). `skip`/`info` in local/dev. | Enable and warm OPcache in production. |
| 19 | `env.required` | critical | runtime | All **required environment keys** present and valid before cutover (`DEPLOYMENT_GUIDE.md` §3; `.env.example`). | Populate the missing required keys in the environment. |
| 20 | `app.writable_lock` | info | runtime | The install lock marker is consistent with install state (installed ⇒ locked — `Installer.md` §8). | Re-run the Installer only against an uninstalled system; restore the lock. |

> The catalog is **extensible**: modules contribute probes via their providers
> (§5). New probes are added by registration only — no change to this checker, the
> aggregator, or existing probes (`ARCHITECTURE.md` §9). The version-pinned
> extension list is owned with the Installer (`INSTALLATION.md` §1.2); if this
> table and that list ever differ, the installer spec governs.

---

## 9. Security & Safety Rules (binding)

- Probe output **MUST NOT** contain secrets, credentials, connection strings,
  tokens, or PII — only safe, operator-facing facts (`SECURITY_GUIDE.md` §6.2,
  §10; `CODING_STANDARD.md` §7).
- A probe **MUST** be **read-only** (no writes, no outbound sends, no tenant data
  access) and **MUST NOT** crash the host request when it fails — it is contained
  as a single failed result (§1, §7).
- Health/diagnostics endpoints **MUST** be permission-gated
  (`system.health.view` / `system.diagnostics.run`), Platform-Context-only; there
  is **no** unauthenticated health endpoint except inside the gated install flow
  (`SECURITY_GUIDE.md` §3; `Installer.md` §6).
- All probe code obeys `CODING_STANDARD.md`: `strict_types`, PSR-12, constructor
  injection, no facades/service locator, no global/static state.

---

## 10. Phase 15 Hand-off (Observability)

This kernel component is the **producer**; the **Observability** module is the
**consumer and aggregator** (`Observability.md` §1). The **Health Center** reads
the probe surface to build the real-time, per-module view and to snapshot results
as time-series (`HEALTH_CENTER.md`, `OBSERVABILITY.md`; *Health Snapshot* —
`Observability.md` §8); the **Alert Engine** turns degraded/unhealthy results into
alerts delivered **only** through the Integration Platform — the kernel never
sends anything itself (`Observability.md` §2, §7). Probe failures correlate with
the global Error Handler's `request_id` for **Error Tracking** (`ERROR_TRACKING.md`,
`ERROR_HANDLING_GUIDE.md`). Until Phase 15 ships, the Health Checker still fully
serves the **Installer** and the **deploy readiness** gate; the operations console
is additive on top.

---

### Related Documents

`ARCHITECTURE.md` (§6) · `Core_Kernel.md` · `PROJECT_STRUCTURE.md` ·
`Installer.md` · `INSTALLATION.md` · `DEPLOYMENT_GUIDE.md` · `SECURITY_GUIDE.md` ·
`CODING_STANDARD.md` · `PERMISSION_MODEL.md` · `MODULES.md` · `AI_ENGINE.md` ·
`ERROR_HANDLING_GUIDE.md` · `Observability.md` · `OBSERVABILITY.md` (Phase 15) ·
`HEALTH_CENTER.md` (Phase 15) · `ERROR_TRACKING.md` (Phase 15)
