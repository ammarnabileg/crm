# OBSERVABILITY, DIAGNOSTICS & OPERATIONS — HaHireAI

> **Status:** Implemented (Phase 15, core) · **Version:** 1.0.0 · **Last updated:** 2026-06-28
> **Defers to:** `PROJECT_CONSTITUTION.md`, `ARCHITECTURE.md`, `HEALTH_CHECK_SYSTEM.md`, `ERROR_HANDLING_GUIDE.md`.

---

## 1. Purpose & Scope

This phase makes the platform **operable**: the System Owner can see health,
metrics, captured errors, alerts, and backups — and act on them. It deliberately
**reuses existing infrastructure** (the `HealthChecker` + probes, the `Logger`,
the global `ErrorHandler`, the event bus, and the `Audit` service) rather than
bolting on a parallel telemetry stack.

Everything here lives in the **Platform Context** (System Owner) — the same single
`User`, a different context — surfaced through the one dynamic sidebar.

**Interpretation keywords** (MUST / MUST NOT / SHOULD / MAY) follow RFC 2119.

---

## 2. Health

The pluggable `HealthChecker` (Phase 7) already folds probe results into an
overall status. Probes registered by default: **php.version**, **storage.writable**,
**database** (connectivity round-trip). `/health` exposes the summary; the
diagnostics dashboard renders each probe with its severity and message.

---

## 3. Metrics

`MetricsService::platformSnapshot()` computes the system-wide picture **from
existing domain tables** — no separate metrics pipeline to drift out of sync:

- **Tenancy:** workspaces (total/active), users (+ system owners).
- **Revenue:** subscriptions by status + approximate **MRR**.
- **Recruitment:** jobs (+ published), applications.
- **AI:** sessions, tokens, cost.
- **Automation / Integration:** workflow executions (+ failed), webhook
  deliveries (+ failed), active API tokens.
- **Health:** errors in 24h, open alerts.

Every read is resilient — a missing/locked table contributes `0`, never an error,
so the dashboard is robust during partial outages.

---

## 4. Error Tracking

The global `ErrorHandler` (Core) now **publishes a `system.error` event** in
addition to logging. The Observability module subscribes and `ErrorTracker`
persists each event to `error_events` (message, class, file:line, fingerprint for
grouping). Two guarantees:

- **Core stays DB-agnostic** — it emits an event; persistence is a reactor on the
  bus (`ARCHITECTURE.md` §4).
- **Telemetry never masks the original error** — both the emit (in the handler)
  and the persist (in the listener) are wrapped so a logging failure can't
  escalate.

---

## 5. Monitors & Alerts

`MonitorService::tick($now)` evaluates platform monitors and opens/auto-resolves
`alerts`. Each monitor opens **at most one** open alert and resolves it when the
condition clears:

| Monitor | Trips when | Severity |
|---|---|---|
| `subscriptions.suspended` | any workspace suspended | warning |
| `subscriptions.past_due` | any subscription past due | warning |
| `webhooks.failures_24h` | ≥ 5 failed deliveries in 24h | warning |
| `errors.spike_1h` | ≥ 10 errors in the last hour | critical |

The tick runs when the ops dashboard is viewed (no cron required to see current
state) and is also safe to schedule. `$now` is injectable, so the whole monitor
behaviour is unit-tested deterministically.

---

## 6. Backups

`BackupService::run()` records a backup in `backups` and writes a verifiable
**manifest** (every table + row count) to `storage/backups/`. A full SQL dump is
delegated to `mysqldump` / managed snapshots in production; the manifest gives a
restore-time source of truth and proves the run. Each run is recorded
(running → completed | failed) and audited (`observability.backup.run`).

---

## 7. The Ops Dashboard (Platform Context)

Rendered through `PlatformShell` — the **same** layout and **same** single
`SidebarBuilder`, only `context = 'platform'`, so the sidebar shows
System/Companies/Users/Subscriptions/Diagnostics/… (no separate admin sidebar).

| Route | Permission | Shows |
|---|---|---|
| `GET /admin` | `system.dashboard.view` | Overview: metrics, open alerts, health badge |
| `GET /admin/diagnostics` | `system.diagnostics.run` | Probes, recent errors, alerts, backups (+ run backup) |
| `GET /admin/diagnostics/json` | `system.observability.view` | Machine-readable health + metrics + alert count |
| `POST /admin/diagnostics/backup` | `system.diagnostics.run` | Trigger a backup |

Access requires a **System Owner** (`PlatformContext` — a `User` holding
`system.*`, never a separate account type). The JSON endpoint is intended for
external uptime/monitoring integrations.

---

## 8. Deferred (designed)

- The remaining platform screens (`/admin/workspaces`, `/admin/users`,
  `/admin/subscriptions`, `/admin/ai`, `/admin/audit`, `/admin/settings`) —
  controllers on the same `PlatformShell`/`PlatformContext`.
- Log **viewer/streaming** UI (the `Logger` already writes structured logs),
  per-request latency histograms, and external sinks (OpenTelemetry/Sentry) as
  additional `system.error`/metrics reactors.
- Scheduled monitor ticks + **alert delivery** (email/webhook) and full
  `mysqldump` automation with retention.

These are screens/adapters on the primitives here (health, metrics, the event
bus, the platform shell), not new architecture.

---

## 9. Acceptance (Phase 15)

Verified on a live MySQL 8 database **and** an end-to-end HTTP run (System Owner
login → `/admin` + `/admin/diagnostics/json`):

- ✅ `platformSnapshot()` reports tenancy/revenue/recruitment/AI/automation/health
  totals, resiliently.
- ✅ Captured errors persist via the `system.error` event; `recent()` is
  deterministically ordered.
- ✅ Monitors open an alert on a tripped condition, don't duplicate, and
  auto-resolve when it clears.
- ✅ Backups write a manifest and record completion.
- ✅ The diagnostics JSON reports healthy DB/PHP/storage probes + live metrics.

---

### Related Documents

`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `HEALTH_CHECK_SYSTEM.md` ·
`ERROR_HANDLING_GUIDE.md` · `BILLING_PLATFORM.md` · `INTEGRATION_PLATFORM.md` ·
`PERMISSION_MODEL.md` · `SIDEBAR_MODEL.md` · `SECURITY_GUIDE.md`
