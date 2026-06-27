# DASHBOARD GUIDE — HaHireAI

> **Status:** Adopted (Phase 5) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `NAVIGATION_MAP.md`, `UI_GUIDELINES.md`, `SCREEN_CATALOG.md`.

---

## 1. Principle — A Dashboard Is a Command Center, Not a Welcome Page

A dashboard in HaHireAI is the **operational command center** of its context — the
place from which a user *sees the state of the work and acts on it*. It is **not**
a welcome screen, a marketing splash, or a static "you are logged in" page.

Binding rules:

1. **Decisions, not greetings.** Every dashboard MUST surface the metrics, queues,
   and actions that drive the next decision in that context. A dashboard that only
   says "Welcome back" is a defect.
2. **A projection of the system.** Like every screen, a dashboard is a *projection
   of the domain*, never its definition (`UI_GUIDELINES.md` §1). Its widgets render
   real, permission-scoped, tenant-isolated state — never decoration.
3. **Gated by the same three inputs as navigation.** Dashboard content is composed
   from **permission keys AND subscription/entitlement AND enabled modules**,
   exactly as the sidebar is (`NAVIGATION_MAP.md` §1, `UI_GUIDELINES.md` §2). A
   widget appears only when all three allow it.
4. **Visibility is never authorization.** Showing a widget is a UX convenience;
   the server still authorizes every read and write the widget performs
   (`SECURITY_MATRIX.md` §1.1, `UI_GUIDELINES.md` §1). A hidden-but-reachable
   action MUST still be denied server-side.
5. **One product, one grammar.** Widgets, tiles, empty states, and quick actions
   MUST reuse the shared component set (`UI_GUIDELINES.md` §7). A user MUST NOT be
   able to tell where one module's dashboard contribution ends and another's
   begins by a change in visual language.
6. **Additive growth.** Modules contribute dashboard widgets through their
   manifest, exactly as they contribute navigation (`NAVIGATION_MAP.md` §8,
   `MODULES.md` §6). Adding a module adds widgets; it never requires redesigning
   the dashboard framework.

---

## 2. The Dashboard / Widget Framework

A dashboard is a **composable layout of widgets**. The framework — not any single
module — owns layout, gating, refresh, and the mandatory per-widget states.

### 2.1 Widget contract

Every widget MUST declare, via its module manifest (`MODULES.md` §6): the exact
**permission key(s)** that gate its visibility *and* that the server re-checks on
every read; its **owning module**; any **subscription/entitlement** it depends on
(e.g. AI Engine for AI widgets, `WORKSPACE_MODEL.md` §8.4); and a **read source**
(a read-model/view-model or read contract). Widgets MUST NOT run business logic or
branch on role names in templates (`UI_GUIDELINES.md` §7).

### 2.2 Composition (gating)

```
Widget renders  IFF  permission key held
                 AND  owning module enabled for this workspace
                 AND  subscription entitles the capability
                 AND  active context matches (Platform vs Workspace)
```

A widget that fails **any** clause MUST NOT render and MUST NOT leave a broken
placeholder. The dashboard simply composes the widgets that pass — the same
deny-by-default rule the sidebar obeys (`SECURITY_MATRIX.md` §1.2).

### 2.3 Layout

- Dashboards use a **responsive grid** built on the design tokens
  (`UI_GUIDELINES.md` §5). Widgets MUST consume tokens, never hard-coded values.
- Layout MUST be **direction-aware** (logical start/end) so it renders correctly
  in both AR/RTL and EN/LTR (`UI_GUIDELINES.md` §6).
- The grid MUST reflow gracefully as widgets are added or hidden by gating; the
  layout MUST NOT assume a fixed widget set.

### 2.4 Refresh

- Widgets SHOULD refresh **asynchronously** without blocking first paint; heavy or
  AI-backed aggregation runs on the server (`UI_GUIDELINES.md` §10,
  `Reports_Analytics.md` §4).
- Each widget SHOULD expose a **last-updated** indication and MAY offer manual
  refresh. Auto-refresh intervals SHOULD be modest and MUST NOT undermine the
  p95 < 300 ms first-render budget (`UI_GUIDELINES.md` §10).
- Refreshing a widget MUST re-run the server-side permission and tenant checks; a
  stale grant MUST NOT leak data on refresh.

### 2.5 Per-widget states (mandatory)

Every widget is a data-bearing component and MUST handle **all** applicable
mandatory screen states (`UI_GUIDELINES.md` §8) — independently of its neighbors:

