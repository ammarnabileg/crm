# NAVIGATION ARCHITECTURE — HaHireAI

> **Status:** Adopted (Phase 5) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `NAVIGATION_MAP.md`, `UI_GUIDELINES.md`, `PERMISSION_CATALOG.md`.

---

## 0. About This Document

This document specifies the **navigation architecture** of HaHireAI: the single
engine that turns *who is signed in, where they are, what they may do, what they
pay for, and what is turned on* into the navigation the user actually sees. It is
the authoritative design for the **dynamic navigation engine**, the **Platform /
Workspace contexts**, **route → screen** resolution, **deep-linking and
breadcrumbs**, the **command palette / global search** entry, and **RTL/LTR**
direction handling.

It is a **paper design**. No HTML, CSS, or JavaScript is delivered here, and none
should be inferred as canonical. The screen inventory and the parent→child trees
are owned by `NAVIGATION_MAP.md`; the binding UI principles are owned by
`UI_GUIDELINES.md`; the permission keys are owned by `PERMISSION_CATALOG.md`.
Where this document summarizes those, **they govern** — if they ever disagree,
this file is the one that is corrected. Interpretation keywords (**MUST**,
**MUST NOT**, **SHOULD**, **SHOULD NOT**, **MAY**) follow RFC 2119, exactly as in
the Constitution.

The companion document `SIDEBAR_MODEL.md` specifies the sidebar item descriptor
and the rendering of the one sidebar; this document specifies the engine and the
routing/navigation system around it.

---

## 1. Principles (binding)

1. **Navigation is computed, never authored.** There is no hand-maintained menu
   anywhere in the product. Navigation is a **pure function** of a small set of
   inputs (§2). The same function, given the same inputs, MUST always produce the
   same navigation.
2. **One dynamically generated sidebar.** There is exactly **one** sidebar, and
   it is generated at runtime. There is **NO** per-role sidebar, and the engine
   MUST NOT branch on a role name anywhere — not in routing, not in view-models,
   not in templates (`UI_GUIDELINES.md` §2; `PERMISSION_MODEL.md` Invariant 1).
3. **One coherent product.** Navigation follows the design philosophy of Slack,
   Notion, Linear, GitHub, and the Stripe Dashboard: a single, consistent
   navigation grammar across every module, so a user cannot tell where one module
   ends and another begins (`UI_GUIDELINES.md` §1).
4. **Two contexts, one engine.** Navigation resolves into either the **Platform
   Context** or the **Workspace Context** (§3). Both are served by the **same**
   engine; they differ only in the inputs supplied and therefore the entries
   emitted (`NAVIGATION_MAP.md` §2).
5. **Gated visibility is three-way AND.** A navigation entry, screen, or
   drill-down link is *visible* only when **permission AND subscription AND
   enabled module** all allow it (§5). Visibility is a UX convenience and is
   **never** a substitute for server-side authorization (§6; `SECURITY_MATRIX.md`
   §1.1).
6. **Deny-by-default.** Absence of an explicit grant means hidden, then denied.
   Nothing is reachable unless something deliberately opens it
   (`SECURITY_MATRIX.md` §1.2).
7. **Additive growth.** A new module contributes its navigation through its
   manifest; the navigation engine itself is **never** modified to add a feature
   (§9; `MODULES.md` §6).
8. **Bilingual, bidirectional.** Navigation is bilingual AR/EN with full RTL/LTR
   support from day one; direction is a first-class property of the engine, not a
   bolt-on (§10; `UI_GUIDELINES.md` §6).

---

## 2. The Inputs (what the engine reads)

The navigation engine composes navigation from **five inputs** plus the resolved
context. These are exactly the inputs named in `UI_GUIDELINES.md` §2 and
`WORKSPACE_MODEL.md` §7; this document MUST NOT add inputs beyond them.

