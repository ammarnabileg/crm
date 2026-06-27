# SCREEN CATALOG — HaHireAI

> **Status:** Adopted (Phase 5) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `NAVIGATION_MAP.md`, `SECURITY_MATRIX.md`, `UI_GUIDELINES.md`.

---

## 0. How to Read This Catalog

This is the **catalog of every screen** in HaHireAI — one entry per screen, in
both operating contexts. It is a documentation artifact only: **no UI, no code.**
Visual layout, the sidebar engine, and detailed flows are described in companion
Phase 5 docs (`NAVIGATION_ARCHITECTURE.md`, `SIDEBAR_MODEL.md`,
`SCREEN_RELATIONSHIPS.md`).

**Authority order.** Where this catalog and a referenced doc differ, the
referenced doc governs: screen names and the tree come from `NAVIGATION_MAP.md`;
permission keys come from `PERMISSION_CATALOG.md` (reconciled through
`SECURITY_MATRIX.md` §1.4); gate semantics come from `SECURITY_MATRIX.md`;
mandatory states come from `UI_GUIDELINES.md` §8; entity states come from
`STATE_DIAGRAMS.md`; module ownership comes from `MODULES.md`.

**Per-screen sub-structure.** Every screen below documents, in order:

- **Purpose** — what the screen is for.
- **Entry Points** — how the user arrives (`NAVIGATION_MAP.md` §7).
- **Permissions** — exact `PERMISSION_CATALOG.md` keys: the `*.view`/`*.use` read
  gate that controls visibility, plus action keys used inside the screen.
- **Primary Actions** — the main use cases, each citing its action key.
- **Key Widgets/Sections** — the salient regions of the screen.
- **Dependencies** — the owning + consumed modules (`MODULES.md`).
- **Related Screens** — lateral/drill links (`NAVIGATION_MAP.md` §5–§6).
- **States** — the **six mandatory states** every screen MUST handle
  (`UI_GUIDELINES.md` §8): First-Use/Empty, Loading, No-Permission, Error,
  Success, Offline.
- **Acceptance Criteria** — a short conformance checklist.

**Two laws applied to every screen (do not restate per entry):**

1. **Visibility ≠ authorization** (`SECURITY_MATRIX.md` §1.1). A screen renders
   only when *permission* AND *subscription/entitlement* AND *enabled module* all
   pass; the server still re-checks the permission on every read and write. Hiding
   is a courtesy, never a gate.
2. **Tenant isolation is always on** (`SECURITY_MATRIX.md` §1.3). Every
   Workspace-Context screen is scoped to the active `workspace_id`; Platform
   screens act on global data; any cross-tenant reach by a System Owner is an
   explicit, audited bypass.

**Cross-cutting controls** (`SECURITY_MATRIX.md` §4) also apply everywhere: CSRF
on all writes, rate limits + audit on sensitive actions, PII gating on candidate
read/export, and secrets shown once. The per-screen **Offline** state always
includes double-submit protection for writes.

Two states recur verbatim and are abbreviated in entries as **[NP]** and
**[OFF]**:

- **[NP] No-Permission:** the read gate is absent (or subscription/module
  disabled) → a clear, non-leaking message; never the data, never a broken page;
  server denial stands regardless of UI (`UI_GUIDELINES.md` §8).
- **[OFF] Offline/degraded:** connectivity/backend loss is detected and surfaced;
  no silent failure; in-flight writes are guarded against double-submission.

---

# PART A — PLATFORM CONTEXT

Scope: the SaaS platform itself (`NAVIGATION_MAP.md` §3). Every screen requires a
`system.*` key, is invisible in any Workspace Context, and operates on **global**
data with no `workspace_id`. Entry to the whole console requires
`system.dashboard.view`; absent it, the Platform Context is never offered
(`SECURITY_MATRIX.md` §2). Every `system.*` action is audited to
`system_audit_logs` (`AUDIT_POLICY.md` §2.2).

## A1. Overview

- **Purpose:** Platform health & KPI landing for System Owners.
- **Entry Points:** Context switch → Platform (default platform landing).
- **Permissions:** `system.dashboard.view` (read-only aggregate).
- **Primary Actions:** Drill into Workspaces, Users, Subscriptions, System
  Analytics, Diagnostics from KPI cards (each target re-gated by its own key).
- **Key Widgets/Sections:** Platform KPIs (tenants, users, MRR, AI usage), health
  summary, recent system audit highlights, quick links.
- **Dependencies:** System Administration (owner); reads Observability,
  Subscriptions, Audit.
- **Related Screens:** Companies/Workspaces, System Analytics, Diagnostics, Audit
  Logs.
- **States:** *First-Use/Empty:* fresh platform → "no tenants yet," link to create
  the first workspace / invite owners. *Loading:* KPI skeletons; no layout jump.
  *[NP]*. *Error:* metric panels degrade individually with retry; page still
  renders. *Success:* n/a (read-only). *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Requires `system.dashboard.view`; hidden otherwise.
  - [ ] All six states handled; failed widgets isolate, never blank the page.
  - [ ] No tenant data shown without an explicit, audited drill-in.

## A2. Companies / Workspaces (list) + Workspace Detail

> One entry manages **Workspaces**; a *Company* is optional data inside a
> workspace's settings, not a separate entity (`WORKSPACE_MODEL.md` §1, §8).

**A2a — Companies / Workspaces (list)**

- **Purpose:** Cross-tenant directory of all workspaces (system view).
- **Entry Points:** Sidebar; Overview KPI.
- **Permissions:** `system.workspaces.manage` (read + manage under one key).
- **Primary Actions:** Search/filter tenants; open a Workspace Detail; (from
  detail) lifecycle actions below.
- **Key Widgets/Sections:** Tenant table (name, owner, plan, status, members,
  created), status/plan filters, bulk selection.
- **Dependencies:** System Administration; reads Workspaces, Subscriptions.
- **Related Screens:** Workspace Detail, Subscriptions, Users.
- **States:** *Empty:* "no workspaces yet." *Loading:* table skeleton.
  *[NP]*. *Error:* list-level retry. *Success:* n/a. *[OFF]*.

**A2b — Workspace Detail**

- **Purpose:** One tenant's members, subscription, status, and lifecycle.
- **Entry Points:** Companies/Workspaces row.
- **Permissions:** `system.workspaces.manage`; plan changes also need
  `system.subscriptions.manage`.
- **Primary Actions:** Suspend/resume workspace (`system.workspaces.manage`);
  assign/change license (`system.workspaces.manage` + `system.subscriptions.manage`);
  **impersonate / enter as tenant** (`system.workspaces.manage` — explicit
  bypass, always audited). Workspace state follows `STATE_DIAGRAMS.md` §10.
- **Key Widgets/Sections:** Identity & status header, subscription panel, member
  roster (→ User Detail), lifecycle action bar, audit excerpt.
- **Dependencies:** System Administration; Subscriptions, Memberships (read),
  Audit.
- **Related Screens:** User Detail, Subscription Detail, Audit Logs.
- **States:** *Empty:* new tenant with no members beyond owner. *Loading:* panel
  skeletons. *[NP]*. *Error:* action-level inline errors; state unchanged on
  failure. *Success:* toast + reflected new status; bypass actions confirm an
  audit entry was written. *[OFF]*.
