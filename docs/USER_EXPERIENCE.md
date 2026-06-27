# USER EXPERIENCE — HaHireAI

> **Status:** Adopted (Phase 5) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `UI_GUIDELINES.md`, `NAVIGATION_MAP.md`, `USER_JOURNEYS.md`.

---

## 0. About This Document

This document states the **user-experience philosophy** of HaHireAI: the
cross-cutting principles that make every screen feel like one product rather than
a stack of forms. It is binding at the level of *experience*, not pixels —
component specifications, layout grids, and per-screen detail are owned by
`SCREEN_CATALOG.md`, `LAYOUT_SYSTEM.md`, `PAGE_STANDARDS.md`, and `SIDEBAR_MODEL.md`.

It **defers** to `UI_GUIDELINES.md` (the Phase-1 UI law), `NAVIGATION_MAP.md`
(which screens exist and how they nest), and `USER_JOURNEYS.md` (the end-to-end
flows). Where this document summarizes a rule those files own, **they govern** and
this file is corrected. Screen, context, and module names are used **exactly** as
defined in `NAVIGATION_MAP.md` and `MODULES.md`. Interpretation keywords (**MUST**,
**MUST NOT**, **SHOULD**, **SHOULD NOT**, **MAY**) follow RFC 2119.

---

## 1. UX Philosophy — One Coherent Product

HaHireAI is engineered to the standard of **Slack, Notion, Linear, GitHub, the
Stripe Dashboard, and ClickUp**: a single, coherent product, **system over
screens** (`UI_GUIDELINES.md` §1, `PROJECT_CONSTITUTION.md` §3.1). The
experience — not the route, the module, or the database table — is the unit we
design.

Binding principles:

- **The user sees an experience, not routes or modules.** A person operating
  HaHireAI MUST NOT be able to tell where one module ends and another begins.
  Recruitment, Members, Reports, Files, Search, Notifications, and Settings share
  one shell, one interaction grammar, and one visual language. The word "module"
  is an engineering term; it MUST NOT leak into the product surface.
- **The UI is a projection of the system.** Every screen is a *view onto* the
  domain, never the place the domain is invented (`UI_GUIDELINES.md` §1,
  `APPLICATION_FLOW.md` §2). Users navigate **relationships** between entities
  (a Job to its Applications to a Candidate Profile), not a sitemap.
- **Consistency is a requirement, not a preference.** The same action — create,
  edit, archive, filter, paginate, confirm, export — MUST look and behave the
  same on every surface. A divergent one-off pattern is a defect
  (`UI_GUIDELINES.md` §1).
- **One product, one sidebar.** There is exactly **one** dynamically generated
  sidebar, composed from the active context, the user's permissions, the
  workspace subscription, and the enabled modules — never one sidebar per role
  (`UI_GUIDELINES.md` §2, `NAVIGATION_MAP.md` §1). The UX consequence: the
  navigation a user sees *is* the set of things they can do, and nothing more.
- **The active context is unmistakable.** A user always knows whether they are in
  the **Platform Context** or a specific **Workspace Context**, and which
  workspace is active (`UI_GUIDELINES.md` §3, `NAVIGATION_MAP.md` §2).
- **Hiding is courtesy, never security.** Hiding a control improves the
  experience; the server still enforces every rule. Visibility and authorization
  are independent layers (`SECURITY_MATRIX.md` §1.1, `PERMISSION_MODEL.md` §5).

---

## 2. Unified Global Search

Search is a **single, global capability**, reached from the top bar on every
screen — the `Search` surface of `NAVIGATION_MAP.md` §4, gated by `search.use`
(`SECURITY_MATRIX.md` §3.11). It is not a per-list filter and not a separate
section a user must navigate to.

- **One entry point.** A persistent search affordance MUST be present in the
  global top bar in both contexts and MUST be reachable by keyboard (§5). It is
  always rendered for an authenticated user; results are gated, not the box.
- **Searches across entity types, results grouped.** In the **Workspace Context**,
  unified search spans **Jobs, Candidate Profiles, Applications, Members, Files,
  Reports, and Interviews**; results MUST be **grouped by entity type** so the
  user scans one coherent panel rather than seven scattered lists. (Offers and
  Talent Pool entries are reachable through their parent Application / Candidate
  Profile per §3 of `SCREEN_RELATIONSHIPS.md`.)
