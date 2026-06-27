# NAVIGATION ARCHITECTURE — HaHireAI

> **Status:** Adopted (Phase 5) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `NAVIGATION_MAP.md`, `UI_GUIDELINES.md`, `PERMISSION_CATALOG.md`.

---

## 0. About This Document

This document specifies the **navigation architecture** of HaHireAI: the single
engine that turns *who is signed in, where they are, what they may do, what they
pay for, and what is turned on* into the navigation the user sees. It is the
authoritative design for the **dynamic navigation engine**, the **Platform /
Workspace contexts** and switching, **route → screen** resolution,
**deep-linking and breadcrumbs**, the **command palette / global search** entry,
**gated visibility vs authorization**, and **RTL/LTR** direction handling.

It is a **paper design**: no HTML, CSS, or JavaScript, and none should be inferred
as canonical. The screen inventory and parent→child trees are owned by
`NAVIGATION_MAP.md`; the binding UI principles by `UI_GUIDELINES.md`; the
permission keys by `PERMISSION_CATALOG.md` — where this document summarizes them,
**they govern**. The companion `SIDEBAR_MODEL.md` specifies the sidebar item
descriptor and rendering. Interpretation keywords (**MUST**, **MUST NOT**,
**SHOULD**, **SHOULD NOT**, **MAY**) follow RFC 2119.

---

## 1. Principles (binding)

1. **Computed, never authored.** Navigation is a **pure function** of a small set
   of inputs (§2); there is no hand-maintained menu. Same inputs ⇒ same nav.
2. **One dynamically generated sidebar.** Exactly **one** sidebar, generated at
   runtime. There is **NO** per-role navigation, and the engine MUST NOT branch on
   a role name anywhere (`UI_GUIDELINES.md` §2; `PERMISSION_MODEL.md` Invariant 1).
3. **One coherent product.** Navigation follows the grammar of Slack, Notion,
   Linear, GitHub, and the Stripe Dashboard — consistent across every module, so a
   user cannot tell where one module ends and another begins (`UI_GUIDELINES.md`
   §1).
4. **Two contexts, one engine.** Navigation resolves into the **Platform Context**
   or the **Workspace Context** (§3); both use the same engine and differ only in
   inputs and emitted entries (`NAVIGATION_MAP.md` §2).
5. **Gated visibility is three-way AND.** An entry/screen/link is *visible* only
   when **permission AND subscription AND enabled module** all allow it (§5).
   Visibility is never a substitute for server-side authorization (§6).
6. **Deny-by-default.** Absence of a grant means hidden, then denied
   (`SECURITY_MATRIX.md` §1.2).
7. **Additive growth.** A new module contributes navigation via its manifest; the
   engine is never modified to add a feature (§9; `MODULES.md` §6).
8. **Bilingual, bidirectional.** AR/EN with full RTL/LTR from day one; direction is
   a first-class property of the engine (§10; `UI_GUIDELINES.md` §6).

---

## 2. The Inputs (what the engine reads)

Navigation is composed from **five inputs** plus the resolved context — exactly the
inputs named in `UI_GUIDELINES.md` §2 and `WORKSPACE_MODEL.md` §7. The engine MUST
NOT add gating inputs beyond these.

| # | Input | Source of truth | Role in navigation |
|---|---|---|---|
| 1 | **Current Context** | Session (active context) | Selects the Platform vs Workspace nav universe (§3). |
| 2 | **Current Workspace** | Active `Membership` / session | The active tenant whose data, branding, modules scope the Workspace Context. |
| 3 | **Permissions** | Effective keys for this `User` here | Per-entry `*.view`/`*.use` visibility gate (`PERMISSION_CATALOG.md`). |
| 4 | **Subscription** | Subscriptions/Licensing for the workspace | Entitlement gate — a capability not in the plan is not shown. |
| 5 | **Enabled Modules** | Module Registry × workspace config | Module gate — a disabled module contributes nothing. |

