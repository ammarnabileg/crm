# SIDEBAR MODEL — HaHireAI

> **Status:** Adopted (Phase 5) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `NAVIGATION_ARCHITECTURE.md`, `PERMISSION_CATALOG.md`.

---

## 0. About This Document

This document specifies the **sidebar model** of HaHireAI: the single sidebar, the
**required attributes of every sidebar item**, the **generation algorithm**
(filter → group → order), **collapse/expand · sections · active state · badges**,
**context-aware rendering** (Platform vs Workspace), the **accessibility** of the
nav, and the binding **"no item without a reason"** rule.

It is a **paper design**: no HTML, CSS, or JavaScript, and the descriptor in §3.2
is **illustrative only** — a documentation shape, not canonical code. The
navigation *engine*, *contexts*, *routing*, *deep-linking*, and *command palette*
are owned by `NAVIGATION_ARCHITECTURE.md`; permission keys by
`PERMISSION_CATALOG.md`; screen names by `NAVIGATION_MAP.md`. Where this document
summarizes them, **they govern**. Interpretation keywords (**MUST**, **MUST NOT**,
**SHOULD**, **SHOULD NOT**, **MAY**) follow RFC 2119.

---

## 1. The Single Sidebar (binding)

1. **There is exactly ONE sidebar**, generated dynamically and **NEVER** per-role
   (`UI_GUIDELINES.md` §2; `WORKSPACE_MODEL.md` §7). The same component renders
   both contexts; only the items differ.
2. **One coherent product.** The sidebar follows the navigation grammar of Slack,
   Notion, Linear, GitHub, and the Stripe Dashboard (`UI_GUIDELINES.md` §1).
3. **Items are data, declared by modules** and assembled by the engine
   (`NAVIGATION_ARCHITECTURE.md` §9; `MODULES.md` §6) — never a hard-coded menu.
4. **No role-name branching** in the view-model, templates, or JS
   (`PERMISSION_MODEL.md` Invariant 1).
5. **Hiding is not enforcing.** Omitting an item is a UX convenience; the server
   still authorizes every action and read (`SECURITY_MATRIX.md` §1.1;
   `NAVIGATION_ARCHITECTURE.md` §6).

The sidebar is the **primary navigation surface** of the shell — the rendered
output of the engine's pipeline (`NAVIGATION_ARCHITECTURE.md` §4, stage 6).
Alongside it the shell hosts the **workspace/context switcher** and the
always-present top-bar **Search** and **Notifications** (`NAVIGATION_MAP.md` §4),
which are not sidebar items; they share the same context and gates and are
specified in `NAVIGATION_ARCHITECTURE.md` §3, §8.

---

## 2. The Sidebar Item — Required Attributes

Every item is a **descriptor** declared by its owning module. An item is
**invalid** unless it carries all required attributes; the engine MUST reject
(skip) a malformed descriptor rather than render a guesswork entry.

| Attribute | Requirement | Notes |
|---|---|---|
| **Title** | **MUST** | A localization **key** resolved to AR/EN — never a literal string (`UI_GUIDELINES.md` §6). |
| **Icon** | **MUST** | A shared icon-set token; direction-aware where directional (§6.4). Collapsed icon items still need an accessible name (§7). |
| **Permission** | **MUST** | The exact `*.view`/`*.use` key from `PERMISSION_CATALOG.md` that gates visibility — the read gate, not an action verb. |
| **Route** | **MUST** | The stable route the item navigates to (`NAVIGATION_ARCHITECTURE.md` §7), declared by the manifest. |
| **Badge Support** | **MUST** (MAY be none) | Whether the item shows a count/dot, and the **lazy** provider supplying it under the same gate as the item (§5). "None" is explicit. |
| **Children** | **MUST** (MAY be empty) | Ordered child descriptors. A parent with no *visible* children is dropped (§3). |
| **Search Keywords** | **MUST** | Localized synonyms so the command palette/global search can surface the entry; indexed only when visible. |
| **Documentation Reference** | **MUST** | The canonical doc/screen this item maps to (e.g. a `NAVIGATION_MAP.md` screen name) — "no item without a reason" (§8). |