| # | Input | Source of truth | Role in navigation |
|---|---|---|---|
| 1 | **Current Context** | Session (active context) | Selects the Platform vs Workspace navigation universe (§3). |
| 2 | **Current Workspace** | Active `Membership` / session | The active tenant whose data, branding, and modules scope the Workspace Context. |
| 3 | **Permissions** | Effective keys for this `User` here | Per-entry `*.view`/`*.use` visibility gate (`PERMISSION_CATALOG.md`). |
| 4 | **Subscription** | Subscriptions/Licensing for the workspace | Entitlement gate — a capability not in the plan is not shown. |
| 5 | **Enabled Modules** | Module Registry × workspace config | Module gate — a disabled module contributes nothing. |

Supporting (non-gating) inputs the engine MAY read for *rendering* only — never
for visibility decisions:

- **Active locale & direction** (AR/EN, RTL/LTR) — for labels and mirroring (§10).
- **Badge/count providers** — per-entry counts (e.g. pending interviews), resolved
  lazily and gated by the same three-way AND as the entry itself
  (`SIDEBAR_MODEL.md`).
- **Active route** — to compute the active/expanded state and breadcrumbs (§4, §7).

> The current `User` (the single identity, `USER_MODEL.md` §1) is implicit in
> every input above: permissions, the active membership, and the active context
> are all resolved for that one signed-in identity. There is exactly one login.

---

## 3. Platform Context vs Workspace Context

HaHireAI presents **two operating contexts**, both served by one engine and one
shell (`NAVIGATION_MAP.md` §2; `UI_GUIDELINES.md` §3).

| | **Platform Context** | **Workspace Context** |
|---|---|---|
| **Who operates here** | System Owners (`system.*`) | Members of the active workspace |
| **Scope** | The platform itself (global data) | Exactly one active workspace (tenant-isolated) |
| **Nav universe** | `NAVIGATION_MAP.md` §3 screen tree | `NAVIGATION_MAP.md` §4 screen tree |
| **Visibility inputs** | `system.*` permissions + platform modules | permission **AND** subscription **AND** enabled module |
| **Data visibility** | Platform-level + system audit | Strictly that workspace's data |
| **Entry gate** | `system.dashboard.view` (`SECURITY_MATRIX.md` §2) | Active `Membership` + `workspace.view` |

### 3.1 Context resolution

```
On each request, the engine resolves the active context:

  session.activeContext == "platform"
      AND user holds system.dashboard.view   ──▶ PLATFORM CONTEXT
                                                  (else: context not offered)

  session.activeContext == "workspace"
      AND user has Membership in activeWorkspace ──▶ WORKSPACE CONTEXT
                                                     (else: re-resolve / chooser)

  No active workspace and no system.* ──▶ post-registration chooser:
                                          Create Workspace · Join Workspace
                                          (USER_MODEL.md §5)
```

The same `User`, with one login, MAY reach both contexts; data **never** crosses
the tenant boundary, and a System Owner reaching into a tenant is an explicit,
permission-gated, audited bypass (`SECURITY_MATRIX.md` §1.3).

### 3.2 Switching contexts

- A **workspace switcher** MUST let the user move between their joined workspaces
  and, if they hold `system.*`, into the Platform Context — all without a second
  login (`WORKSPACE_MODEL.md` §7; `USER_MODEL.md` §4).
- Switching context or workspace MUST **re-derive the entire navigation** from the
  new context's inputs (§2). The engine recomputes from scratch; it MUST NOT
  retain or merge entries from the prior context.
- UI, data, branding, badges, and breadcrumbs from one context MUST NOT **leak**
  into another (`UI_GUIDELINES.md` §3). The active context MUST be unmistakable in
  the shell at all times.
- A switch MUST land on a safe default screen for the new context (Platform
  **Overview**, or the workspace **Dashboard**) rather than attempting to carry a
  context-specific deep link across the boundary (§7.3).

---

## 4. From Inputs to Navigation — the Generation Pipeline

The engine runs a deterministic pipeline on every navigation render. Each stage
is pure and side-effect-free except the final emit.