Non-gating inputs read for **rendering only** (never for visibility): active
**locale & direction** (§10), **badge providers** (lazy, gated like their entry),
and the **active route** (active state, breadcrumbs). The current `User` (the
single identity, `USER_MODEL.md` §1) is implicit in every input — one login.

---

## 3. Platform Context vs Workspace Context

Two operating contexts, one engine and one shell (`NAVIGATION_MAP.md` §2;
`UI_GUIDELINES.md` §3).

| | **Platform Context** | **Workspace Context** |
|---|---|---|
| **Who operates here** | System Owners (`system.*`) | Members of the active workspace |
| **Scope** | The platform itself (global data) | Exactly one active workspace (tenant-isolated) |
| **Nav universe** | `NAVIGATION_MAP.md` §3 tree | `NAVIGATION_MAP.md` §4 tree |
| **Visibility inputs** | `system.*` permissions + platform modules | permission **AND** subscription **AND** module |
| **Entry gate** | `system.dashboard.view` | Active `Membership` + `workspace.view` |

### 3.1 Context resolution & switching

```
On each request the engine resolves context:
  activeContext = platform  AND system.dashboard.view ──▶ PLATFORM CONTEXT
  activeContext = workspace AND Membership(activeWs)   ──▶ WORKSPACE CONTEXT
  no workspace AND no system.*  ──▶ chooser: Create Workspace · Join Workspace
```

- A **workspace switcher** MUST let the user move between joined workspaces and, if
  granted `system.*`, into the Platform Context — all with **one login**
  (`WORKSPACE_MODEL.md` §7; `USER_MODEL.md` §4).
- Switching MUST **re-derive the entire navigation** from the new context's inputs;
  the engine recomputes from scratch and MUST NOT merge entries from the prior
  context. UI, data, branding, badges, and breadcrumbs MUST NOT **leak** across
  contexts; the active context MUST be unmistakable.
- A switch lands on a safe default (Platform **Overview** or workspace
  **Dashboard**) rather than carrying a context-specific deep link across the
  boundary (§7).
- The same `User` reaches both contexts; data **never** crosses tenants, and a
  System Owner reaching into a tenant is an explicit, audited bypass
  (`SECURITY_MATRIX.md` §1.3).

---

## 4. From Inputs to Navigation — the Generation Pipeline

The engine runs a deterministic, side-effect-free pipeline on every render.

```
                  NAVIGATION GENERATION PIPELINE (one engine · both contexts)

   ┌──────────────────────────────────────────────────────────────┐
   │ INPUTS: Current Context · Current Workspace · Permissions ·    │
   │         Subscription · Enabled Modules   (+ locale/direction)  │
   └───────────────────────────────┬──────────────────────────────┘
                                   ▼
   (1) RESOLVE CONTEXT   platform? workspace? → pick the nav universe
                                   │
                                   ▼
   (2) COLLECT CANDIDATES gather declared nav items from the Module
                          Registry for that context (manifests, not hard-coded)
                                   │
                                   ▼
   (3) GATE — three-way AND, deny-by-default     keep item IFF:
              hasPermission(item.permission) AND moduleEnabled(item.module)
              AND subscriptionAllows(item.module)   (parent w/ no kids dropped)
                                   │
                                   ▼
   (4) GROUP & ORDER     into sections; apply declared order; stable sort
                                   │
                                   ▼
   (5) DECORATE          localize (AR/EN) · resolve direction (RTL/LTR) ·
                         attach lazy badges · compute active/expanded from route
                                   │
                                   ▼
   (6) EMIT              the ONE sidebar view-model + breadcrumb + palette index
                         (server-rendered; SIDEBAR_MODEL.md renders it)
```

Guarantees: **determinism** (no randomness, no role-name branching); **single
gate** — stage (3) is the only place visibility is decided, with the three-way AND
only; **empty-safe** — any stage may yield zero items and the shell still renders;
**cheap** — runs per request and per switch, with badges deferred to stage (5) so
they never block first render (`UI_GUIDELINES.md` §10).

