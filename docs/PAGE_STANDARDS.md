# PAGE STANDARDS — HaHireAI

> **Status:** Adopted (Phase 5) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `LAYOUT_SYSTEM.md`, `UI_GUIDELINES.md`, `SCREEN_CATALOG.md`.

---

## 0. About This Document

This document defines the **standard anatomy and behavior every page MUST
follow** in HaHireAI: the page header, the filters/toolbar, the content body,
pagination, the six mandatory states, and the cross-cutting standards for forms,
tables, modals/drawers, and feedback. It is the per-page counterpart to
`LAYOUT_SYSTEM.md` (the shell and tokens) and `UI_GUIDELINES.md` (principles).

This document **defers** to `LAYOUT_SYSTEM.md`, `UI_GUIDELINES.md`, and
`SCREEN_CATALOG.md`, and — above all — to `PROJECT_CONSTITUTION.md`. It does not
re-litigate the stack, tenancy, or the permission model. Interpretation keywords
(**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**, **MAY**) follow RFC 2119; a
**MUST / MUST NOT** rule is binding and a violation is a defect.

**Consistency is a requirement, not a preference** (`UI_GUIDELINES.md` §1). The
same action MUST look and behave the same on every page. A one-off page that
diverges from this anatomy is a defect. These standards SHOULD be delivered as
**shared components** so each module inherits correct behavior
(`UI_GUIDELINES.md` §7).

> Markup/class snippets are **illustrative only** and MUST NOT be copied as
> canonical code.

---

## 1. Standard Page Anatomy

Every page renders into the shell's **content region** (`LAYOUT_SYSTEM.md` §1) and
MUST be composed of these stacked zones, in order:

```
┌─ CONTENT REGION ─────────────────────────────────────────────┐
│  PAGE HEADER   breadcrumbs · title (+count) · primary actions  │
├───────────────────────────────────────────────────────────────┤
│  TOOLBAR       search · filters · view switch · bulk actions    │
├───────────────────────────────────────────────────────────────┤
│  PAGE BODY     table / list / cards / form  (one of the SIX     │
│                states — §3 — at any moment)                     │
├───────────────────────────────────────────────────────────────┤
│  FOOTER ZONE   pagination · result summary · (sticky save bar)  │
└───────────────────────────────────────────────────────────────┘
```

- A page MUST have **exactly one** primary `<h1>` title (localized —
  `UI_GUIDELINES.md` §6) and SHOULD show a result/record count where meaningful.
- Zones MUST appear in this order and MUST mirror correctly under RTL
  (`LAYOUT_SYSTEM.md` §4). Zones not applicable to a page (e.g. no toolbar on a
  pure detail page) are omitted, not reordered.
- All zones MUST use the spacing scale and grid from `LAYOUT_SYSTEM.md` §2.

### 1.1 Page header

- **Breadcrumbs** MUST reflect the navigation hierarchy from `NAVIGATION_MAP.md`
  (§5 drill-down chains) and MUST be permission- and tenant-gated — a crumb links
  only to a target the user may reach in the active workspace
  (`NAVIGATION_MAP.md` §6). Breadcrumb separators mirror under RTL.
- **Title** is the primary `<h1>`; an optional subtitle/description and a status
  badge MAY accompany it (e.g. a Job's `Draft / Published` state —
  `STATE_DIAGRAMS.md`).
- **Primary actions** sit at the inline **end** of the header. A page MUST expose
  at most **one** visually primary action; others are secondary/tertiary or move
  into an overflow menu. Every action MUST be **permission-gated** — a control the
  user cannot perform MUST NOT render (hiding complements, never replaces, server
  enforcement — `UI_GUIDELINES.md` §1, `PERMISSION_MODEL.md` §5,
  `ACCESS_POLICIES.md`).

### 1.2 Filters / toolbar

- The toolbar hosts **search**, **filters**, **sort**, **view switching**
  (table/board/cards), and the **bulk-action bar** (revealed on selection, §4).
- Applied filters MUST be **visible** (e.g. chips) and individually clearable,
  with a "clear all" affordance. Filter, sort, and pagination state SHOULD be
  reflected in the URL (query params) so a view is shareable and restorable, and
  MUST stay within the active workspace's data (`WORKSPACE_MODEL.md` §3).
- The toolbar SHOULD remain accessible while the body scrolls (sticky, `z-sticky`
  — `LAYOUT_SYSTEM.md` §5.4) for long lists.

### 1.3 Content body