| State | Per-widget requirement |
|---|---|
| **Empty / first-use** | A meaningful message: what the widget is and the next permitted action — never a blank tile. |
| **Loading** | A skeleton/placeholder that reserves space; the grid MUST NOT jump when data arrives (`UI_GUIDELINES.md` §10). |
| **No-permission** | If reached without the gate, render nothing or a non-leaking notice — never the data, never a broken tile. |
| **Error** | A human-readable, actionable message scoped to that tile; no stack traces. One failing widget MUST NOT break the dashboard. |
| **Success** | State-changing quick actions confirm explicitly (toast/inline) and reflect the new state. |
| **Offline / degraded** | If a source module (e.g. AI Engine) is disabled or unreachable, the widget shows "no data," not an error (`Reports_Analytics.md` §9). |

### 2.6 Customization

- A user MAY reorder, show, or hide widgets **within the set they are entitled to
  see**. Customization MUST NOT reveal a widget the gating denies.
- Layout preferences are **per user, per context** (and per workspace for the
  Workspace Dashboard); one workspace's layout MUST NOT leak into another
  (`WORKSPACE_MODEL.md` §3).
- A workspace MAY define a **default dashboard layout** for new members; defaults
  are still subject to per-member gating at render.
- Composable dashboard/widget definitions are owned by **Reports / Analytics** for
  cross-module tiles (`Reports_Analytics.md` §8); module-native widgets are
  contributed by their owning module.

---

## 3. Workspace Dashboard (Platform Baseline, Phase 9)

The **Workspace Dashboard** is the default landing of the Workspace Context
(`NAVIGATION_MAP.md` §4, §7.2) and is gated by **`workspace.view`**. It exists as
part of the **platform baseline delivered in Phase 9** — *before* any business
domain. Its widgets describe the **workspace as a tenant**: people, storage,
activity, invitations, and health.

> **Phase boundary (binding):** The widgets in this section ship with the Phase 9
> platform (Workspaces, Memberships, Files, Notifications, Search, Audit, Settings
> — `MODULES.md` §2). **Recruitment widgets do NOT exist here.** They are added to
> the Recruitment Dashboard in **Phase 10** (§4), not before. A Phase 9 workspace
> with Recruitment disabled MUST show a complete, useful Workspace Dashboard with
> **zero** recruitment tiles.

### 3.1 Baseline widgets

| Widget | What it shows |
|---|---|
| **Members Count** | Active members of this workspace, with pending/suspended breakdown; links to Members. |
| **Storage Usage** | Workspace file storage consumed against the plan limit; links to Files / Billing. |
| **Recent Activity** | A workspace-scoped activity stream from the immutable audit trail; links to each source record. |
| **Pending Invitations** | Outstanding member invitations (sent, awaiting acceptance/expiry); links to Members. |
| **Workspace Health** | A composite status tile: subscription state, storage headroom, configuration/setup completeness. |
| **Recent Files** | The most recently uploaded/changed files in this workspace; links to Files. |
| **Notifications** | A compact view of the user's unread, workspace-contextual notifications; links to the notification center. |

Each tile is **tenant-isolated** to the active `workspace_id` (`SECURITY_MATRIX.md`
§1.3) and gated by its widget permission (see §6). The Workspace Dashboard MUST
remain coherent and non-empty for a brand-new workspace: first-use states explain
each area and offer the next permitted step (`UI_GUIDELINES.md` §8).

---

## 4. Recruitment Dashboard (Phase 10)

The **Recruitment Dashboard** is the hiring command center within the Recruitment
bounded context (`NAVIGATION_MAP.md` §4; `Recruitment.md` §1). It appears **only**
when the **Recruitment module is enabled** for the workspace **and** the
subscription entitles it, and its entry-level read gate is **`job.view`**
(`SECURITY_MATRIX.md` §3.1). It is delivered in **Phase 10** (`MODULES.md` §2) —
these widgets do not exist in the Phase 9 baseline (§3).

Each widget below is **individually permission-gated** with the exact catalog key;
a member sees only the tiles whose key they hold, whose module is enabled, and
whose capability the plan includes.