---

## 5. Gated Visibility — permission AND subscription AND enabled module

Visibility answers *"should this entry/screen/link appear?"* via a **single
three-way AND**, deny-by-default (`SECURITY_MATRIX.md` §1.1; `UI_GUIDELINES.md`
§2):

```
visible(item) :=
        hasPermission(user, item.permission, context)   // exact PERMISSION_CATALOG.md key
   AND  moduleEnabled(workspace, item.module)           // MODULES.md registry × config
   AND  subscriptionAllows(workspace, item.module)      // Subscriptions / Licensing
```

- **Permission** uses the exact `*.view`/`*.use` key from `PERMISSION_CATALOG.md`;
  screen visibility uses the read gate, while action controls inside a screen use
  the verb key (e.g. `job.create`, `candidate.export`).
- **Enabled module** is the owning module per `MODULES.md`; a disabled module's
  whole subtree disappears.
- **Subscription** is an entitlement precondition: a member MAY *hold*
  `interview.ai.run`, but if the plan excludes the AI Engine the entry stays hidden
  and the action unavailable (`SECURITY_MATRIX.md` §1.1).

The three checks are independent and all required; none substitutes for another,
and subscription/module gate **visibility**, never authorization (§6).

> **Platform Context** uses the same shape, simplified: `system.*` entries are not
> subscription- or workspace-module-gated. A Platform entry is visible when its
> `system.*` permission is held (and any declared feature flag is on — e.g.
> **Developer Tools**, `SECURITY_MATRIX.md` §2).

---

## 6. Gated Visibility vs Authorization (two independent layers)

Two layers that must *both* pass; conflating them is a defect
(`SECURITY_MATRIX.md` §1.1).

| Layer | Question | Inputs | Enforced | Failure |
|---|---|---|---|---|
| **Visibility** | Should it *appear*? | permission **AND** subscription **AND** module | Navigation engine (this doc) | Hidden / not rendered |
| **Authorization** | May this caller *execute/read*? | permission key(s) only | Application boundary, server-side | `AuthorizationException` → 403, audited |

- **Hiding is never enforcing.** A hidden-but-reachable route (typed URL, stale
  bookmark, API call) MUST still be denied server-side; the engine's output is
  advisory to the human, authoritative to no one (`PERMISSION_MODEL.md` §5).
- **Every screen has a server gate.** Each route resolves to a use case that
  re-verifies the `*.view` permission before reading data; the engine only decides
  whether to *advertise* the route.
- **No-permission state.** If navigation is bypassed and the server denies, the UI
  renders the **No-permission** state — a clear, non-leaking message, never the
  data and never a broken page (`UI_GUIDELINES.md` §8).
- **Tenant isolation is orthogonal and always on**: a visible, permitted entry
  still resolves data only for the active `workspace_id` (`SECURITY_MATRIX.md`
  §1.3).

---

## 7. Routes, Screens, Deep-Linking & Breadcrumbs

**Route → screen.** Every screen in `NAVIGATION_MAP.md` (§3–§4) has a **stable
route** declared by its owning module's manifest, not invented per page. Routes are
**context-rooted** (Platform routes under the platform root; Workspace routes
scoped to the active workspace) and carry enough to resolve their context. Per
request: **resolve context (§3) → match route → authorize (§6) → render**. No match
renders a localized not-found in the current shell; an unauthorized match renders
the No-permission state. Nested screens map to nested routes — **Job Detail** and
its tabs (Overview · Applications · Pipeline · Interviews · Offers · Hiring Team ·
Settings) are routes under the Job, each independently gated (`SECURITY_MATRIX.md`
§3.2).

**Breadcrumbs** are **derived from the screen tree**, not stored per page; they
mirror the drill-down of `NAVIGATION_MAP.md` §5 (e.g. `Recruitment ▸ Jobs ▸ Job
Detail ▸ Applications ▸ Candidate Profile`). Each crumb is a **permission-gated**
link: a crumb the user may not view renders as non-navigable text, never a leaking
link (§6). Crumbs are localized and direction-aware — the chevron mirrors under RTL
(§10).