Supporting attributes the engine MAY read (non-gating, for ordering/grouping/
rendering only): **Section** (§4.1), **Order** (stable sort key, §3), **Module** and
**enabledBy** (used by the gate, §3), and **Active-match** (route prefix for active/
expanded state, §4.3). These never decide visibility — only the three-way AND does.

### 2.1 Illustrative item descriptor (NOT code — documentation shape only)

> Illustrative only, to make the attributes concrete. **Not** an implementation,
> **not** canonical, and MUST NOT be copied as markup or config. Real items are
> declared in module manifests (`MODULES.md` §6).

```
ITEM (illustrative)
  title:       "nav.recruitment.jobs"          # i18n key (AR/EN), never a literal
  icon:        "icon.briefcase"                # shared token; mirrors under RTL if directional
  permission:  "job.view"                      # exact PERMISSION_CATALOG.md key (visibility gate)
  route:       "<workspace>/recruitment/jobs"  # stable route (NAVIGATION_ARCHITECTURE.md §7)
  module:      "Recruitment"                   # owning module (MODULES.md)
  enabledBy:   "recruitment"                   # subscription/feature precondition
  section:     "Recruitment"                   # grouping (§4)
  order:       20                              # stable sort key within section
  badge:       { supported: true,              # Badge Support (declared)
                 provider: "jobs.openCount",   #   lazy, gated like the item (§5)
                 style:    "count" }
  activeMatch: "<workspace>/recruitment/jobs*" # active/expanded computation (§4.3)
  keywords:    ["jobs","requisitions","وظائف"] # palette/search (localized)
  docRef:      "NAVIGATION_MAP.md ▸ Workspace ▸ Recruitment ▸ Jobs"
  children:    [ /* Job Detail is reached via the screen, not a static child */ ]
```

---

## 3. Generation Algorithm (filter → group → order)

The sidebar is produced by the engine's pipeline (`NAVIGATION_ARCHITECTURE.md`
§4). The sidebar-specific algorithm:

```
GENERATE SIDEBAR(context, workspace, permissions, subscription, modules):

  1. COLLECT  all sidebar descriptors declared in the Module Registry
              for the resolved context (Platform vs Workspace)

  2. FILTER   three-way AND, deny-by-default (the ONLY visibility decision):
                 keep IFF hasPermission(item.permission)
                      AND moduleEnabled(item.module)
                      AND subscriptionAllows(item.enabledBy)
                 recurse into children with the same test

  3. PRUNE    drop a parent whose visible-children set is empty AND whose own
              route is not independently meaningful (header-only parent removed)

  4. GROUP    place kept items into their declared Section (§4); drop empty sections

  5. ORDER    sort sections by canonical order; within a section by item.order
              then title — stable & deterministic

  6. DECORATE localize Title/keywords (AR/EN); resolve direction (RTL/LTR);
              attach lazy badges; compute active/expanded from the active route

  → return the ONE sidebar view-model
```

Guarantees: **determinism** (same inputs ⇒ identical sidebar; no role-name
branching); **single gate** (step 2 is the only visibility decision, three-way AND
only, `SECURITY_MATRIX.md` §1.1); **empty-safe** (any user may see a minimal
sidebar; the shell still renders); **cheap** (runs per request and per
context/workspace switch; badges are lazy and never block first render,
`UI_GUIDELINES.md` §10).

---

## 4. Sections, Collapse/Expand & Active State

**4.1 Sections.** The sidebar is organized into **sections** reflecting the screen
tree of `NAVIGATION_MAP.md` — in the Workspace Context: the workspace **Dashboard**;
the **Recruitment** bounded context (Dashboard, Jobs, Talent Pool, Pipeline,
Interviews, Offers, Analytics); then **Members**, **Reports**, **Files**,
**Settings**, **Billing**. Section membership and order are **declared**, stable,
and identical across renders; empty sections are dropped (§3).

**4.2 Collapse / expand.** The sidebar **MAY** collapse to an icon rail and expand
to labels; the state is a persisted user preference. A **parent with children** MAY
expand/collapse its subtree, and the active branch's expansion follows the active
route (§4.3). Collapsed icon-only items MUST keep an **accessible name** and a
localized tooltip — meaning is never icon-only (§7).