| Widget | What it shows | Gating key |
|---|---|---|
| **Open Jobs** | Count and shortlist of `Published`/active requisitions; links to Jobs. | `job.view` |
| **Active Applications** | Candidacies in non-terminal stages across the workspace; links to Applications. | `application.view` |
| **AI Interviews Waiting** | AI interviews awaiting run/review; advisory only — humans decide (`Recruitment.md` §1). | `interview.view` (+ `interview.ai.run`, `ai.run` to launch) |
| **Today's Interviews** | Interviews scheduled for today (AI + human); links to each interview. | `interview.view` |
| **Pending Offers** | Offers in `Draft`/`Approved`/`Sent` awaiting action; links to Offers. | `offer.view` |
| **Pipeline Overview** | Stage-by-stage counts across jobs (mini-funnel); links to the Pipeline. | `pipeline.view` |
| **Hiring Velocity** | Time-to-hire / time-in-stage / throughput trend (in-context hiring analytics). | `report.view` |
| **Recent Activities** | Recent recruitment events on this workspace's jobs/applications; links to each record. | `application.view` |
| **Quick Actions** | Context launchers (e.g. create job, schedule interview); each action gated by its own verb key. | per-action (e.g. `job.create`, `interview.schedule`) |

Notes:

- **AI is advisory.** AI-backed tiles (AI Interviews Waiting, AI-derived insights
  in Hiring Velocity) present recommendations; a permitted human always overrides
  (`Recruitment.md` §1, §8). They also require the **AI Engine** to be enabled and
  in-plan, beyond the recruitment key (`SECURITY_MATRIX.md` §3.5).
- **Hiring Velocity** renders metrics authored in Recruitment and aggregated by
  **Reports / Analytics**; its gate is **`report.view`** (`SECURITY_MATRIX.md`
  §3.8, `Reports_Analytics.md` §6).
- **Quick Actions** never bypass authorization: each launcher is shown only if the
  user holds the action's verb key, and the server re-checks it
  (`SECURITY_MATRIX.md` §1.1).
- Every tile is **tenant-isolated**; no cross-workspace hiring data is ever shown
  (`WORKSPACE_MODEL.md` §3, `Recruitment.md` §8).

---

## 5. Platform Dashboard (System Owners)

The **Platform Dashboard** is the Overview landing of the **Platform Context**
(`NAVIGATION_MAP.md` §3) — the command center for operating the SaaS itself. It is
gated by **`system.dashboard.view`**, which is also the gate to *entering* the
Platform Context at all (`SECURITY_MATRIX.md` §2). It is visible **only** to
**System Owners** and never appears in any Workspace Context.

It surfaces **platform health and KPIs** over **global** data (no `workspace_id`):

- **Platform health / status** — service and runtime health at a glance
  (drill to Diagnostics).
- **Tenant KPIs** — workspace counts and lifecycle states (active / archived /
  suspended), drill to Workspaces.
- **Subscription & revenue summary** — plan distribution and subscription state
  (drill to Subscriptions).
- **Cross-tenant platform metrics** — usage/volume aggregates surfaced from
  `system.observability.view` sources.
- **Recent system audit** — high-signal entries from the immutable platform trail
  (drill to Audit Logs, gated by `system.audit.view`).

Binding rules for the Platform Dashboard:

- Deeper KPI tiles MUST honor their **own** `system.*` gate — e.g. cross-tenant
  metrics require **`system.observability.view`**, audit tiles require
  **`system.audit.view`** (`SECURITY_MATRIX.md` §2). Holding `system.dashboard.view`
  opens the console but does not grant every tile.
- Any tile that reaches into a single tenant's data is an **explicit, audited
  bypass** (`SECURITY_MATRIX.md` §1.3) — never an implicit cross-tenant read.
- No tile branches on a role name; each is gated purely by its `system.*` key
  (`PERMISSION_CATALOG.md` §4).

---

## 6. Widget Permission-Gating Table

Every widget below cites the **exact** `PERMISSION_CATALOG.md` key, its owning
module (`MODULES.md`), and its empty state. Visibility additionally requires the
owning module enabled **and** the subscription entitlement (`SECURITY_MATRIX.md`
§1.1); the server re-checks the key on every read (deny-by-default,
`SECURITY_MATRIX.md` §1.2). Where the catalog and the Phase 2 draft diverged, the
catalog governs (`SECURITY_MATRIX.md` §1.4).

### 6.1 Workspace Dashboard (Phase 9 baseline)

| Widget | Required permission | Module | Empty state |
|---|---|---|---|
| Members Count | `member.view` | Memberships | "No members yet — invite your team." (action gated by `member.invite`) |
| Storage Usage | `files.view` | Files | "No files stored yet." |
| Recent Activity | `audit.view` | Audit | "No recent activity." |
| Pending Invitations | `member.view` | Memberships | "No pending invitations." (send gated by `member.invite`) |
| Workspace Health | `workspace.view` | Workspaces | "Finish setting up your workspace." |
| Recent Files | `files.view` | Files | "No recent files." |
| Notifications | `notifications.view` | Notifications | "You're all caught up." |

