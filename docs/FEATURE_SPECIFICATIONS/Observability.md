# FEATURE SPEC — Observability

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Observability · **Layer:** Operations · **Implemented in:** Phase 15
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

Observability is the platform's **operations brain** — the single place where the
health, performance, logs, errors, security signals, backups, and maintenance of
HaHireAI are collected, visualized, and acted upon. It exists so that
**monitoring lives in exactly one module, never scattered inside business code**
(`ARCHITECTURE.md` §8: auditing, logging, and metrics are emitted via shared
services, never hand-rolled inside modules).

Other modules **emit** signals (via the shared Logger, the Event Bus, and metric
helpers) and **expose** health probes (the Core Kernel's pluggable Health
Checker). Observability is the **consumer and aggregator** of those signals: it
reads health from all modules through probes, ingests events and metrics, and
turns them into dashboards, monitors, and alerts. It **MUST NOT** require any
business module to embed monitoring logic of its own.

## 2. Scope

**In scope**

- **Health Center** — aggregated, real-time view of system and per-module health
  via the Core Kernel's pluggable health probes.
- **Metrics Engine** — collection, aggregation, and time-series storage of
  platform and per-workspace metrics.
- **Log Explorer** — searchable, structured log view sourced from the shared
  PSR-3 Logger output (no module writes its own log files).
- **Error Tracking** — capture, grouping, and trend of exceptions surfaced by the
  global Error Handler.
- **Domain monitors** — **performance**, **queue** (background jobs),
  **AI** (sessions/cost/latency), and **database** monitors.
- **Security Center** — aggregated security signals (auth failures, lockouts,
  permission-denied spikes, cross-tenant attempt alarms).
- **Backup Manager** — schedule, status, verification, and restore visibility for
  platform backups.
- **Maintenance Center** — maintenance windows, mode toggles, and operational
  runbooks/tasks.
- **Alert Engine** — rule-based alerting whose **outbound delivery (email,
  messaging, webhook) goes through the Integration Platform**.
- **Operations Dashboard** — the consolidated System-Owner operations view.
- **Workspace-scoped usage views** — read-only usage/health surfaced to permitted
  workspace members, filtered by `workspace_id`.

**Out of scope**

- **Implementing monitoring inside business modules** — forbidden; modules emit
  signals and expose probes only.
- **Owning the data being observed** — recruitment, billing, and AI data belong to
  their modules; Observability reads metrics/health/logs, not their tables.
- **Sending external notifications directly** — alert delivery is brokered by the
  Integration Platform; Observability never opens an outbound connection itself.
- **Audit trail of business actions** — the immutable who/did/what audit trail is
  the **Audit** module's concern; Observability covers operational telemetry.
- **Feature availability/limits** — owned by Licensing (whose usage events
  Observability may surface).

## 3. Inputs

- **Health probe results** — from the Core Kernel Health Checker and each module's
  registered probes.
- **Metrics** — counters, gauges, timings emitted by modules via shared metric
  helpers (request latency, queue depth, AI cost/latency, DB query stats).
- **Structured logs** — PSR-3 log records from the shared Logger across all
  modules and layers.
- **Errors/exceptions** — surfaced by the global Error Handler with safe context
  and a correlation `request_id`.
- **Domain events** — operationally relevant events from the Event Bus (e.g.
  `licensing.usage.exhausted`, `integration.webhook.delivery_failed`).
- **Backup/maintenance signals** — backup job results and maintenance-window
  changes.
- **Alert rule definitions** — thresholds and conditions configured by System
  Owners.

## 4. Outputs

- **Dashboards & views** — Health Center, Metrics Engine views, Log Explorer,
  Error Tracking, domain monitors, Security Center, and the Operations Dashboard.
- **Alerts** — fired when rules trip; delivered via the Integration Platform to
  configured channels (email/messaging/webhook) with severity and context.
- **Health/status readouts** — machine-readable health for the platform and per
  module (consumed by deployment/operations tooling).
- **Backup & maintenance status** — current backup state, last verified restore,
  active maintenance windows.
- **Workspace-scoped usage readouts** — per-workspace usage/health for permitted
  members, strictly filtered by `workspace_id`.
- **Operational metrics/aggregations** — retained time-series for trend analysis.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Core Kernel** — Health Checker (probes), Logger (PSR-3), Error Handler, Event
  Dispatcher, configuration.
- **Event Bus** — subscribes to operationally relevant events from all modules.
- **Integration Platform** — the **only** path for outbound alert delivery
  (email/messaging/webhook); Observability never calls an external service
  directly (`MODULES.md` §5).
- **Workflow Engine** — reads queue/background-job state for the queue monitor.
- **AI Engine** — reads AI session/usage/cost/latency signals for the AI monitor
  (emitted by the AI Engine; not pulled from its tables).