- **System Owners also search Companies / Workspaces.** In the **Platform
  Context**, the same search surface additionally exposes **Companies /
  Workspaces** and the global **Users** directory — never tenant business data,
  which stays inside its workspace (`SECURITY_MATRIX.md` §1.3).
- **Tenant-isolated by construction.** Every Workspace-Context result is scoped to
  the active `workspace_id`; the index never returns another tenant's records,
  nor the *existence* of a candidacy elsewhere (`SECURITY_MATRIX.md` §3.11,
  `APPLICATION_FLOW.md` §6). Switching workspaces re-scopes search entirely.
- **Results obey the target's gate.** A result row renders only if the user may
  view that entity, and opening it routes through the target entity's own
  permission gate (`NAVIGATION_MAP.md` §6, `SECURITY_MATRIX.md` §3.11). Search
  MUST NOT become a side-channel around `*.view`.
- **Search is a way *to* an entity, not a place.** Selecting a result navigates
  directly to that entity's canonical screen (e.g. a result under *Applications*
  opens that Application; under *Interviews*, the Interview Detail). Search never
  dead-ends in a results-only view the user must back out of manually.

---

## 3. Unified Notification Center

Notifications are a **single center**, reached from the top bar on every screen —
the `Notifications` surface of `NAVIGATION_MAP.md` §4, gated by
`notifications.view` (`SECURITY_MATRIX.md` §3.11). There are **NO separate
notification pages** scattered per module.

- **One center, workspace-contextual.** All notifications for the active context
  surface in one place; they are per-user and scoped to the active workspace
  (`NAVIGATION_MAP.md` §4). Switching workspaces re-scopes the center.
- **Every notification links to its origin.** A notification MUST deep-link to the
  exact entity that raised the event — e.g. "application submitted" opens *that*
  Application; "interview scheduled" opens *that* Interview Detail
  (`NAVIGATION_MAP.md` §6). A notification that cannot be acted on is noise.
- **Preferences live with the center.** Marking read/unread, clearing, and
  managing preferences are part of the one center (`notifications.manage`,
  `SECURITY_MATRIX.md` §3.11); they are not a separate Settings sub-screen the
  user must hunt for.
- **Consistent, non-intrusive.** Transient confirmations (toasts) for the user's
  own state-changing actions are part of the Success state (§7, `UI_GUIDELINES.md`
  §8); the notification center is the durable record. The two MUST NOT be
  conflated — a toast is feedback, a notification is an event.

---

## 4. Command Palette & Quick Actions

To deliver **minimal-step access to any feature** (the Linear/Notion/GitHub
standard), HaHireAI SHOULD provide a **command palette** — a single keyboard-first
launcher that unifies navigation and action.

- **Open from anywhere.** A documented shortcut (§5) MUST open the palette from
  any screen in either context, without losing the user's place.
- **Navigate, search, and act from one surface.** The palette SHOULD let a user
  jump to any permitted screen (e.g. *Jobs*, *Pipeline*, *Members ▸ Roles*),
  run quick actions (e.g. *Create job*, *Invite member*), and pivot into unified
  search (§2) — collapsing multi-click journeys into one.
- **Permission-, subscription-, and module-aware.** The palette MUST offer only
  what the user could otherwise reach: every command is gated by the **same**
  permission key, subscription entitlement, and enabled module that govern the
  sidebar entry or the server action (`UI_GUIDELINES.md` §2,
  `SECURITY_MATRIX.md` §1.1). A command the user cannot perform MUST NOT appear.
- **Context-scoped.** Palette results reflect the **active context** and active
  workspace; Platform-Context commands (e.g. *Workspaces*, *AI Providers*) never
  appear in a Workspace Context, and vice versa (`NAVIGATION_MAP.md` §2).
- **Quick actions are consistent.** Inline quick actions on list rows and detail
  headers (e.g. the kebab/overflow menu on a Job row) MUST use the same labels,
  ordering, and confirmation patterns as the palette and the full screens — one
  vocabulary everywhere (§1).

---

## 5. Consistent Interaction Patterns

A user learns HaHireAI **once**. The same gesture means the same thing on every
surface (`UI_GUIDELINES.md` §1, §7).

- **Shared component grammar.** Lists, tables, forms, buttons, badges, tabs,
  drawers, modals, toasts, and empty states come from one shared component set
  (`UI_GUIDELINES.md` §7). Modules **reuse** these; re-implementing a component
  per module is a defect.
