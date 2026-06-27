# SIDEBAR MODEL — HaHireAI

> **Status:** Adopted (Phase 5) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `NAVIGATION_ARCHITECTURE.md`, `PERMISSION_CATALOG.md`.

---

## 0. About This Document

This document specifies the **sidebar model** of HaHireAI: the single sidebar, the
**required attributes of every sidebar item**, the **generation algorithm** that
filters and orders items, the **collapse/expand · sections · active state ·
badges**, **context-aware rendering** (Platform vs Workspace), the **accessibility**
of the sidebar, and the binding **"no item without a reason"** rule.

It is a **paper design**. No HTML, CSS, or JavaScript is delivered here, and the
one descriptor structure shown in §3.2 is **illustrative only** — it is a
documentation shape, not canonical code. The navigation *engine*, *contexts*,
*routing*, *deep-linking*, and the *command palette* are owned by
`NAVIGATION_ARCHITECTURE.md`; permission keys are owned by
`PERMISSION_CATALOG.md`; screen names are owned by `NAVIGATION_MAP.md`. Where this
document summarizes those, **they govern**. Interpretation keywords (**MUST**,
**MUST NOT**, **SHOULD**, **SHOULD NOT**, **MAY**) follow RFC 2119.

---

## 1. The Single Sidebar (binding)

1. **There is exactly ONE sidebar.** It is **generated dynamically** and is
   **NEVER** per-role (`UI_GUIDELINES.md` §2; `WORKSPACE_MODEL.md` §7). The same
   sidebar component renders both contexts; only the items differ.
2. **One coherent product.** The sidebar follows the navigation grammar of Slack,
   Notion, Linear, GitHub, and the Stripe Dashboard — one consistent structure,
   one interaction model, across every module (`UI_GUIDELINES.md` §1).
3. **Items are data, declared by modules.** Every item originates from a module
   manifest and is assembled by the engine (`NAVIGATION_ARCHITECTURE.md` §9;
   `MODULES.md` §6). The sidebar is never a hard-coded menu.
4. **No role-name branching.** The sidebar MUST NOT branch on a role name — not in
   the view-model, not in templates, not in JS (`PERMISSION_MODEL.md`
   Invariant 1).
5. **Hiding is not enforcing.** Omitting an item is a UX convenience; the server
   still authorizes every action and read (`SECURITY_MATRIX.md` §1.1;
   `NAVIGATION_ARCHITECTURE.md` §6).

---

## 2. Where the Sidebar Sits in the Shell

The sidebar is the **primary navigation surface** of the app shell. It is the
rendered output of the navigation engine's pipeline (`NAVIGATION_ARCHITECTURE.md`
§4, stage 6). Alongside it the shell hosts the **workspace/context switcher** and
the always-present **top-bar** entries — **Search** and **Notifications**
(`NAVIGATION_MAP.md` §4) — which are part of the shell, not sidebar items, though
they share the same context and gates. This document covers the sidebar itself;
the switcher and palette behavior live in `NAVIGATION_ARCHITECTURE.md` §3, §8.

---

## 3. The Sidebar Item — Required Attributes

Every sidebar item is a **descriptor** declared by its owning module. An item is
**invalid** unless it carries all required attributes below; the engine MUST
reject (skip) a malformed descriptor rather than render a guesswork entry.

### 3.1 Required attributes

| Attribute | Requirement | Notes |
|---|---|---|
| **Title** | **MUST** | A localization **key** resolved to AR/EN — never a literal string (`UI_GUIDELINES.md` §6). |
| **Icon** | **MUST** | A token from the shared icon set; direction-aware where it implies direction (§7.4). Icon-only collapsed items still need an accessible name (§8). |
| **Permission** | **MUST** | The exact `*.view`/`*.use` key from `PERMISSION_CATALOG.md` that gates visibility. The read gate, not an action verb. |
| **Route** | **MUST** | The stable route the item navigates to (`NAVIGATION_ARCHITECTURE.md` §7), declared by the module manifest. |
| **Badge Support** | **MUST** (declared; MAY be none) | Whether the item shows a count/dot, and the **lazy** provider that supplies it under the same gate as the item (§6). Declaring "none" is explicit. |
| **Children** | **MUST** (MAY be empty) | Ordered child descriptors (e.g. Recruitment ▸ Jobs/Pipeline/…). Empty for leaf items; a parent with no *visible* children is dropped (§4). |
| **Search Keywords** | **MUST** | Localized synonyms so the command palette/global search can surface this entry (`NAVIGATION_ARCHITECTURE.md` §8); indexed only when the item is visible. |
| **Documentation Reference** | **MUST** | The canonical doc/screen this item maps to (e.g. `NAVIGATION_MAP.md` screen name), satisfying "no item without a reason" (§9). |