```
                         NAVIGATION GENERATION PIPELINE
                         (one engine · both contexts)

   ┌───────────────────────────────────────────────────────────────┐
   │ INPUTS                                                         │
   │   Current Context · Current Workspace · Permissions ·         │
   │   Subscription · Enabled Modules        (+ locale/direction)  │
   └───────────────────────────────┬───────────────────────────────┘
                                   │
                                   ▼
   ┌───────────────────────────────────────────────────────────────┐
   │ (1) RESOLVE CONTEXT                                            │
   │     platform? workspace? → pick the navigation universe        │
   └───────────────────────────────┬───────────────────────────────┘
                                   ▼
   ┌───────────────────────────────────────────────────────────────┐
   │ (2) COLLECT CANDIDATES                                         │
   │     gather declared nav items from the Module Registry         │
   │     for the resolved context (manifests, never hard-coded)     │
   └───────────────────────────────┬───────────────────────────────┘
                                   ▼
   ┌───────────────────────────────────────────────────────────────┐
   │ (3) GATE — three-way AND, deny-by-default                      │
   │       keep item IFF:                                           │
   │          hasPermission(item.permission)                       │
   │       AND moduleEnabled(item.module)                          │
   │       AND subscriptionAllows(item.module/feature)             │
   │     (a parent with no visible children is dropped)             │
   └───────────────────────────────┬───────────────────────────────┘
                                   ▼
   ┌───────────────────────────────────────────────────────────────┐
   │ (4) GROUP & ORDER                                              │
   │     place kept items into sections; apply declared order;      │
   │     stable, deterministic sort (SIDEBAR_MODEL.md)             │
   └───────────────────────────────┬───────────────────────────────┘
                                   ▼
   ┌───────────────────────────────────────────────────────────────┐
   │ (5) DECORATE                                                   │
   │     localize labels (AR/EN) · resolve direction (RTL/LTR) ·    │
   │     attach lazy badge providers · compute active/expanded      │
   │     from the active route                                      │
   └───────────────────────────────┬───────────────────────────────┘
                                   ▼
   ┌───────────────────────────────────────────────────────────────┐
   │ (6) EMIT                                                       │
   │     the ONE sidebar view-model + breadcrumb + palette index    │
   │     (rendered server-side; SIDEBAR_MODEL.md renders it)       │
   └───────────────────────────────────────────────────────────────┘
```

Properties the pipeline MUST guarantee:

- **Determinism.** Same inputs ⇒ identical output. No randomness, no time-of-day
  branching, no role-name branching.
- **Purity of gating.** Stage (3) is the *only* place visibility is decided, and
  it decides with the three-way AND only.
- **Empty-safe.** Any stage MAY yield zero items; the shell still renders (the
  sidebar shows only the always-present entries it is entitled to, §8).
- **Cheap recomputation.** The pipeline is inexpensive enough to run per request
  and on every context switch; expensive per-entry data (badges) is deferred to
  stage (5) as lazy providers, never blocking first render (`UI_GUIDELINES.md`
  §10).

---

## 5. Gated Visibility — permission AND subscription AND enabled module

Visibility is the question *"should this entry/screen/link appear?"* and is
decided by a **single three-way AND**, deny-by-default (`SECURITY_MATRIX.md`
§1.1; `UI_GUIDELINES.md` §2):

```
visible(item) :=
        hasPermission(user, item.permission, context)   // PERMISSION_CATALOG.md key
   AND  moduleEnabled(workspace, item.module)           // MODULES.md registry × config
   AND  subscriptionAllows(workspace, item.module)      // Subscriptions / Licensing
```

- **Permission** uses the **exact** `*.view`/`*.use` key from
  `PERMISSION_CATALOG.md`. Screen visibility uses the read gate; action controls
  inside a screen use the verb key (e.g. `job.create`, `candidate.export`).
- **Enabled module** is the module that owns the entry per `MODULES.md`. A
  disabled module contributes nothing — its entire subtree disappears.
- **Subscription** is an entitlement precondition: a member MAY *hold*
  `interview.ai.run`, but if the plan excludes the AI Engine the entry stays
  hidden and the action stays unavailable (`SECURITY_MATRIX.md` §1.1).

The three checks are **independent and all required**. None substitutes for
another, and subscription/module gate **visibility**, never authorization (§6).

> **Platform Context** uses the same shape with one simplification: `system.*`
> keys are not subscription- or workspace-module-gated. A Platform entry is
> visible when its `system.*` permission is held (and any declared feature flag is
> on — e.g. **Developer Tools**, `SECURITY_MATRIX.md` §2).

---