- **One action vocabulary.** Create, edit, archive, delete (soft), filter, sort,
  paginate, bulk-select, confirm, and export behave identically everywhere. A
  destructive action MUST always confirm; a state-changing action MUST always
  surface a Success state (§7).
- **Predictable placement.** Primary actions, secondary/overflow actions,
  filters, and breadcrumbs sit in consistent positions across every screen so
  muscle memory transfers (detailed placement: `PAGE_STANDARDS.md`).
- **Keyboard parity.** Core flows MUST be operable by keyboard, with the command
  palette (§4) as the universal accelerator. Shortcuts MUST be consistent and
  discoverable, and MUST mirror correctly under RTL (§8, `UI_GUIDELINES.md` §6).
- **Progressive enhancement.** The baseline works as server-rendered HTML;
  Alpine.js adds only light interactivity (tabs, dropdowns, modals) and never
  owns authoritative state or business logic (`UI_GUIDELINES.md` §4). An
  interaction MUST degrade gracefully, never break, without JavaScript.
- **No role-name branching in the experience.** No interaction, label, or visible
  affordance is chosen by a role's *name*; behavior derives from permission keys
  (`UI_GUIDELINES.md` §2, `PERMISSION_MODEL.md` Invariant 1).

---

## 6. Minimal-Step Access to Any Feature

The product MUST minimize the steps between intent and outcome
(`UI_GUIDELINES.md` §1).

- **Three paths to anything.** Any permitted feature SHOULD be reachable by at
  least one of: the **one sidebar**, **unified search** (§2), or the **command
  palette** (§4). A capability that exists but is reachable only by a deep,
  undiscoverable click-path is an experience defect.
- **Act in place.** Frequent actions (move a stage on the *Pipeline*, add a note
  on a *Candidate Profile*, schedule from an *Interview*) SHOULD be doable from
  the context where the user already is — via a drawer or modal (§9) — without a
  full-page detour, subject to permission.
- **Cross-links over navigation.** Lateral relationships (Application to Candidate
  Profile to Job) are surfaced as direct links, not as journeys the user must
  reconstruct through the sidebar (`NAVIGATION_MAP.md` §6,
  `SCREEN_RELATIONSHIPS.md` §3).
- **Deep-linkable.** Notifications (§3) and search results (§2) jump straight to
  the target entity, never to a generic landing the user must drill down from.

---

## 7. Empty-State & First-Use Philosophy

A blank screen is a defect (`UI_GUIDELINES.md` §8). Every screen and
data-bearing component MUST deliberately handle its **first-use / empty** state,
alongside loading, no-permission, error, success, and offline/degraded.

- **First-use teaches and invites.** An empty state MUST explain *what this is*
  and offer the **next action**, subject to permission — e.g. the first-run
  *Jobs* screen explains requisitions and offers *Create job* only if the user
  holds `job.create`; an empty *Talent Pool* explains saved candidates and
  points back to a *Candidate Profile*.
- **The no-workspace first-run is sacred.** A newly registered `User` with no
  `Membership` sees **exactly two paths — Create Workspace or Join Workspace —
  and nothing else** in the navigation (`USER_JOURNEYS.md` Journey 1 step 4 and
  Invariant 2, `NAVIGATION_MAP.md`). This is the product's most important empty
  state and MUST NOT be cluttered with anything else.
- **Permission-aware emptiness.** When a list is empty because the user lacks
  permission, the screen shows the **no-permission** state — a clear, non-leaking
  message — never an inviting "create" affordance the user cannot use, and never
  the data (`UI_GUIDELINES.md` §8, `SECURITY_MATRIX.md` §1.1).
- **Subscription- and module-aware emptiness.** When a capability is absent
  because the subscription or enabled module excludes it (e.g. AI-backed actions
  on a plan without the AI Engine), the empty state SHOULD explain the
  entitlement, not present a dead button (`SECURITY_MATRIX.md` §1.1).
- **Consistency.** Empty states MUST be delivered by a shared component so every
  screen inherits the same tone, structure, and placement (`UI_GUIDELINES.md`
  §7, §8).

---

## 8. Responsive Strategy

HaHireAI is a **responsive, server-rendered web application** — not a SPA and not
a native mobile app (`UI_GUIDELINES.md` §4). One codebase adapts across
breakpoints; the *experience* is preserved, the *layout* adapts.