Supporting attributes the engine MAY read (non-gating, for ordering/grouping/
rendering only): **Section** (group placement, §5), **Order** (stable sort key,
§4), **Module** and **enabledBy** (the owning module and its subscription/feature
precondition used by the gate, §4), and **Active-match** (route prefix used to
compute active/expanded state, §5.3). These never decide visibility — only the
three-way AND does (§4).

### 3.2 Illustrative item descriptor (NOT code — documentation shape only)

> The following is an **illustrative descriptor** to make the attributes concrete.
> It is **not** an implementation, is **not** canonical, and MUST NOT be copied as
> markup or config. Real items are declared in module manifests (`MODULES.md` §6).

```
ITEM (illustrative)
  title:        "nav.recruitment.jobs"        # i18n key (AR/EN), never a literal
  icon:         "icon.briefcase"              # shared token; mirrors under RTL if directional
  permission:   "job.view"                    # exact PERMISSION_CATALOG.md key (visibility gate)
  route:        "<workspace>/recruitment/jobs"# stable route (NAVIGATION_ARCHITECTURE.md §7)
  module:       "Recruitment"                 # owning module (MODULES.md)
  enabledBy:    "recruitment"                 # subscription/feature precondition
  section:      "Recruitment"                 # grouping (§5)
  order:        20                            # stable sort key within section
  badge:        { supported: true,            # Badge Support (declared)
                  provider: "jobs.openCount", #   lazy, gated like the item (§6)
                  style:    "count" }
  activeMatch:  "<workspace>/recruitment/jobs*" # active/expanded computation (§5.3)
  keywords:     ["jobs","requisitions","وظائف","طلبات توظيف"]  # palette/search (localized)
  docRef:       "NAVIGATION_MAP.md ▸ Workspace Context ▸ Recruitment ▸ Jobs"
  children:     [ /* Job Detail is reached via the screen, not a static child */ ]
```

---

## 4. Generation Algorithm (filter → group → order)

The sidebar is produced by the navigation engine's pipeline
(`NAVIGATION_ARCHITECTURE.md` §4). The sidebar-specific algorithm is:

```
GENERATE SIDEBAR(context, workspace, permissions, subscription, modules):

  1. COLLECT
       items ← all sidebar descriptors declared in the Module Registry
               for the resolved context (Platform vs Workspace)

  2. FILTER  — three-way AND, deny-by-default (the ONLY visibility decision)
       keep item IFF:
            hasPermission(permissions, item.permission)      # exact catalog key
        AND moduleEnabled(modules, item.module)              # MODULES.md registry
        AND subscriptionAllows(subscription, item.enabledBy) # entitlement
       recurse into item.children with the same test

  3. PRUNE
       drop any parent whose visible-children set is empty
       AND whose own route is not independently meaningful
       (a header-only parent with no reachable target is removed)

  4. GROUP
       place kept items into their declared Section (§5),
       drop empty sections

  5. ORDER
       sort sections by canonical section order;
       within a section, sort by item.order then title — stable & deterministic

  6. DECORATE
       localize Title/keywords (AR/EN); resolve direction (RTL/LTR);
       attach lazy badge providers; compute active/expanded from active route

  → return the ONE sidebar view-model
```

Guarantees:

- **Determinism.** Same inputs ⇒ identical sidebar (`NAVIGATION_ARCHITECTURE.md`
  §4). No role-name branching, no randomness.
- **Single gate.** Step 2 is the **only** place visibility is decided, using the
  three-way AND only (`SECURITY_MATRIX.md` §1.1).
- **Empty-safe.** Any user may see a minimal sidebar; the shell still renders.
- **Cheap.** The algorithm runs per request and on every context/workspace switch;
  badges are lazy and never block first render (§6; `UI_GUIDELINES.md` §10).

---

## 5. Sections, Collapse/Expand & Active State

### 5.1 Sections