**Deep-linking.** Every screen is directly addressable. Opening a deep link runs
the full pipeline: resolve context, set the active workspace if the link implies
one the user may access, authorize, render. A link into an accessible workspace MUST
**switch the active workspace** to it then re-derive navigation (§3.1); a link into
an inaccessible workspace resolves to No-permission — never a cross-tenant leak
(`SECURITY_MATRIX.md` §1.3). A link whose target is hidden by subscription/module
(but otherwise permitted) resolves to an entitlement-aware message, not a raw 404.
Deep links are the substrate for **Notifications** and **Search** results, each
pointing at the precise entity that raised it (`NAVIGATION_MAP.md` §6) and
re-authorized on arrival.

---

## 8. Global Entries — Command Palette / Search, and Cross-Links

**Command palette / global search.** The shell MUST provide a global search /
command palette — the unified **Search** affordance present in the Workspace
top-bar (`NAVIGATION_MAP.md` §4), gated by `search.use` (`SECURITY_MATRIX.md`
§3.11). In the Slack/Linear manner it MAY surface two result classes from one
entry:

- **Navigation targets** — screens the user is *entitled to see*. The palette
  indexes **only** entries the pipeline already emitted (§4); it MUST NOT advertise
  a screen the three-way AND hid (§5).
- **Entity results** — Jobs, Applications, Candidate Profiles, Interviews, Offers,
  Files, Members in the active workspace, via the Search module.

Opening any result is a deep-link (§7), **re-authorized** server-side on arrival;
results never cross tenants (`SECURITY_MATRIX.md` §3.11). The palette MUST be
keyboard-first and fully accessible and localized (§10–§11).

**Always-present top-bar.** **Search** (`search.use`) and **Notifications**
(`notifications.view`) are shell entries — not sidebar items — produced by the same
context resolution and gates (`NAVIGATION_MAP.md` §4).

**Cross-links.** Lateral relationships (`NAVIGATION_MAP.md` §6) — Candidate Profile
↔ Applications ↔ Job, Interview → Application → Job, Notification → originating
entity, a metric → its underlying Jobs/Applications — are **navigation produced by
relationships**, not a separate menu. Each renders only if its target passes the
three-way AND (§5) and is re-authorized on click (§6), within the active
workspace's data.

---

## 9. Adding a Module Adds Navigation, Not a Redesign

Navigation is **generated from module manifests**, never hand-maintained
(`NAVIGATION_MAP.md` §8; `MODULES.md` §6; `WORKSPACE_MODEL.md` §8 invariant 5).

```
New module ships module.php ─┬─ declares permissions ─┐
                            ├─ declares routes       ├─▶ Module Registry
                            ├─ declares enabledBy     │       │
                            └─ declares nav entries ──┘       ▼
                                                  Engine COLLECTS (stage 2) and
                                                  GATES (stage 3): appears IFF
                                                  permission AND subscription AND
                                                  module enabled
```

- Adding a capability is **additive**: a module contributes routes, permissions,
  and nav entries via its manifest; the navigation, permission, and database
  engines are **not** modified (`MODULES.md` §7; `WORKSPACE_MODEL.md` §8).
- The single sidebar **absorbs** new entries automatically for users who pass all
  three gates; everyone else never sees them.
- Because the workspace platform is **domain-agnostic** (`WORKSPACE_MODEL.md` §1), a
  future business domain (e.g. HR, CRM) appears as a new top-level bounded-context
  node **exactly as Recruitment does today** — no change to this architecture, the
  pipeline, or the screen-tree structure. **No item without a reason**
  (`SIDEBAR_MODEL.md` §9).

---

## 10. RTL/LTR & Bilingual Direction Handling

Direction is a **first-class property** of the engine, not a late translation layer
(`UI_GUIDELINES.md` §6; Constitution §3, §12).