| Surface | Desktop | Tablet | Mobile |
|---|---|---|---|
| **Shell & sidebar** | Persistent sidebar + top bar | Collapsible sidebar; top bar persists | Sidebar collapses to a drawer; top bar (search, notifications, context) persists |
| **Global search & notifications** | Inline from top bar | Inline from top bar | Full-screen overlay invoked from top bar |
| **List screens** (Jobs, Members, Files, Reports) | Multi-column tables with inline actions | Reduced columns; overflow into row menu | Card/stacked rows; primary action per card; filters in a drawer |
| **Detail + tabs** (Job Detail, Candidate Profile) | Tabs inline; side context visible | Tabs inline; context may stack | Tabs become a scrollable/segmented control; context stacks vertically |
| **Pipeline (Kanban)** | Full multi-stage board | Horizontally scrollable board | Stage-by-stage view with stage switcher; drag replaced by an explicit move action |
| **Drawers / modals** (§9) | Drawer or centered modal | Drawer or modal | Full-screen sheet |

Binding rules:

- **Behavior is preserved across breakpoints.** A capability available on desktop
  MUST remain reachable on mobile; the affordance MAY change (drawer vs inline,
  explicit-move vs drag), but the capability MUST NOT disappear due to viewport
  alone — only permission, subscription, and module gate availability.
- **Direction-aware and mirrored.** Every responsive layout MUST use logical
  start/end (not hard left/right) and mirror correctly under RTL — including the
  sidebar drawer, breadcrumb chevrons, and progress indicators
  (`UI_GUIDELINES.md` §6).
- **No layout thrash.** Space for async content is reserved so layout does not
  jump as content or breakpoints change (`UI_GUIDELINES.md` §8, §10).
- **Touch targets.** Interactive targets MUST be comfortably tappable on
  touch surfaces without sacrificing keyboard operability on pointer surfaces.

---

## 9. Full Page vs Modal vs Drawer vs Tab (UX intent)

The *experience* rationale for each container is summarized below; the canonical,
binding decision rules and the per-screen assignments live in
`SCREEN_RELATIONSHIPS.md` §2 and `SCREEN_CATALOG.md`.

- **Full page** — a primary destination with its own URL, breadcrumb, and a place
  in the sidebar or a drill-down chain (e.g. *Jobs*, *Job Detail*, *Candidate
  Profile*). Use when the user has *arrived somewhere*.
- **Tab** — a facet of one entity that shares its identity and breadcrumb (e.g.
  *Job Detail ▸ Applications / Pipeline / Interviews / Offers / Hiring Team /
  Settings*). Use to organize one thing, never to stitch unrelated things.
- **Drawer** — a focused side panel for context or a quick edit *without leaving*
  the current screen (e.g. add a note, quick-edit, preview a record). Use when
  the user must keep their place.
- **Modal** — a short, blocking, self-contained task or confirmation (e.g.
  *Create job*, *Invite member*, destructive confirmations). Use sparingly and
  never to host a long, multi-step destination.

Whatever the container, the **mandatory screen states** (§7, `UI_GUIDELINES.md`
§8) and **accessibility** rules (§10) apply equally.

---

## 10. Accessibility — A Baseline, Not a Later Phase

Accessibility is **binding from the first line of markup** (`UI_GUIDELINES.md`
§9). It is part of the experience, not a follow-up project.

- **Keyboard navigation.** Every interactive element MUST be reachable and
  operable by keyboard alone, in a logical order, with no keyboard traps. The
  command palette (§4) and global search (§2) MUST be keyboard-first.
- **Focus management.** Focus MUST be visible and deliberately managed — moved
  into opened modals and drawers (§9) and restored to the trigger on close.
- **ARIA & semantics.** Use correct semantic HTML first; add ARIA roles, names,
  and states only where semantics are insufficient. Icon-only controls (search,
  notifications, overflow menus) MUST have accessible, **localized** names
  (`UI_GUIDELINES.md` §6, §9).
- **Color contrast.** Text and essential UI MUST meet **WCAG 2.1 AA** contrast,
  and color MUST NOT be the only carrier of meaning — pair it with text or icon
  (e.g. application stage, job state).
- **Screen readers.** Content MUST be navigable and understandable with a screen
  reader; dynamic updates (toasts, async search and AI results) MUST be announced
  via appropriate live regions (`UI_GUIDELINES.md` §8, §9).