**4.3 Active state.** The engine computes **active** and **expanded** from the
active route via each item's active-match prefix (§2). Exactly one leaf is active;
its ancestors are marked expanded/current. Active state MUST NOT rely on color
alone — it pairs with a non-color indicator (weight, marker, `aria-current`) for
contrast and color-independence (`UI_GUIDELINES.md` §9). Deep-linking or a context
switch re-derives active state from scratch (`NAVIGATION_ARCHITECTURE.md` §3, §7).

---

## 5. Badges & Counts

- An item shows a badge only if its descriptor declares **Badge Support** (§2). The
  value comes from a **lazy provider** resolved after first render so it never
  blocks the page (`UI_GUIDELINES.md` §10).
- A provider is gated by the **same** three-way AND as its item: a hidden item's
  provider is never called; empty data omits the badge (not shown as zero unless the
  descriptor opts into a zero style).
- Badge data is **tenant- and context-scoped**: counts reflect only the active
  workspace's data (e.g. open Jobs, pending Interviews, unread Notifications), never
  another tenant's (`SECURITY_MATRIX.md` §1.3).
- Badge numbers are localized (locale digits/format, §6.4) and have an accessible
  text equivalent (e.g. "3 pending interviews"), announced on change (§7).

---

## 6. Context-Aware Rendering (Platform vs Workspace)

The **same** sidebar component renders both contexts; the engine supplies a
different item set per resolved context (`NAVIGATION_ARCHITECTURE.md` §3). The
sidebar MUST make the active context unmistakable and MUST NOT leak items, badges,
or branding across contexts.

**6.1 Workspace Context** — items are the Workspace-Context tree
(`NAVIGATION_MAP.md` §4), each gated by **permission AND subscription AND enabled
module** (§3). Representative items and their exact visibility keys
(`SECURITY_MATRIX.md` §3):

| Item | Visibility key | Enabling module |
|---|---|---|
| Dashboard | `workspace.view` | Workspaces |
| Recruitment ▸ Dashboard | `job.view` | Recruitment |
| Recruitment ▸ Jobs | `job.view` | Recruitment |
| Recruitment ▸ Talent Pool | `talent.view` | Recruitment |
| Recruitment ▸ Pipeline | `pipeline.view` | Recruitment |
| Recruitment ▸ Interviews | `interview.view` | Recruitment |
| Recruitment ▸ Offers | `offer.view` | Recruitment |
| Recruitment ▸ Analytics | `report.view` | Reports / Analytics |
| Members | `member.view` | Memberships |
| Members ▸ Roles | `role.view` | Permissions |
| Reports | `report.view` | Reports / Analytics |
| Files | `files.view` | Files |
| Settings | `settings.view` | Settings |
| Billing | `billing.view` | Billing / Subscriptions |

The sidebar reflects the **active workspace's branding** (logo/colors) via tokens
resolved at render, without forking the component; one workspace's brand MUST NOT
appear in another (`UI_GUIDELINES.md` §5; `WORKSPACE_MODEL.md` §4).

**6.2 Platform Context** — items are the Platform-Context tree (`NAVIGATION_MAP.md`
§3), each gated by a `system.*` key (and any feature flag), **not** workspace-
subscription gated (`SECURITY_MATRIX.md` §2):

| Item | Visibility key |
|---|---|
| Overview | `system.dashboard.view` |
| Companies / Workspaces | `system.workspaces.manage` |
| Users | `system.users.manage` |
| Subscriptions | `system.subscriptions.manage` |
| AI Providers | `system.ai.manage` |
| System Analytics | `system.observability.view` |
| Audit Logs | `system.audit.view` |
| Platform Settings | `system.settings.manage` |
| Diagnostics | `system.diagnostics.run` |
| Developer Tools | `system.dashboard.view` **+ feature flag** (hidden unless enabled) |

**6.3 Switching.** A workspace or context switch re-derives the whole sidebar from
the new context's inputs; nothing from the prior context persists
(`NAVIGATION_ARCHITECTURE.md` §3).

**6.4 Direction & localization.** The sidebar mirrors fully under **RTL** (Arabic)
and renders normally under **LTR** (English) from one direction-agnostic view-model,
using logical start/end — never hard left/right (`UI_GUIDELINES.md` §6); collapse
handles, expand chevrons, and directional icons mirror. All titles, tooltips,
section headers, keywords, and badge text are localized AR/EN — a literal string in
the sidebar is a defect; AR/EN are peers.