### 6.2 Recruitment Dashboard (Phase 10)

| Widget | Required permission | Module | Empty state |
|---|---|---|---|
| Open Jobs | `job.view` | Recruitment | "No open jobs — create your first requisition." (gated by `job.create`) |
| Active Applications | `application.view` | Recruitment | "No active applications yet." |
| AI Interviews Waiting | `interview.view` | Recruitment (+ AI Engine) | "No AI interviews waiting." |
| Today's Interviews | `interview.view` | Recruitment | "No interviews scheduled today." |
| Pending Offers | `offer.view` | Recruitment | "No pending offers." |
| Pipeline Overview | `pipeline.view` | Recruitment | "Your pipeline is empty." |
| Hiring Velocity | `report.view` | Reports / Analytics | "Not enough data yet to chart velocity." |
| Recent Activities | `application.view` | Recruitment | "No recent hiring activity." |
| Quick Actions | per-action verb key (e.g. `job.create`, `interview.schedule`) | Recruitment | (renders only the actions the user may perform) |

### 6.3 Platform Dashboard (System Owners)

| Widget | Required permission | Module | Empty state |
|---|---|---|---|
| Platform Health / Status | `system.dashboard.view` | System Administration | "All systems nominal." |
| Tenant KPIs | `system.dashboard.view` | System Administration | "No workspaces yet." |
| Subscription & Revenue | `system.subscriptions.manage` | Subscriptions | "No active subscriptions." |
| Cross-Tenant Metrics | `system.observability.view` | Observability | "No metrics available." |
| Recent System Audit | `system.audit.view` | Audit | "No recent system events." |

> A widget whose key is **not** held is omitted entirely (not greyed out); the
> "empty state" column applies only when the user **is** permitted but there is no
> data to show (`UI_GUIDELINES.md` §8).

---

## 7. Accessibility & Responsive Behavior

Dashboards inherit the **Phase-1 accessibility baseline** (`UI_GUIDELINES.md` §9)
and the responsive/i18n rules (`UI_GUIDELINES.md` §6, §10). These are binding, not
aspirational.

### 7.1 Accessibility

- **Keyboard.** Every widget, quick action, refresh control, and drill-down link
  MUST be reachable and operable by keyboard alone, in a logical reading order,
  with no keyboard traps.
- **Focus.** Focus MUST be visible and managed deliberately — moved into any
  widget drawer/modal and restored on close.
- **Semantics & ARIA.** Use semantic HTML first; widgets SHOULD expose an
  accessible name/region label, and icon-only controls MUST have localized
  accessible names. Numeric tiles MUST convey meaning in text, not by color alone
  (WCAG 2.1 AA, `UI_GUIDELINES.md` §9).
- **Announcements.** Async widget updates and quick-action results MUST be
  announced to assistive technology (e.g. polite live regions), not silently
  swapped.
- **Contrast.** Charts, status colors, and KPI deltas MUST meet AA contrast and
  MUST pair color with text/icon so meaning never depends on color alone.

### 7.2 Responsive behavior

- The widget grid MUST reflow across breakpoints — multi-column on wide viewports,
  single-column stacking on narrow/mobile — without horizontal scroll or layout
  shift (`UI_GUIDELINES.md` §10).
- Tiles MUST reserve space for async content (skeletons) so the grid does not jump
  as widgets resolve (ties to the Loading state, §2.5).
- The full grammar MUST mirror correctly under **RTL**: tile order, chart axes,
  trend arrows, and chevrons follow logical start/end (`UI_GUIDELINES.md` §6).
- Dates, numbers, and currency in every tile MUST format per the active locale and
  **workspace settings** (timezone, language, currency, date format —
  `WORKSPACE_MODEL.md` §4); the dashboard MUST NOT assume a single locale.
- Non-critical and below-the-fold widgets SHOULD lazy-load so first render stays
  within the performance budget (`UI_GUIDELINES.md` §10).

---

### Related Documents

`NAVIGATION_MAP.md` · `UI_GUIDELINES.md` · `SECURITY_MATRIX.md` ·
`PERMISSION_CATALOG.md` · `MODULES.md` · `WORKSPACE_MODEL.md` ·
`FEATURE_SPECIFICATIONS/Recruitment.md` · `FEATURE_SPECIFICATIONS/Reports_Analytics.md` ·
`SCREEN_CATALOG.md` · `NAVIGATION_ARCHITECTURE.md` · `SIDEBAR_MODEL.md`