- **Direction-aware a11y.** Accessibility MUST hold equally in **AR/RTL** and
  **EN/LTR** — focus order, shortcuts, and announcements included
  (`UI_GUIDELINES.md` §6).
- **Inseparable from components.** Accessibility is built into the shared
  components once, so every screen inherits correct focus order, ARIA, and
  direction-awareness rather than re-solving it per screen (`UI_GUIDELINES.md`
  §7).

---

## 11. The Design System Is a Developer-Mode Tool

HaHireAI is built on a **design-token system** and a shared component library
(`UI_GUIDELINES.md` §5, §7). The component gallery / design-system reference is a
**Developer-Mode tool — NOT a product feature, and NOT a sidebar entry.**

- **Not in the product navigation.** The design system MUST NOT appear in the one
  workspace sidebar. End users — owners, recruiters, hiring teams, candidates —
  never see it. It is an engineering artifact, not a screen a customer operates.
- **Behind Developer Tools, feature-flagged.** Any in-app surfacing belongs with
  **Developer Tools** in the Platform Context, which is **hidden unless explicitly
  enabled** by feature flag and gated by `system.dashboard.view` + feature flag
  (`NAVIGATION_MAP.md` §3, §7.1, `SECURITY_MATRIX.md` §2). It is never visible in
  a Workspace Context.
- **The standard, not a destination.** The design system's purpose is to keep the
  product coherent (§1): one set of tokens (color, spacing, typography, radius,
  elevation, z-index) and one component contract that every screen consumes
  (`UI_GUIDELINES.md` §5, §7). It governs the experience from behind the scenes;
  users feel its consistency without ever navigating to it.
- **Branding flows through tokens.** Per-workspace branding (logo, cover, colors,
  favicon, email branding) is applied by resolving the active workspace's tokens,
  without forking templates or components, and never crosses the tenant boundary
  (`UI_GUIDELINES.md` §5).

---

## 12. UX Self-Review Checklist (Phase 5)

A screen or flow is conformant only if **all** hold:

- [ ] **One product.** No visual or interaction seam reveals a module boundary;
      shared components and one action vocabulary are used (§1, §5).
- [ ] **One sidebar, gated.** Navigation derives from context + permissions +
      subscription + enabled modules; no role-name branching (§1,
      `UI_GUIDELINES.md` §2).
- [ ] **Context is unmistakable.** The active context (Platform vs Workspace) and
      active workspace are always clear (§1).
- [ ] **Global search is unified & grouped.** One top-bar entry, grouped results
      across the defined entity types, tenant-isolated, target-gated (§2).
- [ ] **One notification center.** No per-module notification pages; every item
      deep-links to its origin (§3).
- [ ] **Minimal-step access.** Every permitted feature is reachable via sidebar,
      search, or command palette; frequent actions act in place (§4, §6).
- [ ] **All six screen states.** Empty/first-use, loading, no-permission, error,
      success, offline are handled — emptiness is permission/subscription-aware
      (§7, `UI_GUIDELINES.md` §8).
- [ ] **No-workspace first-run.** A user with no membership sees only Create
      Workspace / Join Workspace (§7).
- [ ] **Responsive parity.** Capabilities survive every breakpoint; layouts are
      direction-aware and mirror under RTL; no layout thrash (§8).
- [ ] **Accessibility baseline.** Keyboard nav, focus management, ARIA, AA
      contrast, screen-reader announcements — in AR/RTL and EN/LTR (§10).
- [ ] **Design system stays a dev tool.** It is never a sidebar feature; any
      surfacing is behind feature-flagged Developer Tools in the Platform Context
      (§11).

---

### Related Documents

`UI_GUIDELINES.md` · `NAVIGATION_MAP.md` · `USER_JOURNEYS.md` ·
`SCREEN_RELATIONSHIPS.md` · `SECURITY_MATRIX.md` · `PERMISSION_MODEL.md` ·
`WORKSPACE_MODEL.md` · `APPLICATION_FLOW.md` · `MODULES.md` ·
`SCREEN_CATALOG.md` · `NAVIGATION_ARCHITECTURE.md` · `SIDEBAR_MODEL.md` ·
`LAYOUT_SYSTEM.md` · `PAGE_STANDARDS.md` · `DASHBOARD_GUIDE.md`