- **Document direction.** The shell sets `dir`/`lang` from the active language; the
  navigation re-renders **mirrored** for RTL (Arabic) and normal for LTR (English)
  from the *same* generated view-model — the engine emits one direction-agnostic
  structure; the renderer resolves direction.
- **Logical, not physical.** Navigation uses logical start/end, never hard
  left/right, so the single sidebar, drawers, breadcrumb chevrons, expand/collapse
  affordances, and directional icons all mirror under RTL.
- **Localized labels & data.** Every label, section title, crumb, and palette hint
  comes from the AR/EN catalogs (`/resources/lang/{ar,en}`) — a literal string in
  navigation is a defect; AR/EN are peers. Badge counts, dates, and numbers follow
  the active locale and **workspace settings** (`WORKSPACE_MODEL.md` §4).
- **Direction-aware a11y.** Keyboard order and focus hold equally in AR/RTL and
  EN/LTR; "next" follows reading order per direction (§11).

---

## 11. Accessibility & Performance of Navigation

- **Keyboard-operable, focus-managed.** The whole system — sidebar, switcher,
  breadcrumbs, palette, top-bar — MUST be reachable and operable by keyboard alone
  in a logical order, no traps; opening the palette/drawer moves focus in and
  restores on close (`UI_GUIDELINES.md` §9). Sidebar ARIA detail lives in
  `SIDEBAR_MODEL.md` §8.
- **Announced changes.** Context switches and async badge updates are announced to
  assistive technology rather than changing silently.
- **Fast first render.** Navigation is **server-rendered first**; the pipeline is
  cheap and badges are lazy, so navigation never blocks the page and never
  undermines the p95 < 300 ms server budget (`UI_GUIDELINES.md` §4, §10;
  Constitution §11).
- **Six states reachable.** Every screen navigation points to handles empty,
  loading, no-permission, error, success, and offline states (`UI_GUIDELINES.md`
  §8); navigation never lands on a blank or broken page.

---

## 12. Self-Review Checklist (Phase 5 navigation gate)

Conformant only if **all** hold:

- [ ] **Computed, not authored** — pure function of Current Context, Current
      Workspace, Permissions, Subscription, Enabled Modules (§2, §4).
- [ ] **One sidebar, no roles** — exactly one generated sidebar; no per-role nav,
      no role-name branch anywhere (§1).
- [ ] **Three-way AND visibility** — permission **AND** subscription **AND** module,
      deny-by-default (§5).
- [ ] **Visibility ≠ authorization** — hiding never enforces; every route
      re-authorized server-side; no cross-tenant leak (§6).
- [ ] **Routes, deep-links, breadcrumbs** — stable routes; deep-links set context
      and re-authorize; breadcrumbs derive from the tree and are gated (§7).
- [ ] **Palette/search entitled-only** — indexes only entitled targets,
      re-authorizes on open, never crosses tenants (§8).
- [ ] **Additive modules** — a new module adds nav via manifest, no engine change;
      a future domain appears like Recruitment (§9).
- [ ] **Bilingual & bidirectional** — AR/EN parity, logical start/end, full RTL/LTR
      mirroring, localized labels/formats (§10).
- [ ] **Accessible & fast** — keyboard-operable, focus-managed, announced,
      server-rendered-first, lazy badges (§11).
- [ ] **Defers to canon** — screen names from `NAVIGATION_MAP.md`, keys from
      `PERMISSION_CATALOG.md`, principles from `UI_GUIDELINES.md`.

---

### Related Documents

`NAVIGATION_MAP.md` · `SIDEBAR_MODEL.md` · `UI_GUIDELINES.md` ·
`PERMISSION_CATALOG.md` · `PERMISSION_MODEL.md` · `SECURITY_MATRIX.md` ·
`WORKSPACE_MODEL.md` · `USER_MODEL.md` · `MODULES.md` · `SYSTEM_OVERVIEW.md` ·
`SCREEN_CATALOG.md` · `SCREEN_RELATIONSHIPS.md` (Phase 5)