- The sidebar is organized into **sections** (groups) that reflect the screen tree
  of `NAVIGATION_MAP.md`, e.g. in the Workspace Context: the workspace **Dashboard**,
  the **Recruitment** bounded context (with Dashboard, Jobs, Talent Pool, Pipeline,
  Interviews, Offers, Analytics), then workspace-level **Members**, **Reports**,
  **Files**, **Settings**, **Billing**. Empty sections are dropped (§4).
- Section membership and order are **declared**, not improvised; ordering is stable
  and identical across renders.

### 5.2 Collapse / expand

- The whole sidebar **MAY** collapse to an icon rail and expand to labels; the
  state is a user preference and MUST persist per the product's preference rules.
- A **parent item with children** MAY expand/collapse its subtree. Expansion state
  for the active branch MUST follow the active route (§5.3).
- Collapsed (icon-only) items MUST retain an **accessible name** and a tooltip
  (localized), so meaning is never carried by icon alone (§8;
  `UI_GUIDELINES.md` §9).

### 5.3 Active state

- The engine computes **active** and **expanded** from the active route using each
  item's active-match prefix (§3.1). Exactly one leaf is active; its ancestors are
  marked expanded/current.
- Active state MUST NOT rely on color alone — it MUST pair with a non-color
  indicator (weight, marker, `aria-current`) to satisfy contrast and
  color-independence (`UI_GUIDELINES.md` §9).
- Deep-linking or a context switch re-derives active state from scratch
  (`NAVIGATION_ARCHITECTURE.md` §3.2, §7).

---

## 6. Badges & Counts

- An item shows a badge only if its descriptor declares **Badge Support** (§3.1).
  The badge value comes from a **lazy provider** resolved after first render so it
  never blocks the page (`UI_GUIDELINES.md` §10).
- A badge provider is gated by the **same** three-way AND as its item: if the item
  is hidden, its provider is never called; if the data is empty, the badge is
  omitted (not shown as zero unless the descriptor opts into a zero style).
- Badge data is **tenant-scoped** and context-scoped: counts reflect only the
  active workspace's data (e.g. open Jobs, pending Interviews, unread
  Notifications), never another tenant's (`SECURITY_MATRIX.md` §1.3).
- Badge numbers are localized (locale digits/format, §7.4) and have an accessible
  text equivalent (e.g. "3 pending interviews"), announced on change (§8).

---

## 7. Context-Aware Rendering (Platform vs Workspace)

The **same** sidebar component renders both contexts; the engine supplies a
different item set per resolved context (`NAVIGATION_ARCHITECTURE.md` §3). The
sidebar MUST make the active context unmistakable and MUST NOT leak items, badges,
or branding across contexts.

### 7.1 Workspace Context sidebar

- Items are the Workspace-Context screen tree (`NAVIGATION_MAP.md` §4), each gated
  by **permission AND subscription AND enabled module** (§4). Representative items
  and their exact visibility keys (`SECURITY_MATRIX.md` §3):

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

- The sidebar reflects the **active workspace's branding** (logo/colors) via tokens
  resolved at render, without forking the component; one workspace's brand MUST NOT
  appear in another (`UI_GUIDELINES.md` §5; `WORKSPACE_MODEL.md` §4).

### 7.2 Platform Context sidebar

- Items are the Platform-Context screen tree (`NAVIGATION_MAP.md` §3), each gated by
  a `system.*` key (and any feature flag). Representative items
  (`SECURITY_MATRIX.md` §2):

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

- Platform items are **not** workspace-subscription gated; visibility is the
  `system.*` permission (plus any declared flag), per
  `NAVIGATION_ARCHITECTURE.md` §5.

### 7.3 Switching

A workspace or context switch re-derives the whole sidebar from the new context's
inputs; nothing from the prior context persists into the new one
(`NAVIGATION_ARCHITECTURE.md` §3.2).

### 7.4 Direction & localization in the sidebar

- The sidebar mirrors fully under **RTL** (Arabic) and renders normally under
  **LTR** (English) from one direction-agnostic view-model, using logical
  start/end — never hard left/right (`UI_GUIDELINES.md` §6). Collapse handles,
  expand chevrons, and directional icons mirror.
- All titles, tooltips, section headers, keywords, and badge accessible-text are
  localized AR/EN; a literal string in the sidebar is a defect
  (`UI_GUIDELINES.md` §6). AR and EN are peers.

---

## 8. Accessibility of the Sidebar