## 6. Gated Visibility vs Authorization (two independent layers)

These are **two independent layers** that must *both* pass; conflating them is a
defect (`SECURITY_MATRIX.md` §1.1; `UI_GUIDELINES.md` §1).

| Layer | Question | Inputs | Where enforced | Failure |
|---|---|---|---|---|
| **Visibility** | Should it *appear*? | permission **AND** subscription **AND** module | Navigation engine (this doc) | Entry hidden / not rendered |
| **Authorization** | May this caller *execute / read*? | permission key(s) only | Application boundary, server-side | `AuthorizationException` → 403, audited |

Binding consequences:

- **Hiding is never enforcing.** A hidden-but-reachable route (typed URL, stale
  bookmark, API call) MUST still be denied server-side. The navigation engine's
  output is advisory to the human, authoritative to no one
  (`PERMISSION_MODEL.md` §5; `SECURITY_MATRIX.md` §1.1).
- **Every screen has a server gate.** Each route resolves to a use case that
  re-verifies the `*.view` permission before reading data; the engine merely
  decides whether to *advertise* the route.
- **No-permission state.** If navigation is bypassed and the server denies, the UI
  MUST render the **No-permission** screen state — a clear, non-leaking message,
  never the data and never a broken page (`UI_GUIDELINES.md` §8).
- **Tenant isolation is orthogonal and always on.** Even a permitted, visible
  entry only ever resolves data for the active `workspace_id`
  (`SECURITY_MATRIX.md` §1.3).

---

## 7. Routes, Screens, Deep-Linking & Breadcrumbs

### 7.1 Route → screen resolution

- Every screen in `NAVIGATION_MAP.md` (§3–§4) has a **stable route**, declared by
  its owning module's manifest (`MODULES.md` §6), not invented per page.
- Routes are **context-rooted**: Platform routes live under the platform root;
  Workspace routes are scoped to the active workspace. A route MUST carry enough
  to resolve its context unambiguously so a cold deep-link lands correctly (§7.3).
- Resolution order on every request: **resolve context (§3) → match route →
  authorize (§6) → render screen**. A route with no match renders a localized
  not-found within the current shell; an unauthorized match renders the
  No-permission state (§6).
- Nested screens map to nested routes. **Job Detail** and its tabs (Overview ·
  Applications · Pipeline · Interviews · Offers · Hiring Team · Settings) are
  routes *under* the Job, each independently permission-gated per
  `SECURITY_MATRIX.md` §3.2.

### 7.2 Breadcrumbs

- Breadcrumbs are **derived from the screen tree**, not stored per page. They
  mirror the parent→child drill-down of `NAVIGATION_MAP.md` §5 — e.g.
  `Recruitment ▸ Jobs ▸ Job Detail ▸ Applications ▸ Candidate Profile`.
- Each crumb is a real, **permission-gated** link: a crumb the user may not view
  renders as non-navigable text, never as a leaking link (§6).
- Breadcrumbs respect lateral relationships for *return* paths but express the
  canonical parent chain for *position*; cross-links (§8) are navigation, not
  crumbs.
- Crumbs are localized and **direction-aware**: the separator/chevron mirrors
  under RTL (§10).

### 7.3 Deep-linking

- Every screen is **directly addressable** by URL. Opening a deep link MUST run
  the full pipeline: resolve context, set the active workspace if the link
  implies one the user may access, authorize, then render.
- A deep link into a workspace the user can access MUST **switch the active
  workspace** to it (then re-derive navigation, §3.2). A deep link into a
  workspace the user cannot access resolves to the No-permission state — never a
  cross-tenant leak (`SECURITY_MATRIX.md` §1.3).
- A deep link whose target is hidden by subscription/module (but otherwise
  permitted) resolves to an entitlement-aware message (e.g. capability not in
  plan), consistent with the visibility gate (§5), not a raw 404.
- Deep links are the substrate for **Notifications** and **Search** results: each
  points at the precise entity that raised it (`NAVIGATION_MAP.md` §6) and is
  re-authorized on arrival.

---

## 8. Global Entries — Command Palette & Search, Notifications, Cross-Links

### 8.1 Command palette / global search