The body holds exactly one primary content pattern at a time — **table/list**
(§5), **cards**, **form** (§4), or a detail composition (which MAY use the
optional right panel, `LAYOUT_SYSTEM.md` §1). Whatever the pattern, the body MUST
render the correct one of the **six mandatory states** (§3) for its current
condition.

### 1.4 Footer zone

- List/table pages MUST show **pagination** plus a **result summary** (e.g.
  "1–25 of 312"). Unbounded lists are forbidden (Constitution §11); every list
  endpoint is paginated.
- Long forms MUST use a **sticky action/save bar** in this zone (§4) so primary
  submit/cancel remain reachable without scrolling.

---

## 2. Page Types (consistency baseline)

To keep modules coherent (`UI_GUIDELINES.md` §1), pages MUST conform to one of
these canonical types; their anatomy follows §1:

| Type | Body pattern | Notes |
|---|---|---|
| **List / index** | table or cards + toolbar + pagination | e.g. Jobs, Members (`NAVIGATION_MAP.md` §4) |
| **Board** | Kanban columns | e.g. Pipeline; supports Compact density (`LAYOUT_SYSTEM.md` §8.1) |
| **Detail** | header + tabbed sections (+ optional right panel) | e.g. Job Detail tabs (`NAVIGATION_MAP.md` §5) |
| **Form / create-edit** | sectioned form | §4 |
| **Dashboard** | metric cards + widgets that drill down | `DASHBOARD_GUIDE.md` |
| **Settings** | grouped sections / sub-nav | workspace & platform settings |

---

## 3. The Six Mandatory States

Every page and every data-bearing component MUST deliberately handle **all six**
applicable states (`UI_GUIDELINES.md` §8). Designing only the happy path is a
defect. States SHOULD be provided by shared components so modules inherit them.

| # | State | MUST requirements |
|---|---|---|
| 1 | **First-use / Empty** | Explain what this is and offer the next action (subject to permission, §1.1). Never a blank screen. Distinguish *no data yet* from *no results for current filters* (offer "clear filters"). |
| 2 | **Loading / Skeleton** | Non-blocking skeletons/placeholders that match final layout; the page MUST NOT jump when content arrives (reserve space — `LAYOUT_SYSTEM.md` §3, `UI_GUIDELINES.md` §10). |
| 3 | **No-Permission** | A clear, **non-leaking** message; never the data, never a stack trace, never a broken page. UI gating complements server enforcement (`PERMISSION_MODEL.md` §5, `SECURITY_MATRIX.md`). |
| 4 | **Error** | Human-readable, actionable message with a retry/next step; no internal details or stack traces (`ARCHITECTURE.md` §6). Distinguish transient (retry) from permanent (correct input/contact). |
| 5 | **Success** | Explicit confirmation of state-changing actions (toast or inline, §7), with the UI reflecting the **new** state immediately. |
| 6 | **Offline / Degraded** | Detect connectivity loss or a degraded backend; inform the user, avoid silent failure, and **prevent double-submission** (§4). Re-enable on recovery. |

These states MUST be **consistent across modules** (`UI_GUIDELINES.md` §8) and MUST
each hold in RTL/LTR and both density modes (`LAYOUT_SYSTEM.md` §4, §8.1).

---

## 4. Form Standards

Forms are server-validated; the client MAY mirror validation for UX but the
**server is the source of truth** (`UI_GUIDELINES.md` §4, Constitution §10).

**Structure & labels**
- Every field MUST have a **visible, persistent label** (placeholder is not a
  label) and MUST be programmatically associated with its control. Labels are
  localized (`UI_GUIDELINES.md` §6).
- Required vs optional MUST be explicit and consistent across the product.
- Long forms MUST be **sectioned** with headings and MUST mirror under RTL.
- Help text and constraints (formats, limits) SHOULD be shown before submission,
  not only on error.

**Validation & inline errors**
- Validation errors MUST appear **inline, adjacent to the offending field**, with
  a clear message; the field MUST be marked invalid (`aria-invalid`) and described
  by its error (`aria-describedby`). Error text MUST NOT rely on color alone
  (`UI_GUIDELINES.md` §9).
- On a failed submit, focus MUST move to the **first invalid field** and a brief
  summary MAY appear at the form top.
- Validation timing SHOULD be forgiving: validate on blur / on submit, not
  aggressively on every keystroke for empty fields.

**Autosave vs submit**
- A form MUST clearly be **either** an explicit-submit form (primary submit +
  cancel, with a sticky save bar for long forms, §1.4) **or** an **autosave**
  surface — never ambiguous.
- Autosave surfaces MUST show save status (saving / saved / failed) and MUST
  surface failures (state 4/6, §3) without losing user input.