- **Database** — reads DB performance/health signals via probes for the database
  monitor.
- **Permissions** — diagnostics actions and operational views are permission-gated;
  workspace usage views combine the tenant guard with workspace permissions.
- **Licensing / Subscriptions** — surfaces usage-vs-entitlement and commercial
  health where relevant.
- **Audit** — operational actions (e.g. trigger backup, enter maintenance mode) are
  auditable.

## 6. Permissions (keys this module declares)

System-scoped (Platform Context only, held by System Owners):

- `system.diagnostics.run` — run diagnostics/health checks and access the full
  operations console (Health Center, Log Explorer, Error Tracking, monitors,
  Security Center, Backup Manager, Maintenance Center, Alert Engine, Operations
  Dashboard).
- `system.diagnostics.manage` — configure alert rules, backup schedules, and
  maintenance windows.

Workspace-scoped:

- `usage.view` — view this workspace's own operational/usage telemetry (read-only,
  strictly `workspace_id`-filtered); platform-wide telemetry remains
  Platform-Context-only.

> Deep platform telemetry is visible **only** in the Platform Context with
> `system.*` permissions; workspace members see only their own workspace's usage,
> never another tenant's data (`PERMISSION_MODEL.md` §6; `SECURITY_GUIDE.md` §4.2).

## 7. Events (Published / Subscribed)

**Published** (`observability.<entity>.<event>`, past tense):

- `observability.alert.triggered`
- `observability.alert.resolved`
- `observability.health.degraded`
- `observability.health.recovered`
- `observability.backup.completed`
- `observability.backup.failed`
- `observability.maintenance.started`
- `observability.maintenance.ended`

**Subscribed:**

- Operationally relevant events from across the platform — e.g.
  `integration.webhook.delivery_failed`, `licensing.usage.exhausted`,
  `billing.payment.failed`, and any module's error/health signals — consumed from
  the Event Bus to drive monitors and alert rules.

Workspace-attributed signals carry `workspace_id` so workspace-scoped views stay
isolated; platform-level signals are scoped to the Platform Context.

## 8. Data Owned (conceptual entities only — defer to DATABASE_ARCHITECTURE.md)

- **Metric Series** — named time-series of counters/gauges/timings (platform or
  workspace-attributed).
- **Health Snapshot** — point-in-time aggregated health of the platform and each
  module.
- **Log Record (index)** — structured, searchable index over emitted log output
  (the Logger remains the source).
- **Error Group** — grouped, fingerprinted exception with occurrence trend.
- **Alert Rule** — condition/threshold + target channel(s) + severity.
- **Alert Incident** — a fired alert and its lifecycle (triggered → resolved).
- **Backup Record** — backup run, status, location reference, verification result.
- **Maintenance Window** — scheduled/active maintenance period and scope.

Workspace-attributed telemetry carries `workspace_id`; identifiers are ULIDs.
Observability stores **telemetry**, not copies of business records.

## 9. Acceptance Criteria (testable checklist)

- [ ] Monitoring exists only in this module; no business module embeds monitoring
  logic (verified by review) — modules emit via shared services and expose probes.
- [ ] The Health Center aggregates per-module health via the Core Kernel's
  pluggable Health Checker probes.
- [ ] The Log Explorer reads from the shared PSR-3 Logger output; no module writes
  its own log files.
- [ ] Error Tracking groups exceptions surfaced by the global Error Handler and
  correlates them via `request_id`.
- [ ] Performance, queue, AI, and database monitors each present their domain
  telemetry sourced from emitted signals (not by reading other modules' tables).
- [ ] The Security Center surfaces auth-failure, lockout, permission-denied, and
  cross-tenant-attempt signals.
- [ ] The Backup Manager shows schedule, status, and verified-restore state; the
  Maintenance Center manages maintenance windows/mode.
- [ ] The Alert Engine fires on rule thresholds and delivers **only** through the
  Integration Platform (email/messaging/webhook); no direct external calls.
- [ ] The Operations Dashboard consolidates the above for System Owners and is
  reachable only with `system.diagnostics.run` in the Platform Context.
- [ ] Workspace-scoped usage views are filtered by `workspace_id` and gated by
  `usage.view`; a workspace can never see another tenant's telemetry.
- [ ] Operational actions (trigger backup, enter maintenance) require
  `system.diagnostics.manage` and are audited.
- [ ] Telemetry is retained as time-series/aggregates; Observability stores no
  copies of business records.

### Related Documents

`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `MODULES.md` ·
`SECURITY_GUIDE.md` · `PERMISSION_MODEL.md` · `WORKSPACE_MODEL.md` ·
`Integration_Platform.md` · `Licensing.md` · `AI_ENGINE.md` · `OBSERVABILITY.md` ·
`DATABASE_ARCHITECTURE.md`