- **Acceptance Criteria (A2):**
  - [ ] Both screens gated by `system.workspaces.manage`; license change also
        requires `system.subscriptions.manage`.
  - [ ] Suspend/resume/license/impersonate each produce a `system_audit_logs`
        entry (`AUDIT_POLICY.md` §2.2).
  - [ ] Impersonation is flagged as a tenant bypass; no silent cross-tenant reach.
  - [ ] Lifecycle transitions obey `STATE_DIAGRAMS.md` §10.

## A3. Users (list) + User Detail

**A3a — Users (global directory)**

- **Purpose:** The single global `User` identity directory (global, not tenant
  data).
- **Entry Points:** Sidebar.
- **Permissions:** `system.users.manage`.
- **Primary Actions:** Search users; open User Detail.
- **Key Widgets/Sections:** User table (name, email, status, system flags,
  workspaces count), filters.
- **Dependencies:** System Administration; Users, Memberships (read).
- **Related Screens:** User Detail, Workspace Detail.
- **States:** *Empty:* only the bootstrap System Owner exists. *Loading:*
  skeleton. *[NP]*. *Error:* retry. *Success:* n/a. *[OFF]*.

**A3b — User Detail**

- **Purpose:** One user's profile, memberships, and system flags.
- **Entry Points:** Users row; Workspace Detail (member link).
- **Permissions:** `system.users.manage`.
- **Primary Actions:** Suspend / reactivate / set system flag
  (`system.users.manage` — privilege change, audited).
- **Key Widgets/Sections:** Profile header, memberships list (→ Workspace
  Detail), system-flag panel, security/session summary, audit excerpt.
- **Dependencies:** System Administration; Users, Memberships, Audit.
- **Related Screens:** Workspace Detail, Audit Logs.
- **States:** *Empty:* user with no memberships. *Loading:* skeleton. *[NP]*.
  *Error:* inline; no privilege change on failure. *Success:* toast + new flag
  state; audited. *[OFF]*.
- **Acceptance Criteria (A3):**
  - [ ] Both gated by `system.users.manage`.
  - [ ] Privilege changes (suspend/flag) are audited.
  - [ ] `User` is the single identity; memberships shown read-only here.

## A4. Subscriptions (list) + Plan Detail + Subscription Detail

**A4a — Subscriptions (list)**

- **Purpose:** Per-workspace subscription state & revenue across all tenants.
- **Entry Points:** Sidebar; Workspace Detail.
- **Permissions:** `system.subscriptions.manage`.
- **Primary Actions:** Filter by plan/status; open Subscription Detail or Plan
  Detail; change a workspace's subscription (`system.subscriptions.manage`).
- **Key Widgets/Sections:** Subscriptions table (workspace, plan, status, MRR,
  renewal), revenue summary, plan catalog link.
- **Dependencies:** Subscriptions (owner); Billing, Licensing (read).
- **Related Screens:** Plan Detail, Subscription Detail, Workspace Detail.
- **States:** *Empty:* no paid subscriptions. *Loading:* skeleton. *[NP]*.
  *Error:* retry. *Success:* n/a. *[OFF]*.

**A4b — Plan Detail**

- **Purpose:** A single global Plan (features + limits) and its coupons.
- **Entry Points:** Subscriptions list.
- **Permissions:** `system.plans.manage` (note: plans use `*.plans.*`, distinct
  from `*.subscriptions.*`).
- **Primary Actions:** Create / edit plan or coupon (`system.plans.manage` —
  audited).
- **Key Widgets/Sections:** Feature & limit matrix, pricing, coupon list,
  affected-tenants count.
- **Dependencies:** Subscriptions, Licensing.
- **Related Screens:** Subscriptions, Subscription Detail.
- **States:** *Empty:* no plans defined → create first plan. *Loading:* skeleton.
  *[NP]* (needs `system.plans.manage`). *Error:* validation inline. *Success:*
  toast + saved plan; audited. *[OFF]*.

**A4c — Subscription Detail**

- **Purpose:** One workspace's subscription lifecycle (`STATE_DIAGRAMS.md` §9:
  Trialing→Active→PastDue→Suspended / Cancelled / Expired).
- **Entry Points:** Subscriptions list; Workspace Detail.
- **Permissions:** `system.subscriptions.manage`.
- **Primary Actions:** Change plan, adjust status, apply coupon
  (`system.subscriptions.manage` — tenant entitlement change, audited).
- **Key Widgets/Sections:** Status timeline, current plan/limits, invoices &
  payment history (read), entitlement panel.
- **Dependencies:** Subscriptions; Billing, Licensing, Audit.
- **Related Screens:** Workspace Detail, Plan Detail.
- **States:** *Empty:* trialing with no invoices. *Loading:* skeleton. *[NP]*.
  *Error:* inline; entitlement unchanged on failure. *Success:* toast + new
  status; audited. *[OFF]*.
- **Acceptance Criteria (A4):**
  - [ ] List & Subscription Detail gated by `system.subscriptions.manage`; Plan
        Detail by `system.plans.manage`.
  - [ ] Plan/coupon and subscription changes are audited.
  - [ ] Status transitions follow `STATE_DIAGRAMS.md` §9; expiry never hard-deletes
        data.

## A5. AI Providers (list) + Provider Detail

**A5a — AI Providers (global catalog)**

- **Purpose:** Global AI provider/model catalog and platform-level keys.
- **Entry Points:** Sidebar.
- **Permissions:** `system.ai.manage`.
- **Primary Actions:** Add provider; open Provider Detail; set default/fallback
  order (`system.ai.manage`).
- **Key Widgets/Sections:** Provider list (status, models, default flag), global
  fallback chain, usage-at-a-glance.
- **Dependencies:** AI Engine (owner); Integration (provider transport), Audit.
- **Related Screens:** Provider Detail, System Analytics.
- **States:** *Empty:* no providers → add first provider. *Loading:* skeleton.
  *[NP]*. *Error:* connectivity test errors shown per provider. *Success:* toast.
  *[OFF]*.

**A5b — Provider Detail**

- **Purpose:** One provider's models, credentials, and fallback config.
- **Entry Points:** AI Providers row.
- **Permissions:** `system.ai.manage`.
- **Primary Actions:** Add / rotate provider credential (`system.ai.manage` —
  **secret shown once**, encrypted at rest, audited — `SECURITY_MATRIX.md` §4.5);
  enable/disable models; set fallback.