- The primary submit MUST be **disabled or guarded against double-submission**
  while in flight (ties to Offline/Degraded, §3), and MUST show progress.

**Destructive & confirmations**
- Destructive actions (delete, archive, revoke, withdraw an offer) MUST require an
  explicit confirmation **modal** (§6) — not a dismiss-to-confirm. High-impact or
  irreversible actions SHOULD require typed confirmation and MUST state the
  consequence and scope (which workspace/record). This aligns with
  `ARCHIVING_POLICY.md` (soft-delete/restore semantics) and is audited
  (`AUDIT_EVENTS.md`).
- Confirmation dialogs MUST default focus to the **safe** (cancel) action.

```html
<!-- EXAMPLE ONLY — inline error association (illustrative) -->
<label for="title">Job title</label>
<input id="title" aria-invalid="true" aria-describedby="title-err" />
<p id="title-err" role="alert">Title is required.</p>
```

---

## 5. Table / List Standards

Tables are the workhorse of list pages and MUST behave identically across modules.

**Columns**
- Columns MUST have clear, localized headers; numeric/currency/date columns are
  formatted per workspace locale (`WORKSPACE_MODEL.md` §4) and SHOULD align
  consistently (numerics end-aligned, mirrored under RTL).
- A primary identifying column SHOULD link to the record's detail page
  (`NAVIGATION_MAP.md` §5). Row-level actions live in a trailing actions column or
  overflow menu and are **permission-gated** (§1.1).
- Column visibility/density MAY be user-configurable; Compact density
  (`LAYOUT_SYSTEM.md` §8.1) reduces row height only, never legibility.

**Sort & filter**
- Sortable columns MUST indicate sortability and current sort direction
  (`aria-sort`); sorting is server-side for paginated data and reflected in the
  URL (§1.2). Filters apply through the toolbar (§1.2).

**Bulk actions**
- Row selection MUST reveal a **bulk-action bar** showing the selection count and
  the available, permission-gated bulk actions, with a clear way to deselect.
  "Select all" MUST distinguish *page* selection from *all-matching* selection.
- Bulk destructive actions follow the destructive-confirmation rules (§4) and are
  audited (`AUDIT_EVENTS.md`).

**Empty / loading rows & pagination**
- A table MUST render the six states (§3) within its frame: skeleton **rows**
  while loading (keeping header and column widths stable), an in-table
  empty/no-results state, and an in-table error with retry.
- Every table MUST paginate (Constitution §11) with a result summary (§1.4).
  Page size SHOULD be user-selectable within bounded options. Pagination controls
  mirror under RTL.

---

## 6. Modal & Drawer Standards

Modals and drawers are **shared overlay components** bound to the z-ladder
(`LAYOUT_SYSTEM.md` §6, §7). Choose by intent:

- **Modal** — a focused, **blocking** task or confirmation that the user must
  resolve or dismiss (create-quick, destructive confirm, single-step action).
- **Drawer** — a **contextual side panel** for detail, edit-in-context, or
  inspector content; slides from the inline start/end (RTL-aware,
  `LAYOUT_SYSTEM.md` §4). The optional right panel becomes a drawer on
  tablet/mobile (`LAYOUT_SYSTEM.md` §3).

Binding rules (both):
- MUST have an accessible name (labelled title), `role="dialog"` (modal:
  `aria-modal="true"`), a visible close control, and Escape-to-close for
  non-destructive content.
- MUST **trap focus** while open and **restore focus** to the trigger on close;
  the background scrim MUST lock scroll without layout shift (`LAYOUT_SYSTEM.md`
  §6).
- MUST render the relevant six states (§3) for any data they load or submit (e.g.
  a loading skeleton, an inline error with retry).
- SHOULD avoid stacking; if a confirmation is needed from within a modal, the
  topmost layer owns focus/Escape (`LAYOUT_SYSTEM.md` §6).
- Heavy multi-step flows SHOULD prefer a full page over a modal; modals are for
  focused, short interactions.

---

## 7. Toast & Notification Feedback

- **Toasts** (`z-toast`, `LAYOUT_SYSTEM.md` §5.4) give transient, non-blocking
  feedback for completed actions (Success state, §3) and recoverable errors. They
  stack in a single region, sit clear of primary actions, and mirror placement
  under RTL.
- Toasts MUST be **announced to assistive tech** (polite for success/info,
  assertive for errors — `UI_GUIDELINES.md` §9) and MUST be dismissible. Auto-
  dismiss SHOULD apply to success/info; **errors MUST persist** until dismissed or
  resolved. Toasts MUST NOT be the sole channel for critical/destructive outcomes
  — pair with inline state where the action occurred.