---

## 7. Accessibility of the Sidebar

The sidebar MUST meet the Phase-1 accessibility baseline (`UI_GUIDELINES.md` §9),
specifically:

- **Landmark & structure.** A navigation landmark with an accessible, localized
  name; sections are programmatically grouped and labeled.
- **Keyboard navigation.** Every item is reachable and operable by keyboard alone in
  a logical order, no traps. The sidebar SHOULD use a roving tabstop with arrow-key
  movement; **Enter/Space** activates; expandable parents toggle with the standard
  keys and expose `aria-expanded`.
- **Current item.** The active item exposes `aria-current` (e.g. `page`), not color
  alone (§4.3).
- **Icon-only safety.** Collapsed icon items expose an accessible name and tooltip;
  meaning is never icon-only (§4.2).
- **Badges announced.** Badge counts have a text equivalent and changes are
  announced to assistive technology (§5).
- **Focus management.** Focus is visible; a drawer sidebar moves focus in on open and
  restores on close.
- **Direction-aware a11y.** All of the above hold equally in AR/RTL and EN/LTR;
  arrow-key "next" follows reading order per direction (§6.4).

---

## 8. "No Item Without a Reason" (binding)

Every sidebar item MUST justify its existence; speculative or decorative entries are
defects.

- **A reason = a mapped screen + a gate + a doc reference.** Each item MUST carry a
  **Route** to a real screen in `NAVIGATION_MAP.md`, a **Permission** key from
  `PERMISSION_CATALOG.md`, and a **Documentation Reference** (§2). An item that
  cannot cite all three MUST NOT exist.
- **No orphan items.** An item MUST NOT point to a non-existent or undocumented
  screen, nor exist "for layout"; header-only parents with no reachable target are
  pruned (§3, step 3).
- **No duplicate paths.** The same destination MUST NOT appear as two competing
  items; lateral relationships are cross-links on screens, not extra sidebar entries
  (`NAVIGATION_MAP.md` §6).
- **Additive, manifest-driven.** New items arrive only via module manifests, gated by
  the three-way AND (`NAVIGATION_ARCHITECTURE.md` §9); the component is never edited
  to add an entry.
- **Visibility ≠ authorization.** Even a fully justified, visible item is still
  authorized server-side on navigation (`SECURITY_MATRIX.md` §1.1).

---

## 9. Self-Review Checklist (Phase 5 sidebar gate)

Conformant only if **all** hold:

- [ ] **One sidebar, generated, never per-role** (§1).
- [ ] **Every item carries all required attributes** — Title, Icon, Permission, Route, Badge Support, Children, Search Keywords, Documentation Reference (§2); malformed descriptors are rejected.
- [ ] **Filter → group → order** runs the three-way AND as the only visibility decision, deny-by-default, deterministic (§3).
- [ ] **Sections, collapse/expand, active state** are declared, stable, color-independent; active/expanded derive from the route (§4).
- [ ] **Badges** are declared, lazy, tenant-scoped, localized, and announced (§5).
- [ ] **Context-aware rendering** uses exact keys per context; workspace branding applies without forking; no cross-context leakage (§6).
- [ ] **Accessible** — landmark, keyboard, `aria-current`/`aria-expanded`, icon-only names, announced badges, direction-aware (§7).
- [ ] **No item without a reason** — every item maps to a screen, a key, and a doc reference; no orphans, no duplicate paths (§8).
- [ ] **Bilingual & bidirectional** — AR/EN parity, logical start/end, full RTL/LTR mirroring (§6.4).
- [ ] **Defers to canon** — screen names from `NAVIGATION_MAP.md`, keys from `PERMISSION_CATALOG.md`, engine from `NAVIGATION_ARCHITECTURE.md`.

---

### Related Documents

`NAVIGATION_ARCHITECTURE.md` · `NAVIGATION_MAP.md` · `UI_GUIDELINES.md` ·
`PERMISSION_CATALOG.md` · `PERMISSION_MODEL.md` · `SECURITY_MATRIX.md` ·
`WORKSPACE_MODEL.md` · `USER_MODEL.md` · `MODULES.md` ·
`SCREEN_CATALOG.md` · `SCREEN_RELATIONSHIPS.md` (Phase 5)