The sidebar MUST meet the Phase-1 accessibility baseline (`UI_GUIDELINES.md` §9),
and specifically:

- **Landmark & structure.** The sidebar is a navigation landmark with an
  accessible, localized name; sections are programmatically grouped and labeled.
- **Keyboard navigation.** Every item is reachable and operable by keyboard alone
  in a logical order, with no keyboard traps. The sidebar SHOULD use a roving
  tabstop with arrow-key movement; **Enter/Space** activates; expandable parents
  toggle with the standard expand/collapse keys and expose `aria-expanded`.
- **Current item.** The active item exposes `aria-current` (e.g. `page`), not color
  alone (§5.3).
- **Icon-only safety.** Collapsed icon items expose an accessible name (and
  tooltip); meaning is never icon-only (§5.2).
- **Badges announced.** Badge counts have a text equivalent and changes are
  announced to assistive technology (§6).
- **Focus management.** Focus is visible; if the sidebar is a drawer on small
  screens, opening moves focus in and closing restores it.
- **Direction-aware a11y.** All of the above hold equally in AR/RTL and EN/LTR;
  arrow-key "next" follows reading order per direction (§7.4).

---

## 9. "No Item Without a Reason" (binding)

Every sidebar item MUST justify its existence; speculative or decorative entries
are defects.

- **A reason = a mapped screen + a gate + a doc reference.** Each item MUST carry a
  **Route** to a real screen in `NAVIGATION_MAP.md`, a **Permission** key from
  `PERMISSION_CATALOG.md`, and a **Documentation Reference** (§3.1). An item that
  cannot cite all three MUST NOT exist.
- **No orphan items.** An item MUST NOT point to a non-existent or undocumented
  screen, and MUST NOT exist "for layout." Header-only parents with no reachable
  target are pruned (§4, step 3).
- **No duplicate paths.** The same destination MUST NOT appear as two competing
  items; lateral relationships are expressed as cross-links on screens, not as
  extra sidebar entries (`NAVIGATION_MAP.md` §6).
- **Additive, manifest-driven.** New items arrive only via module manifests and are
  gated by the three-way AND (`NAVIGATION_ARCHITECTURE.md` §9); the sidebar
  component is never edited to add an entry.
- **Visibility ≠ authorization.** Even a fully justified, visible item is still
  authorized server-side on navigation (`SECURITY_MATRIX.md` §1.1).

---

## 10. Self-Review Checklist (Phase 5 sidebar gate)

This model is conformant only if **all** hold:

- [ ] **One sidebar, generated, never per-role** (§1).
- [ ] **Every item carries all required attributes** — Title, Icon, Permission,
      Route, Badge Support, Children, Search Keywords, Documentation Reference
      (§3); malformed descriptors are rejected.
- [ ] **Filter → group → order** runs the three-way AND as the only visibility
      decision, deny-by-default, deterministic (§4).
- [ ] **Sections, collapse/expand, active state** are declared, stable, and
      color-independent; active/expanded derive from the route (§5).
- [ ] **Badges** are declared, lazy, tenant-scoped, localized, and announced (§6).
- [ ] **Context-aware rendering** uses exact keys per context; workspace branding
      applies without forking; no cross-context leakage (§7).
- [ ] **Accessible** — landmark, keyboard, `aria-current`/`aria-expanded`,
      icon-only names, announced badges, direction-aware (§8).
- [ ] **No item without a reason** — every item maps to a screen, a key, and a doc
      reference; no orphans, no duplicate paths (§9).
- [ ] **Bilingual & bidirectional** — AR/EN parity, logical start/end, full RTL/LTR
      mirroring (§7.4).
- [ ] **Defers to canon** — screen names from `NAVIGATION_MAP.md`, keys from
      `PERMISSION_CATALOG.md`, engine from `NAVIGATION_ARCHITECTURE.md`.

---

### Related Documents

`NAVIGATION_ARCHITECTURE.md` · `NAVIGATION_MAP.md` · `UI_GUIDELINES.md` ·
`PERMISSION_CATALOG.md` · `PERMISSION_MODEL.md` · `SECURITY_MATRIX.md` ·
`WORKSPACE_MODEL.md` · `USER_MODEL.md` · `MODULES.md` ·
`SCREEN_CATALOG.md` · `SCREEN_RELATIONSHIPS.md` (Phase 5)