- A toast MAY offer one inline action (e.g. "Undo", "View") where the domain
  supports it (e.g. archive → undo within the window, `ARCHIVING_POLICY.md`).
- **In-app notifications** (the bell — `NAVIGATION_MAP.md` §7.2) are persistent,
  per-user, workspace-contextual records that deep-link to the entity that raised
  the event (`NAVIGATION_MAP.md` §6); they are distinct from transient toasts and
  MUST NOT be conflated.

---

## 8. Cross-Module Consistency Rules (binding)

These guarantee the product reads as **one product** (`UI_GUIDELINES.md` §1):

- Identical interactions (**create, edit, archive/delete, filter, sort,
  paginate, confirm, bulk-act**) MUST use the **same shared components** and the
  same wording patterns everywhere; divergent one-off UI is a defect.
- Action **placement** is fixed: primary action at the header inline-end (§1.1);
  filters/search in the toolbar (§1.2); pagination in the footer (§1.4);
  destructive confirms in a modal (§6).
- **Permission/tenant gating** is uniform: any control or link the user may not
  use in the active workspace MUST NOT render, and the server still enforces
  (`SECURITY_MATRIX.md`, `ACCESS_POLICIES.md`).
- **Terminology** MUST follow the ubiquitous language (`DOMAIN_MODEL.md`) and be
  localized AR/EN with parity (`UI_GUIDELINES.md` §6).
- All copy is **catalog-driven**; no hard-coded user-facing strings
  (`UI_GUIDELINES.md` §6).
- Every page MUST honor the layout tokens, breakpoints, density, RTL/LTR, and
  dark-mode readiness of `LAYOUT_SYSTEM.md`.

---

## 9. Accessibility Per Component (binding)

Accessibility is a baseline, not a later phase (`UI_GUIDELINES.md` §9). Per
component:

- **Page header** — one `<h1>`; breadcrumbs as a `nav` landmark with an
  accessible name; logical heading order down the page.
- **Toolbar / filters** — controls have labels; applied-filter chips are operable
  by keyboard; search input has an accessible name.
- **Forms** — label association, `aria-invalid` + `aria-describedby` on errors,
  focus to first invalid field, grouped fields use `fieldset`/`legend` (§4).
- **Tables** — proper header semantics, `aria-sort` on sortable columns,
  selection controls labelled, bulk-action bar announced on appearance (§5).
- **Modals/drawers** — `role="dialog"`, focus trap + restore, Escape, labelled
  title (§6).
- **Toasts/notifications** — live-region announcements with correct politeness;
  not keyboard-trapping (§7).
- **Global** — full keyboard operability with no traps, visible focus ring
  (`color-focus-ring`, `LAYOUT_SYSTEM.md` §5.1), WCAG 2.1 AA contrast, meaning
  never carried by color alone, and equal correctness in **AR/RTL** and **EN/LTR**
  and in both density modes.

---

## Self-Review Checklist (Page gate)

- [ ] Page follows the standard anatomy (header → toolbar → body → footer) and a
      canonical page type (§1, §2).
- [ ] Exactly one `<h1>`; one visually primary action; all actions
      permission-gated.
- [ ] All six states implemented (first-use/empty, loading, no-permission, error,
      success, offline) and consistent.
- [ ] Forms: visible labels, inline errors, explicit submit-vs-autosave,
      double-submit guarded, destructive actions confirmed.
- [ ] Tables: sortable indicators, bulk-action bar, skeleton rows, in-table
      empty/error, pagination + result summary.
- [ ] Modals/drawers: focus trap + restore, scrim scroll-lock, Escape, labelled
      title; the right intent (modal vs drawer).
- [ ] Toasts announced + dismissible; errors persist; critical outcomes also shown
      inline; in-app notifications distinct from toasts.
- [ ] No hard-coded strings; terminology matches `DOMAIN_MODEL.md`; correct in
      RTL & LTR and both densities; AA contrast.

---

### Related Documents

`LAYOUT_SYSTEM.md` · `UI_GUIDELINES.md` · `SCREEN_CATALOG.md` ·
`PROJECT_CONSTITUTION.md` · `NAVIGATION_MAP.md` · `DOMAIN_MODEL.md` ·
`PERMISSION_MODEL.md` · `ACCESS_POLICIES.md` · `SECURITY_MATRIX.md` ·
`STATE_DIAGRAMS.md` · `ARCHIVING_POLICY.md` · `AUDIT_EVENTS.md` ·
`WORKSPACE_MODEL.md` · `DASHBOARD_GUIDE.md`