- The shell MUST provide a **global search / command palette** entry, present in
  the top bar of the Workspace Context as the unified search affordance
  (`NAVIGATION_MAP.md` §4: **Search**, top-bar, always present), gated by
  `search.use` (`SECURITY_MATRIX.md` §3.11).
- The palette MAY surface two result classes from one entry, in the Slack/Linear
  manner:
  - **Navigation targets** — screens the user is *entitled to see*. The palette
    indexes **only** the entries the generation pipeline already emitted (§4); it
    MUST NOT advertise a screen the three-way AND hid (§5).
  - **Entity results** — Jobs, Applications, Candidate Profiles, Interviews,
    Offers, Files, Members within the active workspace, via the Search module.
- Opening any result is a deep-link (§7.3) and is **re-authorized** server-side on
  arrival; the palette never bypasses §6. Results never cross tenants
  (`SECURITY_MATRIX.md` §3.11).
- The palette MUST be keyboard-first (open, type, arrow, enter) and fully
  accessible and localized (§10; `UI_GUIDELINES.md` §9).

### 8.2 Always-present top-bar entries

**Search** and **Notifications** are top-bar entries present across the Workspace
Context (`NAVIGATION_MAP.md` §4), gated by `search.use` and `notifications.view`
respectively. They are part of the shell, not the sidebar, but are produced by the
same context resolution and the same gates.

### 8.3 Cross-links (lateral navigation)

Lateral relationships (`NAVIGATION_MAP.md` §6) — e.g. Candidate Profile ↔
Applications ↔ Job, Interview → Application → Job, Notification → originating
entity, a metric → its underlying Jobs/Applications — are **navigation produced by
relationships**, not a separate menu. Each lateral link renders only if its target
passes the same three-way AND (§5) and is re-authorized on click (§6), and only
within the active workspace's data.

---

## 9. Adding a Module Adds Navigation, Not a Redesign

Navigation is **generated from module manifests**; it is never hand-maintained
(`NAVIGATION_MAP.md` §8; `MODULES.md` §6; `WORKSPACE_MODEL.md` §8 invariant 5).

```
New module ships module.php ─┬─ declares permissions ─┐
                            ├─ declares routes       ├─▶ Module Registry
                            ├─ declares enabledBy     │       │
                            └─ declares nav entries ──┘       ▼
                                                       Engine COLLECTS (stage 2)
                                                       and GATES (stage 3):
                                                       appears IFF permission
                                                       AND subscription AND
                                                       module enabled
```

Binding consequences:

- Adding a capability is **additive**: a new module contributes its routes,
  permissions, and nav entries through its manifest. The **navigation**,
  **permission**, and **database** engines are **not** modified (`MODULES.md` §7;
  `WORKSPACE_MODEL.md` §8).
- The single sidebar **absorbs** the new entries automatically for users who pass
  all three gates; everyone else never sees them.
- Because the workspace platform is **domain-agnostic** (`WORKSPACE_MODEL.md` §1),
  a future business domain (e.g. HR, CRM) appears as a new top-level bounded-
  context node **exactly as Recruitment does today** — with no change to this
  architecture, the pipeline, or the screen-tree structure.
- A module MUST declare, for each nav entry, its required permission key, owning
  module, route, and `enabledBy` precondition, so the engine can gate it without
  special-casing (`SIDEBAR_MODEL.md`). **No item without a reason.**

---

## 10. RTL/LTR & Bilingual Direction Handling

Direction is a **first-class property** of the navigation engine, not a late
translation layer (`UI_GUIDELINES.md` §6; Constitution §3, §12).

- **Document direction.** The shell sets `dir` and `lang` from the active language;
  the navigation re-renders **mirrored** for RTL (Arabic) and normal for LTR
  (English) from the *same* generated view-model. The engine emits one
  direction-agnostic structure; the renderer resolves direction.
- **Logical, not physical.** Navigation layout MUST use logical start/end
  (inline-start, inline-end), never hard left/right, so the **single** sidebar,
  drawers, breadcrumb chevrons, expand/collapse affordances, and directional icons
  all mirror correctly under RTL (`UI_GUIDELINES.md` §6).