- **Key Widgets/Sections:** Model list, masked credential panel ("reveal on
  create only"), fallback editor, health/test button.
- **Dependencies:** AI Engine; Integration, Audit.
- **Related Screens:** AI Providers, System Analytics.
- **States:** *Empty:* provider with no models configured. *Loading:* skeleton.
  *[NP]*. *Error:* credential test failure inline; no partial save. *Success:*
  one-time secret reveal + toast; subsequent reads masked; audited. *[OFF]*.
- **Acceptance Criteria (A5):**
  - [ ] Both gated by `system.ai.manage`.
  - [ ] New/rotated credentials display once, store encrypted, and are audited.
  - [ ] Reads never return raw secrets (masked reference only).

## A6. System Analytics

- **Purpose:** Cross-tenant platform metrics (read-only).
- **Entry Points:** Sidebar; Overview.
- **Permissions:** `system.observability.view` (reconciles draft
  `system.analytics.view` — `SECURITY_MATRIX.md` §1.4).
- **Primary Actions:** Filter time range / dimension; drill from a metric to the
  relevant Platform screen (re-gated by target key).
- **Key Widgets/Sections:** Growth, usage, AI-consumption, revenue, and
  performance charts; cohort/tenant breakdowns.
- **Dependencies:** Observability / Reports-Analytics; reads all platform modules
  via probes.
- **Related Screens:** Overview, Diagnostics, Subscriptions.
- **States:** *Empty:* insufficient history → "data will appear as the platform is
  used." *Loading:* chart skeletons. *[NP]* (needs `system.observability.view`).
  *Error:* per-chart retry. *Success:* n/a. *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Gated by `system.observability.view`; read-only.
  - [ ] Charts degrade independently; six states handled.

## A7. Audit Logs (platform)

- **Purpose:** System-level immutable activity trail (`system_audit_logs`).
- **Entry Points:** Sidebar; any platform record's audit excerpt.
- **Permissions:** `system.audit.view`.
- **Primary Actions:** Filter by actor/category/date; **export platform audit**
  (`system.audit.view` — inherits the same gate, MUST NOT widen scope;
  rate-limited + audited).
- **Key Widgets/Sections:** Immutable event stream (actor, action, target, before/
  after redacted of secrets/PII), filters, export.
- **Dependencies:** Audit (owner); System Administration.
- **Related Screens:** Workspace Detail, User Detail, Provider Detail (each links
  here).
- **States:** *Empty:* no events yet. *Loading:* stream skeleton. *[NP]*. *Error:*
  retry; export errors surfaced. *Success:* export confirmation (file ready);
  export itself audited. *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Gated by `system.audit.view`; append-only, never editable/deletable
        (`AUDIT_POLICY.md` §3).
  - [ ] Export inherits the read gate, is rate-limited and audited, and never
        widens scope.
  - [ ] `changes` payloads are redacted of secrets and full PII.

## A8. Platform Settings

- **Purpose:** Global defaults & system configuration.
- **Entry Points:** Sidebar.
- **Permissions:** `system.settings.manage`.
- **Primary Actions:** Change platform setting / feature flag
  (`system.settings.manage` — definition change, audited as System category).
- **Key Widgets/Sections:** Setting groups (defaults, localization, security
  policy, feature flags), per-flag toggles, change history.
- **Dependencies:** Settings (owner); System Administration, Audit.
- **Related Screens:** Diagnostics, Developer Tools (a feature flag here reveals
  it).
- **States:** *Empty:* defaults shown (settings registry always populated).
  *Loading:* skeleton. *[NP]*. *Error:* validation inline; setting unchanged on
  failure. *Success:* toast + applied value; audited. *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Gated by `system.settings.manage`.
  - [ ] Every setting/flag change is audited as a System-category event.
  - [ ] Feature-flag changes (e.g. enabling Developer Tools) take effect on
        next sidebar re-derivation.

## A9. Diagnostics

- **Purpose:** Health probes, maintenance, and runtime checks.
- **Entry Points:** Sidebar; Overview.
- **Permissions:** `system.diagnostics.run` (read/run probes). Distinct adjacent
  capabilities: maintenance mode/cache/cleanup → `system.maintenance.manage`;
  backups/restore → `system.backups.manage`.
- **Primary Actions:** Run health probe (`system.diagnostics.run`); toggle
  maintenance mode / clear cache / run cleanup (`system.maintenance.manage`);
  manage backups / restore (`system.backups.manage`). All audited.
- **Key Widgets/Sections:** Probe runner & results, subsystem health grid,
  maintenance controls, backup/restore panel.
- **Dependencies:** Observability (owner); System Administration, Audit.
- **Related Screens:** System Analytics, Platform Settings, Audit Logs.
- **States:** *Empty:* no probe run yet → "run a check." *Loading:* probe
  in-progress indicator. *[NP]* (e.g. holds `diagnostics.run` but not
  `maintenance.manage` → maintenance controls hidden/denied). *Error:* probe
  failure shown with remediation hints, no internals leaked. *Success:* result
  panel + toast; maintenance/backup actions audited. *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Read/run gated by `system.diagnostics.run`; maintenance and backup actions
        require their own distinct keys.
  - [ ] No stack traces/internals shown (`UI_GUIDELINES.md` §8).
  - [ ] Maintenance and backup actions are audited.

## A10. Developer Tools

- **Purpose:** Low-level developer/diagnostic utilities for the platform.
- **Entry Points:** Sidebar — **hidden unless the feature flag is enabled**
  (`NAVIGATION_MAP.md` §3).
- **Permissions:** `system.dashboard.view` **+ feature flag** (visibility);
  individual utilities re-check their own `system.*` key (e.g. integrations →
  `system.integrations.manage`).
- **Primary Actions:** Inspect runtime/registry, manage global connectors
  (`system.integrations.manage`), run developer utilities — each behind its own
  key.
- **Key Widgets/Sections:** Module registry inspector, connector manager, feature-
  flag-gated experimental tools.
- **Dependencies:** System Administration; Integration Platform, Observability.
- **Related Screens:** Platform Settings (the flag), Diagnostics, AI Providers.
- **States:** *First-Use:* flag off → entry absent entirely. *Loading:* skeleton.
  *[NP]*: flag on but utility's key absent → that tool denied. *Error:* inline.
  *Success:* toast; sensitive actions audited. *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Invisible unless feature flag enabled AND `system.dashboard.view` held.
  - [ ] Each utility enforces its own `system.*` key independently of visibility.
  - [ ] No utility bypasses audit on sensitive actions.

---

# PART B — WORKSPACE CONTEXT

Scope: exactly one active workspace; **all** screens are tenant-isolated
(`SECURITY_MATRIX.md` §1.3) and **additionally** gated by the workspace
**subscription** + **enabled module** for visibility. Recruitment is one bounded
context; its sub-areas appear only when the **Recruitment** module is enabled and
the matching `*.view` key is held (`NAVIGATION_MAP.md` §4).

## B1. Dashboard (workspace)

- **Purpose:** Workspace landing / activity summary on entering a workspace.
- **Entry Points:** Default landing; workspace switcher.
- **Permissions:** `workspace.view`.
- **Primary Actions:** Navigate to enabled areas (Recruitment, Members, Reports,
  Settings, Billing) — each link rendered only if its own gate passes.
- **Key Widgets/Sections:** Activity feed, at-a-glance counts (open jobs, active
  applications, upcoming interviews), quick actions, pending notifications.
- **Dependencies:** Workspaces (owner); reads Recruitment, Notifications, Reports
  (only those enabled + permitted).
- **Related Screens:** Recruitment ▸ Dashboard, Members, Reports, Settings,
  Billing.
- **States:** *First-Use/Empty:* new workspace → onboarding prompts (enable
  modules, invite members, create first job — each subject to permission).
  *Loading:* widget skeletons. *[NP]*: lacks `workspace.view` → not a member of
  this workspace. *Error:* per-widget retry; page renders. *Success:* n/a.
  *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Gated by `workspace.view`; default landing for the active workspace.
  - [ ] Only widgets/links the user is permitted + subscribed + module-enabled to
        see are rendered.
  - [ ] Switching workspace re-derives the whole page from the new context
        (`UI_GUIDELINES.md` §3).

## B2. Recruitment ▸ Dashboard

- **Purpose:** Hiring overview for this workspace (entry-level recruitment read).
- **Entry Points:** Sidebar; workspace Dashboard.
- **Permissions:** `job.view` (entry-level recruitment read; reconciles draft
  `recruitment.view` — `SECURITY_MATRIX.md` §1.4, §3.1).
- **Primary Actions:** Drill to Jobs, Pipeline, Interviews, Offers, Analytics
  (each target re-gated).
- **Key Widgets/Sections:** Hiring funnel summary, jobs-by-status, recent
  applications, interviews-this-week, offers-pending.
- **Dependencies:** Recruitment (owner); Reports/Analytics, AI Engine (insights,
  if entitled).
- **Related Screens:** Jobs, Pipeline, Interviews, Offers, Recruitment ▸
  Analytics.
- **States:** *Empty:* module enabled but no jobs yet → "create your first job"
  (needs `job.create`). *Loading:* skeletons. *[NP]*: lacks `job.view` or
  Recruitment not enabled/subscribed → area hidden. *Error:* per-widget retry.
  *Success:* n/a. *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Gated by `job.view` AND Recruitment enabled + subscribed.
  - [ ] Empty state's "create job" CTA appears only with `job.create`.
  - [ ] All six states handled.

## B3. Recruitment ▸ Jobs (list)

- **Purpose:** Requisitions list for the workspace.
- **Entry Points:** Sidebar; Recruitment Dashboard.
- **Permissions:** `job.view`. Actions: `job.create`, `job.clone`, plus
  per-row lifecycle (see Job Detail B4).
- **Primary Actions:** Create job (`job.create` — audited); clone job (`job.clone`
  → produces a Draft); open Job Detail; quick lifecycle from row menu (publish/
  pause/close/archive — each its own key).
- **Key Widgets/Sections:** Jobs table (title, status per `STATE_DIAGRAMS.md` §2,
  applicants, owner, updated), status/department filters, saved filters, bulk
  selection.
- **Dependencies:** Recruitment; Search, Files (templates), Audit.
- **Related Screens:** Job Detail, Pipeline, Search.
- **States:** *First-Use/Empty:* "no jobs yet" → create/clone (gated by
  `job.create`/`job.clone`). *Loading:* table skeleton. *[NP]*. *Error:* list
  retry. *Success:* created/cloned job toast → opens Draft. *[OFF]* (block double
  create-submit).
- **Acceptance Criteria:**
  - [ ] List gated by `job.view`; create/clone require their own keys.
  - [ ] Create and lifecycle actions are audited (Recruitment category).
  - [ ] Job statuses and row transitions match `STATE_DIAGRAMS.md` §2.

## B4. Job Detail (tabbed)

A single requisition rendered as tabs. **Overview** is the default tab; each tab
is independently permission-gated, so a user may see some tabs and not others.
Job state machine: `Draft → Published → Paused/Closed → Archived`
(`STATE_DIAGRAMS.md` §2). Tabs below combine the `NAVIGATION_MAP.md` §5 set
(Overview, Applications, Pipeline, Interviews, Offers, Hiring Team, Settings) with
the catalog-requested **AI Evaluation**, **Notes**, **History**, and **Preview**
surfaces.

- **Entry Points:** Jobs row; Search; Notifications.
- **Permissions (shell):** `job.view` to open the Job at all; each tab adds its
  own key (below).
- **Dependencies:** Recruitment; Applications, Pipeline, Interviews, Offers,
  Memberships, AI Engine, Files, Audit, Search.
- **Related Screens:** Jobs, Candidate Profile, Interview Detail, Offer Detail,
  Members.
- **States (shell):** *First-Use:* a Draft job with empty tabs → each tab shows
  its own empty state. *Loading:* tab content skeleton (shell stays). *[NP]*: a
  tab the user can't view is hidden; the Job shell still renders permitted tabs.
  *Error:* per-tab retry. *Success:* action toasts reflect new state on the
  relevant tab. *[OFF]*.

**B4.1 Overview** — *Purpose:* requisition summary + lifecycle state. *Permission:*
`job.view`. *Primary Actions:* edit (`job.update`, versioned to `job_versions`);
publish (`job.publish` — opens public posting), pause (`job.pause`), close
(`job.close`), archive (`job.archive`), delete soft (`job.delete`), clone
(`job.clone`), manage public link/share (`job.share`). *Widgets:* state badge +
transition controls, job summary, key metrics, public-link panel. *Empty:* draft
fields prompt completion.

**B4.2 Applications** — *Purpose:* candidacies bound to this Job. *Permission:*
`application.view`. *Primary Actions:* open Application/Candidate Profile; update
(`application.update`), reject (`application.reject`), record withdrawal
(`application.withdraw`). *Widgets:* applications table/cards by stage, filters,
bulk actions. *Empty:* "no applications yet" (public link shown if Published).
*Related:* Candidate Profile.

**B4.3 Pipeline** — *Purpose:* this Job's stages as a Kanban. *Permission:*
`pipeline.view`. *Primary Actions:* move stage / edit stages / bulk
(`pipeline.manage` — each move audited). *Widgets:* stage columns (default stages
from `STATE_DIAGRAMS.md` §3, workspace-customizable), drag handles, per-card
quick actions. *Empty:* stages defined, no candidates yet.

**B4.4 AI Evaluation** — *Purpose:* AI-assisted screening/match for this Job's
applications (advisory only — a human decides transitions, `APPLICATION_FLOW.md`
§6). *Permission:* `interview.view` to read AI evaluation output; running AI needs
`interview.ai.run` (+ `ai.run`) **and** AI Engine in plan + module enabled.
*Primary Actions:* run AI screening/interview (`interview.ai.run` — metered to
this workspace, rate-limited, audited); view AI scores/summaries. *Widgets:* AI
match scores, rationale summaries, "AI is advisory" notice, run/queue controls.
*Empty:* AI not yet run, or AI Engine not entitled → explain how to enable.

**B4.5 Interviews** — *Purpose:* interviews for this Job's applications.
*Permission:* `interview.view`. *Primary Actions:* schedule
(`interview.schedule`), run AI (`interview.ai.run`), submit evaluation
(`interview.evaluate`), cancel (`interview.cancel`). Interview states per
`STATE_DIAGRAMS.md` §5. *Widgets:* interview list by status, scheduler, scorecard
entry. *Empty:* "no interviews scheduled." *Related:* Interview Detail.

**B4.6 Offers** — *Purpose:* offers on this Job's applications. *Permission:*
`offer.view`. *Primary Actions:* create draft (`offer.create`), edit
(`offer.update`), send (`offer.send`), revoke (`offer.revoke`). Offer states per
`STATE_DIAGRAMS.md` §4. *Widgets:* offers list, approval/status badges. *Empty:*
"no offers." *Related:* Offer Detail.

**B4.7 Hiring Team** — *Purpose:* members assigned to this Job. *Permission:*
`member.view` + `job.view`. *Primary Actions:* assign / remove hiring team member
(`job.update` — edits the requisition's team). *Widgets:* assigned members,
add-member picker (workspace members only). *Empty:* "no team assigned." *Related:*
Members.

**B4.8 Notes** — *Purpose:* internal collaboration notes on the requisition.
*Permission:* `candidate.note` for candidate-scoped notes surfaced here; job-level
notes read with `job.view`. *Primary Actions:* add/view notes (`candidate.note`
where attached to a candidate). *Widgets:* threaded notes, mentions. *Empty:* "no
notes yet." (Notes are workspace-scoped, never cross-tenant.)

**B4.9 History** — *Purpose:* append-only change history of this Job (audit-
backed) and its version timeline (`job_versions`, immutable). *Permission:*
`job.view`; workspace audit detail requires `audit.view`. *Primary Actions:*
inspect versions/diffs; jump to related audit entries. *Widgets:* version
timeline, change diffs, actor/timestamp. *Empty:* only the create event present.

**B4.10 Preview** — *Purpose:* preview the public posting as a candidate sees it.
*Permission:* `job.view`; managing the live public link needs `job.share`; the
posting is public only while **Published** (`STATE_DIAGRAMS.md` §2). *Primary
Actions:* preview rendering; copy/manage public link (`job.share`). *Widgets:*
public-page preview, share/link controls, "visible only when Published" notice.
*Empty:* Draft → "publish to make this live."

**B4.11 Settings** — *Purpose:* stages, screening questions, required documents.
*Permission:* `job.update`; managing screening-question/required-doc templates
needs `template.manage` (template *view* `template.view`). *Primary Actions:* edit
stages; manage screening questions / templates (`template.manage`); set required
documents. *Widgets:* stage editor, question/template manager, required-docs
config. *Empty:* defaults shown; prompt to customize.

- **Acceptance Criteria (B4):**
  - [ ] Job shell requires `job.view`; each tab enforces its own key
        (`SECURITY_MATRIX.md` §3.2): Applications `application.view`, Pipeline
        `pipeline.view`, Interviews/AI Evaluation `interview.view`, Offers
        `offer.view`, Hiring Team `member.view`+`job.view`, Settings `job.update`.
  - [ ] Lifecycle verbs map 1:1 to `job.*` keys and obey `STATE_DIAGRAMS.md` §2;
        each is audited.
  - [ ] AI Evaluation/AI run also require AI Engine entitlement + module on, are
        metered + rate-limited + audited, and are labeled advisory.
  - [ ] Public Preview surfaces only while Published; share needs `job.share`.
  - [ ] Edits are versioned to immutable `job_versions`; History is append-only.
  - [ ] Tabs the user can't view are hidden but still denied server-side.

## B5. Recruitment ▸ Talent Pool

- **Purpose:** Saved / passive / past candidates and smart lists.
- **Entry Points:** Sidebar; Candidate Profile (save action).
- **Permissions:** `talent.view` (the Talent Pool **screen** uses `talent.view`,
  NOT `candidate.view` — `SECURITY_MATRIX.md` §1.4, §3.7).
- **Primary Actions:** Manage talent pool / smart lists (`talent.manage`); save a
  candidate to the pool (`talent.manage` + `candidate.view`); open a Candidate
  Profile (`candidate.view`).
- **Key Widgets/Sections:** Saved lists / smart lists, candidate cards, tag &
  source filters, bulk add/remove.
- **Dependencies:** Recruitment; Search, Files.
- **Related Screens:** Candidate Profile, Search, Pipeline.
- **States:** *First-Use/Empty:* "no saved candidates" → save from a profile/
  search (gated by `talent.manage`). *Loading:* card skeletons. *[NP]*: lacks
  `talent.view`. *Error:* list retry. *Success:* add/remove toast. *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Screen gated by `talent.view` (not `candidate.view`).
  - [ ] Saving/managing requires `talent.manage`; opening a profile re-gates on
        `candidate.view` (PII).
  - [ ] Tenant-scoped; no candidate appears across workspaces.

## B6. Recruitment ▸ Pipeline (cross-job)

- **Purpose:** Cross-job Kanban of stages for the whole workspace.
- **Entry Points:** Sidebar; Job Detail (Pipeline tab).
- **Permissions:** `pipeline.view`. Action: `pipeline.manage`.
- **Primary Actions:** Move stage / edit stages / bulk action (`pipeline.manage` —
  each move audited).
- **Key Widgets/Sections:** Stage columns across jobs, job/stage filters,
  application cards (→ Candidate Profile), bulk move.
- **Dependencies:** Recruitment; Applications, Search, Audit.
- **Related Screens:** Job Detail, Candidate Profile, Applications.
- **States:** *Empty:* no applications in any stage. *Loading:* column skeletons.
  *[NP]*: lacks `pipeline.view`. *Error:* a failed move rolls back the card with
  an inline error (state preserved). *Success:* card moves + toast; audited.
  *[OFF]* (queue/guard moves; no lost drags).
- **Acceptance Criteria:**
  - [ ] Read gated by `pipeline.view`; moves/bulk require `pipeline.manage`.
  - [ ] Every stage move is audited; failed moves revert without state loss.
  - [ ] Stages follow `STATE_DIAGRAMS.md` §3 defaults (workspace-customizable).

## B7. Recruitment ▸ Interviews + Interview Detail

**B7a — Interviews (list)**

- **Purpose:** Scheduled AI + human evaluations across the workspace.
- **Entry Points:** Sidebar; Application; Notifications.
- **Permissions:** `interview.view`.
- **Primary Actions:** Schedule (`interview.schedule`); run AI
  (`interview.ai.run` + `ai.run`, AI Engine entitled); cancel
  (`interview.cancel`).
- **Key Widgets/Sections:** Calendar/list by status (`STATE_DIAGRAMS.md` §5),
  filters, scheduler.
- **Dependencies:** Recruitment; AI Engine, Notifications, Files.
- **Related Screens:** Interview Detail, Application, Candidate Profile.
- **States:** *Empty:* "no interviews scheduled." *Loading:* skeleton. *[NP]*.
  *Error:* scheduling conflict inline. *Success:* scheduled toast + notification.
  *[OFF]*.

**B7b — Interview Detail**

- **Purpose:** A single interview's setup, session, and evaluation.
- **Entry Points:** Interviews list; Application.
- **Permissions:** `interview.view`. Actions: `interview.schedule`,
  `interview.ai.run` (+ `ai.run`), `interview.evaluate`, `interview.cancel`.
- **Primary Actions:** Run AI interview (`interview.ai.run` — metered to
  workspace, rate-limited, audited; supports session resume while InProgress);
  submit evaluation/scorecard (`interview.evaluate`); cancel
  (`interview.cancel`).
- **Key Widgets/Sections:** Interview header + state, AI session panel (if AI),
  scorecard form, transcript/recording references (Files), participants.
- **Dependencies:** Recruitment; AI Engine, Files, Notifications, Audit.
- **Related Screens:** Application, Candidate Profile, Offer Detail.
- **States:** *First-Use:* Scheduled, not started. *Loading:* skeleton; AI session
  shows progress. *[NP]*: holds `interview.view` but not `interview.ai.run` → run
  control hidden/denied. *Error:* AI failure surfaces a retry without losing
  prior session; evaluation save errors inline. *Success:* state advances
  (InProgress→Completed→Evaluated) + toast; AI usage + evaluation audited.
  *[OFF]*.
- **Acceptance Criteria (B7):**
  - [ ] Read gated by `interview.view`; each action keyed per
        `SECURITY_MATRIX.md` §3.5.
  - [ ] AI run requires `interview.ai.run` + `ai.run` + AI entitlement; metered,
        rate-limited, audited; advisory only.
  - [ ] Interview states follow `STATE_DIAGRAMS.md` §5.

## B8. Recruitment ▸ Offers + Offer Detail

**B8a — Offers (list)**

- **Purpose:** Extended offers & approvals across the workspace.
- **Entry Points:** Sidebar; Application.
- **Permissions:** `offer.view`.
- **Primary Actions:** Create draft (`offer.create`); open Offer Detail.
- **Key Widgets/Sections:** Offers table by status (`STATE_DIAGRAMS.md` §4),
  filters, approval queue.
- **Dependencies:** Recruitment; Notifications, Files, Audit.
- **Related Screens:** Offer Detail, Application, Candidate Profile.
- **States:** *Empty:* "no offers." *Loading:* skeleton. *[NP]*. *Error:* retry.
  *Success:* draft-created toast. *[OFF]*.

**B8b — Offer Detail**

- **Purpose:** One offer's lifecycle (Draft→Approved→Sent→Accepted/Declined/
  Expired/Revoked) and the hire handoff.
- **Entry Points:** Offers list; Application.
- **Permissions:** `offer.view`. Actions: `offer.create`, `offer.update`,
  `offer.send`, `offer.revoke`; converting a hire → `employee.create`; employee
  read/update → `employee.view` / `employee.update`.
- **Primary Actions:** Edit draft (`offer.update` — draft-only); send/extend
  (`offer.send` — audited); revoke (`offer.revoke`); on acceptance, convert hire
  to Employee (`employee.create` — creates post-hire context, audited).
- **Key Widgets/Sections:** Offer terms, approval & state controls, send/track
  panel, hire→Employee conversion (on `Accepted`/`Hired`).
- **Dependencies:** Recruitment; Notifications, Files, Audit.
- **Related Screens:** Application, Candidate Profile, (post-hire) Employee
  context.
- **States:** *First-Use:* Draft, unsent. *Loading:* skeleton. *[NP]*: e.g. holds
  `offer.view` but not `offer.send`. *Error:* send/approval errors inline; state
  unchanged on failure. *Success:* state transition toast; send/revoke/hire
  audited. *[OFF]* (guard duplicate send).
- **Acceptance Criteria (B8):**
  - [ ] Read gated by `offer.view`; actions keyed per `SECURITY_MATRIX.md` §3.6.
  - [ ] Offer states follow `STATE_DIAGRAMS.md` §4; edits allowed only in Draft.
  - [ ] Hire conversion requires `employee.create`; Employee state per
        `STATE_DIAGRAMS.md` §6; send/revoke/hire audited.

## B9. Candidate Profile (tabbed, PII)

- **Purpose:** Workspace-scoped projection of an applying `User` — a per-`(User,
  Workspace)` view, **not** a global account (`NAVIGATION_MAP.md` §4).
- **Entry Points:** Application; Talent Pool; Search.
- **Permissions:** `candidate.view` — **PII gate** (`SECURITY_MATRIX.md` §4.4);
  there is no broad read. Tabs/actions add: `candidate.note`, `candidate.tag`,
  `candidate.tag.manage`, `candidate.rate`, `candidate.export`; Documents also
  need `files.view`/`files.download`; Applications context `application.view`.
- **Primary Actions:** Add/view notes (`candidate.note`); apply tag
  (`candidate.tag`); manage tag definitions (`candidate.tag.manage`); add rating/
  scorecard (`candidate.rate`); **export candidate data** (`candidate.export` —
  high-risk PII, rate-limited + audited, tenant-scoped).
- **Key Widgets/Sections (tabs):**
  - *Overview* (`candidate.view`): contact/profile summary (PII), source, status.
  - *Applications* (`application.view`): this candidate's applications in the
    workspace (→ Application/Job).
  - *Documents* (`files.view` + `files.download`): referenced Files — CV owned by
    the `User`, not copied; gated by Files tenant + visibility checks.
  - *Notes* (`candidate.note`): workspace-scoped notes.
  - *Tags* (`candidate.tag` / `candidate.tag.manage`): applied tags + definitions.
  - *Ratings/Scorecards* (`candidate.rate`): evaluations.
  - *Activity/Timeline* (`candidate.view`): audit-backed history.
- **Dependencies:** Recruitment; Files, Search, Audit.
- **Related Screens:** Application, Job Detail (Applications), Talent Pool,
  Interview Detail, Offer Detail.
- **States:** *First-Use/Empty:* sparse profile (e.g. no notes/tags/ratings) →
  prompts gated by the matching key. *Loading:* tab skeletons. *[NP]*: lacks
  `candidate.view` → PII never rendered, non-leaking message. *Error:* per-tab
  inline; export errors surfaced. *Success:* note/tag/rating toasts; export
  confirmation (rate-limited, audited). *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Read strictly gated by `candidate.view` (PII gate); no broad read path.
  - [ ] Export requires `candidate.export`, is rate-limited + audited +
        tenant-scoped; sensitive PII encrypted at rest.
  - [ ] Documents enforce `files.view`/`files.download` + Files visibility; CVs
        referenced, not copied.
  - [ ] Each tab/action enforces its own key per `SECURITY_MATRIX.md` §3.4.

## B10. Members (list) + Member Detail + Roles

**B10a — Members (list)**

- **Purpose:** Memberships, invitations, and their status for the workspace.
- **Entry Points:** Sidebar; Settings.
- **Permissions:** `member.view`. Actions: `member.invite`,
  `member.invite.resend`, `member.invite.cancel`.
- **Primary Actions:** Invite member (`member.invite` — audited); resend
  (`member.invite.resend`); cancel invite (`member.invite.cancel`); open Member
  Detail.
- **Key Widgets/Sections:** Members table (user, status per `STATE_DIAGRAMS.md`
  §7, roles, last activity), pending invitations (`STATE_DIAGRAMS.md` §8), invite
  panel.
- **Dependencies:** Memberships (owner); Permissions, Notifications, Audit.
- **Related Screens:** Member Detail, Roles, Settings, Audit log.
- **States:** *First-Use/Empty:* only the owner → "invite your team" (needs
  `member.invite`). *Loading:* skeleton. *[NP]*. *Error:* invite validation
  inline. *Success:* invite-sent toast + notification. *[OFF]* (guard duplicate
  invite).

**B10b — Member Detail**

- **Purpose:** A single membership: status, roles, and direct grants.
- **Entry Points:** Members row; Audit entry.
- **Permissions:** `member.view`. Actions: `member.update` (+ `permission.assign`
  when changing grants), `member.suspend`, `member.reactivate`, `member.remove`.
- **Primary Actions:** Update member roles/status (`member.update`; grant changes
  also need `permission.assign` — privilege change, always audited); suspend
  (`member.suspend`); reactivate (`member.reactivate`); remove (`member.remove`).
- **Key Widgets/Sections:** Member header + status, assigned roles, direct-grant
  editor, activity/audit excerpt.
- **Dependencies:** Memberships; Permissions, Audit.
- **Related Screens:** Roles, User Detail (platform), Members.
- **States:** *First-Use:* member with default access. *Loading:* skeleton.
  *[NP]*: e.g. `member.view` without `member.update` → controls hidden/denied.
  *Error:* inline; no role/status change on failure. *Success:* toast + new
  state; role/grant changes audited (`PERMISSION_MODEL.md` §7). *[OFF]*.

**B10c — Members ▸ Roles (Role Builder)**

- **Purpose:** Workspace role builder — **roles are data**; the system ships **no
  reserved roles** (`ROLE_BUILDER.md`).
- **Entry Points:** Members; Settings.
- **Permissions:** `role.view`. Actions: `role.create`, `role.update`,
  `role.clone`, `role.delete`, `permission.assign`; ownership →
  `workspace.transfer`.
- **Primary Actions:** Create (`role.create`), rename/edit (`role.update`), clone
  (`role.clone`), delete (`role.delete`); assign/revoke permissions on a role
  (`permission.assign` — privilege change, always audited); transfer workspace
  ownership (`workspace.transfer` — session re-auth + audit).
- **Key Widgets/Sections:** Role list, permission-key picker grouped by catalog
  category (display-only categories), members-per-role count, ownership-transfer
  panel.
- **Dependencies:** Permissions (owner); Memberships, Workspaces, Audit.
- **Related Screens:** Members, Member Detail, Settings.
- **States:** *First-Use/Empty:* no roles yet (owner has direct grants) → "create
  your first role." *Loading:* skeleton. *[NP]*: `role.view` without
  `permission.assign` → assignment disabled. *Error:* inline; no change on
  failure. *Success:* toast; create/edit/clone/delete and every assignment
  audited. *[OFF]*.
- **Acceptance Criteria (B10):**
  - [ ] Members/Member Detail gated by `member.view`; Roles by `role.view`.
  - [ ] No screen branches on a role name; gates are permission keys only
        (`SECURITY_MATRIX.md` §5).
  - [ ] Grant/role changes and ownership transfer are always audited; transfer
        re-authenticates.
  - [ ] Membership/Invitation states follow `STATE_DIAGRAMS.md` §7–§8.

## B11. Reports (and Recruitment ▸ Analytics)

- **Purpose:** Saved views & workspace reporting; Recruitment ▸ Analytics is the
  hiring-scoped view of the same capability.
- **Entry Points:** Sidebar; Recruitment Dashboard (Analytics); a metric drills to
  underlying Jobs/Applications.
- **Permissions:** `report.view`. Action: `report.export`.
- **Primary Actions:** Build/save a view; export report (`report.export` —
  inherits scope, MUST NOT widen visibility; rate-limited + audited).
- **Key Widgets/Sections:** Report builder, saved views, charts/tables, export
  controls; drill-through to source records.
- **Dependencies:** Reports / Analytics (owner); Recruitment, AI Engine
  (insights), Audit.
- **Related Screens:** Recruitment ▸ Dashboard, Jobs, Applications.
- **States:** *First-Use/Empty:* no saved views → "create a report." *Loading:*
  chart skeletons. *[NP]*: lacks `report.view`. *Error:* per-widget retry; export
  errors inline. *Success:* saved/exported toast; export audited. *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Gated by `report.view`; export requires `report.export`.
  - [ ] Exports inherit (never widen) the viewer's scope; rate-limited + audited.
  - [ ] Drill-through honors the target entity's own gate and tenant isolation.

## B12. Files

- **Purpose:** Workspace-scoped file storage and browsing.
- **Entry Points:** Sidebar; Application (Documents); Candidate Profile
  (Documents).
- **Permissions:** `files.view` (reconciles draft `file.view` —
  `SECURITY_MATRIX.md` §1.4). Actions: `files.upload`, `files.download`,
  `files.delete`, `files.manage`.
- **Primary Actions:** Upload (`files.upload` — type/content sniffing, size limit,
  AV-scan hook); download (`files.download` — via tenant-guarded, permission-
  checked controller only); delete soft (`files.delete` — per retention, audited);
  manage folders/visibility (`files.manage` — audited).
- **Key Widgets/Sections:** Folder tree, file table (name, type, owner, size,
  visibility), upload dropzone, visibility controls.
- **Dependencies:** Files (owner); Audit; consumed by Recruitment (Documents).
- **Related Screens:** Application (Documents), Candidate Profile (Documents).
- **States:** *First-Use/Empty:* "no files yet" → upload (needs `files.upload`).
  *Loading:* table skeleton; upload progress. *[NP]*. *Error:* rejected upload
  (type/size/scan) explained without internals. *Success:* upload/delete toast.
  *[OFF]* (resumable/guarded upload; no double-delete).
- **Acceptance Criteria:**
  - [ ] Browse gated by `files.view`; each action keyed per `SECURITY_MATRIX.md`
        §3.10.
  - [ ] Downloads only via the tenant-guarded, permission-checked controller.
  - [ ] Uploads enforce type/content/size + AV hook; deletes are soft + audited;
        all tenant-scoped.

## B13. Search

- **Purpose:** Unified workspace-scoped search across entities → any permitted
  entity.
- **Entry Points:** Global top-bar (always present in Workspace Context).
- **Permissions:** `search.use` (reconciles draft `workspace.view` for the
  feature — `PERMISSION_CATALOG.md` §Search; `SECURITY_MATRIX.md` §3.11). Opening a
  result requires the **target entity's** own permission.
- **Primary Actions:** Query the index; open a result (re-gated by the target's
  key, e.g. `job.view`, `application.view`, `candidate.view`, `interview.view`,
  `offer.view`, `files.view`, `member.view`).
- **Key Widgets/Sections:** Search box, scoped result groups (Job · Application ·
  Candidate Profile · Interview · Offer · File · Member), filters.
- **Dependencies:** Search (owner); indexes Recruitment, Files, Memberships.
- **Related Screens:** Any workspace-scoped entity (`NAVIGATION_MAP.md` §6).
- **States:** *First-Use/Empty:* no query → suggestions/recent; empty result set →
  "no matches." *Loading:* result skeletons. *[NP]*: lacks `search.use` → search
  unavailable; individually, results the user can't open are not linked. *Error:*
  retry. *Success:* n/a (navigation). *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Feature gated by `search.use`; index is workspace-scoped — results never
        cross tenants (`SECURITY_MATRIX.md` §3.11).
  - [ ] Each result link honors the target entity's own permission gate.
  - [ ] No result leaks data the user cannot open.

## B14. Notifications

- **Purpose:** Per-user, workspace-contextual notifications.
- **Entry Points:** Global top-bar (always present in Workspace Context).
- **Permissions:** `notifications.view`. Action: `notifications.manage`.
- **Primary Actions:** Open notification → its source entity; manage preferences /
  mark read / clear (`notifications.manage`).
- **Key Widgets/Sections:** Notification list (event, entity, time), unread badge,
  preferences panel, mark/clear controls.
- **Dependencies:** Notifications (owner); subscribes to events across modules.
- **Related Screens:** The entity that raised each event (e.g. Application,
  Interview, Offer) — `NAVIGATION_MAP.md` §6.
- **States:** *First-Use/Empty:* "you're all caught up." *Loading:* skeleton.
  *[NP]*: lacks `notifications.view`. *Error:* retry; opening a deleted/no-access
  target shows a graceful message. *Success:* preference/mark/clear toast.
  *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Read gated by `notifications.view`; preferences/mark/clear require
        `notifications.manage`.
  - [ ] Each notification links only to a target the user may open (re-gated).
  - [ ] Per-user and workspace-contextual; tenant-scoped.

## B15. Settings

> The Settings screen is the workspace-config landing; its sub-areas each enforce
> their **own** key (`SECURITY_MATRIX.md` §3.11). Several sub-areas are gated by
> non-`settings.*` keys (Workspaces, AI Engine, Integration, Audit) — visibility
> still also requires the relevant module + subscription.

- **Purpose:** Workspace configuration: core fields, branding, AI, security,
  integrations, audit, lifecycle.
- **Entry Points:** Sidebar.
- **Permissions:** `settings.view` (landing). Sub-area action keys below.
- **Primary Actions / Sub-areas:**
  - *General settings* — update workspace settings (`settings.update`); edit core
    fields name/slug/profile (`workspace.update`).
  - *Branding* — manage logo/colors/favicon/email branding (`workspace.branding`).
  - *Lifecycle* — archive / restore / delete workspace (`workspace.archive` /
    `workspace.restore` / `workspace.delete`); state per `STATE_DIAGRAMS.md` §10.
  - *AI* — view/configure AI (`ai.view` / `ai.configure`, needs AI in plan +
    module on); manage workspace AI keys (`ai.keys.manage` — secret shown once,
    encrypted, audited); manage prompts (`ai.prompts.manage`).
  - *Integrations* — view/manage integrations (`integration.view` /
    `integration.manage`); manage API keys (`apikey.manage` — scoped, revocable,
    hashed); manage webhooks (`webhook.manage` — per-endpoint HMAC secret shown
    once).
  - *Audit (workspace)* — view (`audit.view`) / export (`audit.export`) the
    tenant-scoped, immutable `audit_logs` (`AUDIT_POLICY.md` §2.1).
  - *Roles* — link into the Role Builder (`role.view`; see B10c).
- **Key Widgets/Sections:** Settings nav (sub-areas above), per-area forms,
  secret panels (one-time reveal), audit viewer, danger zone (lifecycle).
- **Dependencies:** Settings (owner); Workspaces, AI Engine, Integration Platform,
  Audit, Permissions.
- **Related Screens:** Members ▸ Roles, Billing, Audit log, Workspace Dashboard.
- **States:** *First-Use:* defaults populated (settings registry always present) →
  prompts to brand/configure. *Loading:* per-area skeleton. *[NP]*: a sub-area the
  user can't access is hidden/denied (e.g. `settings.view` without `ai.configure`
  → AI config read-only or hidden). *Error:* validation inline; setting/secret
  unchanged on failure. *Success:* toast + applied value; secrets reveal once;
  workspace/AI/integration/audit changes audited. *[OFF]*.
