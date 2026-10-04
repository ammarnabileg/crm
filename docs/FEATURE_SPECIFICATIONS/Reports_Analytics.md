# FEATURE SPEC — Reports / Analytics

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Reports / Analytics · **Layer:** Intelligence · **Implemented in:** Phase 10–15
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The **Reports / Analytics** module provides **cross-module metrics, dashboards,
and saved views** for a workspace (`MODULES.md`). It turns the activity of other
modules — primarily **Recruitment** — into **funnel and hiring analytics**,
operational dashboards, and exportable reports, all **workspace-scoped**.

It is a **read-oriented** module: it **consumes** data from other modules through
**published events and read contracts**, and **MUST NOT** read another module's
tables directly (`PROJECT_CONSTITUTION.md` §4; `ARCHITECTURE.md` §4). It owns only
its own derived/aggregated read models and saved-view definitions.

## 2. Scope

### In scope

- **Cross-module metrics** aggregated from domain events and read contracts
  (hiring, AI usage/cost, activity volumes).
- **Hiring funnel analytics:** applications per stage, conversion between stages,
  time-in-stage, time-to-hire, source effectiveness, drop-off/rejection reasons.
- **Dashboards:** composable, workspace-scoped dashboards and widgets.
- **Saved views:** named, reusable filter/column/grouping definitions for reports
  and dashboards (coordinating with Recruitment's in-context saved views).
- **Exports:** export of report/dashboard data (e.g. CSV) for permitted users,
  PII-gated and audited.
- **Read models / projections** built from subscribed events for fast,
  paginated reads.

### Out of scope

- **Authoring of business data** (jobs, applications, interviews, offers) — owned
  by **Recruitment**. Reports never mutates source data.
- **AI cost/usage source of truth** — owned by the **AI Engine** (Reports
  aggregates and displays it).
- **Direct table access to other modules** — forbidden; consumption is via events
  and read contracts only.
- **Automation/scheduled report delivery actions** — orchestrated by the
  **Workflow Engine** (which MAY trigger an export/notification); Reports exposes
  the read/export capability it invokes.
- **Platform-wide (cross-tenant) analytics** — a System Owner concern in the
  Platform Context (`system.*`), out of scope for this workspace-scoped module.

## 3. Inputs

- **Subscribed domain events** from other modules (§7) — the primary feed for
  building read models/projections.
- **Read contracts** of source modules (e.g. Recruitment) for on-demand
  aggregate queries where event-derived projections are insufficient.
- **Saved-view definitions:** filters, columns, grouping, date ranges,
  comparison periods.
- **Dashboard definitions:** widget layout and configuration.
- **Export requests:** report identifier, filters, and format.
- **Shared-service inputs:** authenticated `User` + active `Workspace`; permission
  decisions; AI Engine usage/cost data (via events/contract).

## 4. Outputs

- **Rendered dashboards and reports** (server-rendered view-models), all
  **paginated** and within the performance budget.
- **Funnel/hiring analytics** datasets (per-stage counts, conversion, time
  metrics, source breakdowns).
- **Exported files** (e.g. CSV) for permitted, audited downloads.
- **Saved views and dashboard definitions** persisted per workspace.
- **Analytics events** (§7) for downstream consumers (e.g. Workflow, Audit).
- **Read models** kept current from subscribed events.

## 5. Dependencies (modules + contracts consumed; shared services used)

| Dependency | Type | Why |
|---|---|---|
| **Core Kernel** | Foundation | Container, events, config, logging. |
| **Database** | Foundation | Persistence for read models and saved-view definitions. |
| **Permissions** | Contract | Authorizes `report.view`, `report.export`. |
| **Workspaces** | Contract | Tenant scope for every read model, dashboard, and saved view. |
| **Recruitment** | Events + read contract | Primary source of hiring data (funnel, time-in-stage, sources). |
| **AI Engine** | Events + read contract | AI usage/cost aggregation. |
| **Audit** | Shared service / events | Activity-volume metrics; export auditing. |
| **Files** | Contract (optional) | Storage of generated export artifacts where retained. |
| **Workflow Engine** | Event (subscriber) | MAY trigger scheduled exports; Reports does **not** depend on it. |

Reports/Analytics **consumes** lower/contract surfaces and **subscribes** to
events; it depends on no reactive module, keeping the graph acyclic
(`MODULES.md` §5).

## 6. Permissions (keys this module declares)

Grammar `resource.action` (lowercase, dot-separated; `PERMISSION_MODEL.md` §2).
Workspace permissions, gated by subscription + enabled modules; deny-by-default.

- `report.view` — view dashboards, reports, and saved views.
- `report.export` — export report/dashboard data (PII-gated and audited).

Saved-view management is governed by `report.view` (create/update own views)
unless a workspace elects finer control; cross-module recruitment saved views on
the Kanban board remain governed by Recruitment's `pipeline.view`/`pipeline.manage`.

## 7. Events (Published / Subscribed)

Grammar `<module>.<entity>.<event>`, past tense (`PROJECT_CONSTITUTION.md` §7).

### Published

- `reports.report.generated` — a report/dashboard dataset was produced.
- `reports.export.completed` — an export artifact was produced and is ready.
- `reports.saved_view.created` — a saved view was created.
- `reports.saved_view.updated` — a saved view was changed.

### Subscribed

Reports/Analytics is primarily a **subscriber**, building read models from:

- `jobs.job.published` / `jobs.job.closed` (Recruitment) — job-volume metrics.
- `applications.application.submitted` (Recruitment) — funnel intake.
- `applications.application.stage_changed` (Recruitment) — conversion,
  time-in-stage, drop-off.
- `applications.application.rejected` / `applications.application.withdrawn`
  (Recruitment) — loss analysis.
- `interviews.interview.completed` / `interviews.interview.evaluated`
  (Recruitment) — interview throughput.
- `offers.offer.accepted` / `offers.offer.declined` (Recruitment) — offer
  acceptance and time-to-hire.
- `ai.usage.recorded` / `ai.cost_limit.reached` (AI Engine) — AI usage and cost
  analytics.

All subscriptions are **workspace-scoped**; projections carry `workspace_id`.

## 8. Data Owned (conceptual entities only — defer to `DATABASE_ARCHITECTURE.md`)

All entities are **workspace-scoped** (carry `workspace_id`) with a ULID `id`
(`CHAR(26)`), isolated per tenant. Reports owns only **derived** data.

- **Report Definition** — a named, reusable report configuration (metrics,
  filters, grouping, comparison period).
- **Dashboard** — a composable layout of widgets, workspace-scoped.
- **Dashboard Widget** — a single chart/metric tile bound to a report/metric.
- **Saved View** — a stored filter/column/grouping definition (`DOMAIN_MODEL.md`
  §4.4).
- **Metric Projection / Read Model** — pre-aggregated data built from subscribed
  events for fast, paginated reads (no copies of source-of-truth business
  records; derived only).
- **Export Job / Artifact** — a record of an export request and its produced file
  reference (stored via **Files** where retained).

Reports/Analytics MUST NOT duplicate source-of-truth business data; its
projections are **derived** and rebuildable from events (`DATABASE_ARCHITECTURE.md`
no-derived-duplication principle).

## 9. Acceptance Criteria (testable checklist)

- [ ] All analytics data is sourced via **events and read contracts**; the module
      **never reads another module's tables directly** (verified by dependency
      check).