- **Localized labels.** Every entry label, section title, breadcrumb crumb, and
  palette hint comes from the AR/EN catalogs (`/resources/lang/ar`,
  `/resources/lang/en`). A literal string in navigation is a defect
  (`UI_GUIDELINES.md` §6). AR and EN are peers — neither is a fallback.
- **Localized data in nav.** Badge counts, dates, and numbers shown in navigation
  are formatted per the active locale and **workspace settings** (timezone,
  language, currency, date format — `WORKSPACE_MODEL.md` §4).
- **Direction-aware a11y.** Keyboard order and focus management hold equally in
  AR/RTL and EN/LTR: "next" follows reading order in each direction (§11;
  `UI_GUIDELINES.md` §9).

---

## 11. Accessibility & Performance of Navigation

- **Keyboard-operable.** The whole navigation system — sidebar, switcher,
  breadcrumbs, palette, top-bar entries — MUST be reachable and operable by
  keyboard alone, in a logical order, with no keyboard traps
  (`UI_GUIDELINES.md` §9). Detailed ARIA/roving-focus for the sidebar is specified
  in `SIDEBAR_MODEL.md`.
- **Focus management.** Opening the palette or a drawer moves focus in and
  restores it on close (`UI_GUIDELINES.md` §9).
- **Announced changes.** Context switches and async badge updates are announced to
  assistive technology rather than changing silently (`UI_GUIDELINES.md` §9).
- **Fast first render.** Navigation is **server-rendered first**; the pipeline is
  cheap and badges are lazy so navigation never blocks the page (`UI_GUIDELINES.md`
  §4, §10). The engine MUST NOT introduce client work that undermines the
  p95 < 300 ms server budget (Constitution §11).
- **Six screen states reachable through nav.** Every screen navigation points to
  MUST handle empty, loading, no-permission, error, success, and offline states
  (`UI_GUIDELINES.md` §8); navigation never lands the user on a blank or broken
  page.

---

## 12. Self-Review Checklist (Phase 5 navigation gate)

This architecture is conformant only if **all** hold:

- [ ] **Computed, not authored.** Navigation is a pure function of Current
      Context, Current Workspace, Permissions, Subscription, Enabled Modules — no
      hand-maintained menu (§2, §4).
- [ ] **One sidebar, no roles.** Exactly one dynamically generated sidebar; no
      per-role navigation and no branch on a role name anywhere (§1).
- [ ] **Three-way AND visibility.** Every entry/screen/link is gated by
      permission **AND** subscription **AND** enabled module, deny-by-default (§5).
- [ ] **Visibility ≠ authorization.** Hiding never enforces; every route is
      re-authorized server-side; cross-tenant access never leaks (§6).
- [ ] **Routes, deep-links, breadcrumbs.** Stable routes resolve to screens;
      deep-links set context and re-authorize; breadcrumbs derive from the screen
      tree and are permission-gated (§7).
- [ ] **Palette/search entitled-only.** The command palette/global search indexes
      only entitled targets and re-authorizes on open; results never cross tenants
      (§8).
- [ ] **Additive modules.** A new module adds navigation via its manifest with no
      engine change; a future domain appears like Recruitment (§9).
- [ ] **Bilingual & bidirectional.** AR/EN parity, logical start/end, full RTL/LTR
      mirroring of the nav, localized labels and formats (§10).
- [ ] **Accessible & fast.** Keyboard-operable, focus-managed, announced, server-
      rendered-first, lazy badges (§11).
- [ ] **Defers to canon.** Screen names match `NAVIGATION_MAP.md`; keys match
      `PERMISSION_CATALOG.md`; principles match `UI_GUIDELINES.md`.

---

### Related Documents

`NAVIGATION_MAP.md` · `SIDEBAR_MODEL.md` · `UI_GUIDELINES.md` ·
`PERMISSION_CATALOG.md` · `PERMISSION_MODEL.md` · `SECURITY_MATRIX.md` ·
`WORKSPACE_MODEL.md` · `USER_MODEL.md` · `MODULES.md` · `SYSTEM_OVERVIEW.md` ·
`SCREEN_CATALOG.md` · `SCREEN_RELATIONSHIPS.md` (Phase 5)