- **Acceptance Criteria:**
  - [ ] Landing gated by `settings.view`; each sub-area enforces its own key per
        `SECURITY_MATRIX.md` §3.11.
  - [ ] Secrets (AI keys, API keys, webhook secrets) shown once, encrypted/hashed
        at rest, masked thereafter (`SECURITY_MATRIX.md` §4.5).
  - [ ] Workspace lifecycle obeys `STATE_DIAGRAMS.md` §10; never hard-deletes
        business data; each action audited.
  - [ ] AI/integration sub-areas also require their module + subscription.

## B16. Billing

- **Purpose:** This workspace's subscription, plan, invoices, and payments.
- **Entry Points:** Sidebar; Settings.
- **Permissions:** `billing.view` (view invoices/usage/summary). Action:
  `billing.manage`.
- **Primary Actions:** Manage plan / payment methods (`billing.manage` — financial
  action, audited, never crosses tenants).
- **Key Widgets/Sections:** Current plan & status (`STATE_DIAGRAMS.md` §9), usage
  vs limits, invoices, payment methods, plan-change panel.
- **Dependencies:** Billing / Subscriptions (owner); Licensing, Audit.
- **Related Screens:** Settings; (platform-side) Subscription Detail.
- **States:** *First-Use/Empty:* trialing with no invoices → "no invoices yet."
  *Loading:* skeleton. *[NP]*: `billing.view` without `billing.manage` → read-only;
  no `billing.view` → hidden. *Error:* payment errors shown without exposing
  provider internals. *Success:* plan/payment change toast; audited. *[OFF]*
  (guard duplicate payment submit).