- [ ] Hiring **funnel analytics** are derived from the documented Recruitment
      events (stage counts, conversion, time-in-stage, time-to-hire, source).
- [ ] **AI usage/cost** analytics are derived from AI Engine events/contract, not
      recomputed independently.
- [ ] Every dashboard, report, saved view, and projection is **workspace-scoped**
      and isolated; no cross-workspace data appears (tenant-isolation test).
- [ ] `report.view` is required to view any dashboard/report; `report.export` is
      required to export; both are **deny-by-default** and enforced server-side.
- [ ] Exports are **PII-gated and audited** (who exported what, when, in which
      workspace).
- [ ] Every list/report endpoint is **paginated**; no unbounded queries; reads
      stay within the performance budget (`PROJECT_CONSTITUTION.md` §11).
- [ ] Read-model **projections are rebuildable** from subscribed events and store
      no duplicated source-of-truth records.
- [ ] Saved views and dashboards persist per workspace with a ULID `id` and
      `workspace_id`.
- [ ] The module degrades gracefully when a source module (e.g. AI Engine) is
      disabled — affected widgets show no data rather than erroring.
- [ ] The module exposes behavior **only** through its `Contracts` surface and
      published events.

### Related Documents

`MODULES.md` · `ARCHITECTURE.md` · `DOMAIN_MODEL.md` · `PERMISSION_MODEL.md` ·
`WORKSPACE_MODEL.md` · `DATABASE_ARCHITECTURE.md` ·
`FEATURE_SPECIFICATIONS/Recruitment.md` · `FEATURE_SPECIFICATIONS/AI_Engine.md` ·
`FEATURE_SPECIFICATIONS/Workflow_Engine.md`