- **Acceptance Criteria:**
  - [ ] View gated by `billing.view`; plan/payment changes require
        `billing.manage`.
  - [ ] Billing data and actions are tenant-scoped; never cross workspaces.
  - [ ] Financial actions are audited; subscription status reflects
        `STATE_DIAGRAMS.md` §9.

---

## Appendix — Baseline Capabilities (no workspace permission)

These are available to **any authenticated `User`** and are therefore **not**
gated by a workspace permission (`PERMISSION_CATALOG.md` §1, `SECURITY_MATRIX.md`
§3.12), but remain subject to CSRF, rate limits, and tenant stamping. They are
not workspace screens in the sidebar but appear as flows around the catalog:

| Flow | Gate | Notes |
|---|---|---|
| Create a workspace | authenticated `User` | Creator becomes owner via **direct grant**, not a reserved role (`WORKSPACE_MODEL.md` §6). |
| Accept an invitation (join) | authenticated `User` + valid invite token | Single-use, expiring token (`STATE_DIAGRAMS.md` §8). |
| Apply to a **public** job | authenticated `User` | Creates an `Application` (`Applied`); the public job posting is the **only** un-authenticated surface, reachable only while the Job is **Published** (`job.share`/`job.publish`). |
| Manage own profile / sessions | authenticated `User` (self) | A user manages their own identity. |

> **Employee** is a post-hire **context** of a `User`, reached from a `Hired`
> Application (`NAVIGATION_MAP.md` §4–§5), not a top-level screen. Its read/write
> use `employee.view`/`employee.update` and its state machine is
> `STATE_DIAGRAMS.md` §6.

---

### Related Documents

`NAVIGATION_MAP.md` · `SECURITY_MATRIX.md` · `PERMISSION_CATALOG.md` ·
`UI_GUIDELINES.md` · `STATE_DIAGRAMS.md` · `MODULES.md` · `WORKSPACE_MODEL.md` ·
`ACCESS_POLICIES.md` · `AUDIT_POLICY.md` · `ROLE_BUILDER.md` ·
`APPLICATION_FLOW.md` · `NAVIGATION_ARCHITECTURE.md` · `SIDEBAR_MODEL.md` ·
`SCREEN_RELATIONSHIPS.md`
