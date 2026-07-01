# SCREEN RELATIONSHIPS — HaHireAI

> **Status:** Adopted (Phase 5) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `NAVIGATION_MAP.md`, `SCREEN_CATALOG.md`.

---

## 0. About This Document

`NAVIGATION_MAP.md` answers *which* screens exist and *how* they nest. This
document goes one layer deeper: **how screens connect as a graph** — the
parent/child drill-downs, the lateral cross-links, the back-navigation and
breadcrumb model, the choice between a full page / modal / drawer / tab, and how
**context switching** reshapes what is on screen. It is about *relationships and
transitions*, not visual layout.

It **defers** to `NAVIGATION_MAP.md` (the authoritative screen tree and gates)
and `SCREEN_CATALOG.md` (the authoritative per-screen specification); where this
file disagrees with either, **they govern**. Screen, context, tab, and module
names are used **exactly** as in `NAVIGATION_MAP.md` and `MODULES.md`; permission
keys follow `PERMISSION_CATALOG.md` via `SECURITY_MATRIX.md`. Every relationship
below is **gated**: a link renders only if permission **AND** subscription **AND**
enabled module allow it, and only within the active workspace's data — no
cross-tenant traversal (`NAVIGATION_MAP.md` §6, `SECURITY_MATRIX.md` §1.1, §1.3).
Keywords follow RFC 2119.

**Legend.** `──▶` drill-down / forward navigation · `◀──▶` lateral cross-link
(both directions) · `▸` a tab within an entity · `⇧` opens in a drawer ·
`▣` opens in a modal · `«back»` back-navigation / breadcrumb hop.

---

## 1. The Relationship Graph (overview)

Screens form a **graph**, not a tree: a strict drill-down spine, plus lateral
cross-links between related entities, plus two global surfaces (Search,
Notifications) that jump *into* the graph from anywhere.

```
                         ┌───────────────────────────────┐
                         │   GLOBAL TOP BAR (every screen)│
                         │  Search · Notifications ·       │
                         │  Context / Workspace switcher   │
                         └───────────┬───────────┬─────────┘
                            jumps to │           │ jumps to
                                     ▼           ▼
   ONE SIDEBAR ─▶ destinations ─▶  [ ENTITY GRAPH (active context) ]
   (context +                         │
    permissions +     drill-down ─────┼───── cross-links (lateral)
    subscription +                    ▼
    modules)                   parent ──▶ child ──▶ grandchild …
                                     ▲                     │
                                     └──── «back» / breadcrumb
```

Three kinds of edges:

1. **Drill-down (parent → child).** Deepens context: context → bounded context →
   entity → sub-entity (`NAVIGATION_MAP.md` §5). Each hop is permission-gated.
2. **Cross-link (lateral).** Relationships between sibling entities, e.g.
   Candidate Profile ◀──▶ Applications ◀──▶ Job (`NAVIGATION_MAP.md` §6).
3. **Global jump.** `Search` and `Notifications` (and the command palette,
   `USER_EXPERIENCE.md` §4) jump straight to any permitted entity from anywhere.

Every edge is reversible by **back-navigation** and locatable by the
**breadcrumb** (§7).

---

## 2. Full Page vs Modal vs Drawer vs Tab

These are the **binding container rules**; per-screen assignments are catalogued
in `SCREEN_CATALOG.md`, and the UX rationale is in `USER_EXPERIENCE.md` §9.

| Container | Use when | MUST | MUST NOT |
|---|---|---|---|
| **Full page** | The user *arrives at a destination*: a sidebar entry or a node in a drill-down chain. | Have its own route, breadcrumb (§7), and the six screen states (`UI_GUIDELINES.md` §8). | Be used for a transient confirmation. |
| **Tab** | Showing a **facet of one entity** that shares that entity's identity. | Share the parent's breadcrumb and route context; preserve the entity in focus. | Stitch together unrelated entities or act as top-level navigation. |
| **Drawer** ⇧ | A focused side task or context view **without leaving** the current screen (quick note, quick edit, record preview). | Trap and restore focus; be dismissible; keep the underlying screen's place. | Host a long multi-step destination or a primary landing. |
| **Modal** ▣ | A short, blocking, self-contained task or a destructive confirmation. | Trap and restore focus; confirm destructive actions; be escapable. | Replace a full page or nest modals deeply. |

Canonical assignments (authoritative list in `SCREEN_CATALOG.md`):

```
Full page : Dashboard · Jobs · Job Detail · Candidate Profile · Talent Pool ·
            Pipeline · Interviews · Interview Detail · Offers · Offer Detail ·
            Analytics · Reports · Files · Members · Member Detail ·
            Members ▸ Roles · Settings · Billing
            (Platform) Overview · Companies / Workspaces · Workspace Detail ·
            Users · User Detail · Subscriptions · Plan Detail ·
            Subscription Detail · AI Providers · Provider Detail ·
            System Analytics · Audit Logs · Platform Settings · Diagnostics
Tab       : Job Detail ▸ {Overview · Applications · Pipeline · Interviews ·
            Offers · Hiring Team · Settings}
            Application ▸ {Activity Timeline · Documents}
Drawer ⇧  : quick-add note/tag on a Candidate Profile · quick-edit · record
            preview from a list · the global Search and Notifications panels
Modal  ▣  : Create job · Invite member · Schedule interview · Create offer ·
            destructive confirmations (close/archive/delete, remove member)
```

> `Search` and `Notifications` render as global **drawers/overlays** from the top
> bar (`USER_EXPERIENCE.md` §2–§3); they are surfaces, not destinations, and so
> never own a breadcrumb of their own — selecting an item navigates into the graph.

---

## 3. Cross-Links (lateral relationships)

Lateral edges are **relationships, not a menu** (`NAVIGATION_MAP.md` §6). They let
a user move sideways between related entities without climbing back up the tree.

```
Candidate Profile ◀───────────▶ Applications ◀───────────▶ Job
        │  (same User, this workspace)        │  (this requisition)
        ▼                                     ▼
   Talent Pool entry ⇧(save)            Job Detail ▸ Applications

Interview Detail ──▶ Application ──▶ Job          («back» works each hop)
Offer Detail ─────▶ Application ──▶ Candidate Profile
Employee context ─▶ originating (Hired) Application ──▶ Candidate Profile

Reports / Analytics ──▶ drill from a metric ──▶ underlying Jobs / Applications
Files ◀──▶ Application ▸ Documents   (CV is a File owned by the User, referenced)
```

Binding rules for cross-links:

- **Gated and tenant-bound.** A cross-link renders only if the target is
  permitted, subscribed, and module-enabled, and only inside the active
  workspace's data (`NAVIGATION_MAP.md` §6, `SECURITY_MATRIX.md` §1.3,
  §3.11). It MUST NOT expose another tenant's record or its mere existence.
- **References, not copies.** A cross-link navigates to the *same* entity; it
  never duplicates it. The CV opened from *Application ▸ Documents* is the `File`
  owned by the `User`, referenced — not a copy (`APPLICATION_FLOW.md` §4, §10).
- **Profile is a view, reached through Recruitment.** A *Candidate Profile* is a
  per-`(User, Workspace)` view, reached from an Application, the Talent Pool, or
  Search — never a standalone account screen (`NAVIGATION_MAP.md` §4,
  `APPLICATION_FLOW.md` §6).
- **Cross-links are bidirectional where the relationship is.** From a Candidate
  Profile a user reaches this workspace's Applications for that user; from an
  Application a user reaches both the Job and the Candidate Profile.

---

## 4. The Canonical Chain (Jobs → … → Employee)

The flagship drill-down spans the whole hiring operation. It mirrors the
recruitment lifecycle (`APPLICATION_FLOW.md` §2, `USER_JOURNEYS.md` Journey 1) and
the state machine (`STATE_DIAGRAMS.md`), where each arrow is also a state
transition.

```
WORKSPACE CONTEXT (active workspace)
   │
   ▼
Recruitment ▸ Jobs ............................... list of requisitions
   │  «back» ▲                                     [perm: job.view]
   ▼
Job Detail ...................................... one requisition (tabs, §5)
   │  ▸ Applications tab
   ▼
Applications (for this Job) ..................... candidacies on this Job
   │  «back» ▲                                     [perm: application.view]
   ▼
Candidate Profile .............................. view of the applying User
   │  (workspace-scoped projection)               [perm: candidate.view — PII]
   ▼
Interview Detail ............................... attached to the Application
   │  «back» ▲                                     [perm: interview.view]
   ▼
Offer Detail ................................... extended on the Application
   │                                               [perm: offer.view]
   ▼
Employee context ............................... post-hire context of the User
                                                  [perm: employee.view]
```

State alignment (each drill-down hop ≈ a transition):

```
Job.publish ─▶ Application{Applied} ─▶ pipeline stages ─▶ Interview{Evaluated}
            ─▶ Offer{Sent}{Accepted} ─▶ Application{Hired} ─▶ Employee{Onboarding}
```

- Every hop is **independently permission-gated** (`NAVIGATION_MAP.md` §5,
  `SECURITY_MATRIX.md` §3.2–§3.6); holding `job.view` grants no automatic right to
  the Candidate Profile (`candidate.view`, a PII gate, `SECURITY_MATRIX.md` §4.4).
- The chain is **fully reversible** by breadcrumb / «back» (§7): from *Offer
  Detail* a user climbs Application → Candidate Profile → Job Detail → Jobs.
- The **Pipeline** is the cross-job view of the same Applications: *Pipeline*
  (sidebar) and *Job Detail ▸ Pipeline* (one job) drill into the same Application
  cards (`NAVIGATION_MAP.md` §5–§6, `SECURITY_MATRIX.md` §3.3).

---

## 5. Job Detail — Tabs as In-Page Children

A *Job Detail* is a hub whose **tabs** are facets of the one requisition; they
share its identity and breadcrumb and are **not** separate destinations
(`NAVIGATION_MAP.md` §5). Each tab is independently gated.

```
Job Detail  (breadcrumb: … ▸ Jobs ▸ {Job})
│
├─ ▸ Overview ....... requisition summary + state      [job.view]
├─ ▸ Applications ... candidacies ──▶ Candidate Profile [application.view]
├─ ▸ Pipeline ...... this Job's stages (Kanban) ──▶ Application card [pipeline.view]
├─ ▸ Interviews .... interviews for this Job ──▶ Interview Detail   [interview.view]
├─ ▸ Offers ........ offers on this Job ──▶ Offer Detail            [offer.view]
├─ ▸ Hiring Team ... members on this Job                 [member.view + job.view]
└─ ▸ Settings ...... stages · screening questions · required documents (Templates)
                                                          [job.update]
```

And the **Application**, the spine, exposes its own sub-structure
(`NAVIGATION_MAP.md` §5, `APPLICATION_FLOW.md` §4):

```
Application  (User + Job + Workspace)
├─ ▸ Activity Timeline .. append-only, audit-backed     [application.view]
├─ ▸ Documents .......... referenced Files (CV)          [application.view + files.view]
├─ ──▶ Candidate Profile  workspace-scoped view of the User
├─ ──▶ Interview Detail(s) Scheduled→InProgress→Completed→Evaluated
└─ ──▶ Offer (0..1) ...... Draft→Approved→Sent→Accepted (→ Hired → Employee)
```

Rules:

- **Tab switches stay on the entity.** Moving between *Job Detail* tabs MUST NOT
  change the breadcrumb's terminal node; the user is still "on this Job."
- **A tab links out, it does not absorb.** *Applications* and *Pipeline* tabs
  **drill into** full-page children (a Candidate Profile, an Application card);
  they do not flatten those children into the tab.
- **Gating is per tab.** A user with `job.view` but not `offer.view` sees *Job
  Detail* without the *Offers* tab (`SECURITY_MATRIX.md` §3.2).

---

## 6. Global Search & Notifications → Any Entity

The two global surfaces collapse navigation: they jump **into** the graph from
anywhere, bypassing the drill-down spine (`USER_EXPERIENCE.md` §2–§3).

```
GLOBAL SEARCH (top bar, drawer/overlay)        NOTIFICATIONS (top bar, drawer/overlay)
[perm: search.use]                              [perm: notifications.view]
   │ grouped results, gated per target             │ each item carries its origin
   ▼                                                ▼
 Workspace Context groups:                      "application submitted" ──▶ Application
   Jobs ─────────────▶ Job Detail               "interview scheduled" ──▶ Interview Detail
   Candidate Profiles ▶ Candidate Profile       "offer accepted" ──────▶ Offer Detail
   Applications ─────▶ Application               "member invited" ──────▶ Member Detail
   Members ──────────▶ Member Detail            "stage changed" ───────▶ Application
   Files ────────────▶ Files / Documents
   Reports ──────────▶ Reports
   Interviews ───────▶ Interview Detail

 Platform Context adds:
   Companies / Workspaces ▶ Workspace Detail
   Users ──────────────────▶ User Detail
```

- **Open a result = enter the graph at the target.** Selection lands on the
  target's canonical full page; from there normal drill-down, cross-links, and
  breadcrumb apply (§4, §7). The result list is not a place to stay
  (`USER_EXPERIENCE.md` §2).
- **The target's gate is re-checked.** A search hit or a notification deep-link
  routes through the target entity's own permission gate; visibility in the
  result list never substitutes for authorization on open
  (`SECURITY_MATRIX.md` §3.11, `NAVIGATION_MAP.md` §6).
- **Tenant-isolated.** Both surfaces are scoped to the active workspace (or, in
  the Platform Context, to global platform data only). Neither can reach another
  tenant's business records (`SECURITY_MATRIX.md` §1.3).
- **One center, no per-module notification screens** (`USER_EXPERIENCE.md` §3).

---

## 7. Breadcrumb & Back-Navigation Model

Every full-page screen carries a **breadcrumb** that names its position in the
graph; back-navigation walks the same chain in reverse.

```
Workspace Context — drill-down example:
[Workspace] ▸ Recruitment ▸ Jobs ▸ {Job title} ▸ Applications ▸ {Candidate}
     └─ root    └─ context   └─ list  └─ entity     └─ tab        └─ child

Tab example (terminal node unchanged across tabs):
[Workspace] ▸ Recruitment ▸ Jobs ▸ {Job title} ▸ [Overview|Applications|Pipeline|…]

Platform Context example:
[Platform] ▸ Companies / Workspaces ▸ {Workspace} ▸ {member} (User Detail)
```

Binding rules:

- **The breadcrumb reflects the drill-down chain**, not browser history. Each
  segment is a real ancestor screen and is itself a navigation hop
  (`NAVIGATION_MAP.md` §5).
- **The root names the context.** The leading segment makes the active context
  unmistakable — `[Platform]` vs `[Workspace]` — reinforcing
  `USER_EXPERIENCE.md` §1 and `UI_GUIDELINES.md` §3. The active workspace is
  identified at/near the root.
- **Tabs do not extend the breadcrumb's terminal node.** Switching a *Job Detail*
  tab keeps the terminal segment on the Job (§5).
- **Cross-links may re-root the breadcrumb.** Following a lateral link (e.g.
  Application → Candidate Profile) MAY recompute the breadcrumb to the target's
  canonical ancestry rather than appending; the canonical parent of a Candidate
  Profile is its Recruitment ancestry, not whichever screen linked to it
  (`NAVIGATION_MAP.md` §4, §6).
- **Search / Notification entry is re-rooted, not appended.** Arriving via a
  global surface (§6) places the user at the target with the target's **canonical**
  breadcrumb — never "Search ▸ …" — so the back-path is sensible.
- **Every segment is gated.** A breadcrumb segment renders as a link only if the
  user may view that ancestor; otherwise it is shown as inert text, never a
  leaking link (`SECURITY_MATRIX.md` §1.1).
- **Direction-aware.** Breadcrumb separators/chevrons MUST mirror under RTL
  (`UI_GUIDELINES.md` §6).
- **Modals/drawers do not alter the breadcrumb.** A modal (▣) or drawer (⇧)
  overlays the current screen; dismissing it restores the prior place and focus
  (§2, `UI_GUIDELINES.md` §9). Only full-page navigation changes the breadcrumb.

---

## 8. Context Switching & Its Effect on Screens

HaHireAI presents two contexts behind **one** product (`NAVIGATION_MAP.md` §2,
`UI_GUIDELINES.md` §3). Switching context — Platform↔Workspace or
workspace↔workspace — **re-derives the entire visible UI** from the new context's
inputs; UI from one context MUST NOT leak into another.

### 8.1 Workspace ↔ Workspace

```
Active: Workspace A                         Active: Workspace B
  sidebar  = f(user, A, permsA, subA, modA)   sidebar  = f(user, B, permsB, subB, modB)
  search   = A's index only                   search   = B's index only
  notifs   = A's notifications                notifs   = B's notifications
  data     = A's records (workspace_id = A)   data     = B's records (workspace_id = B)
       │                                           ▲
       └──────────── switch via switcher ──────────┘
                (one login; permissions in A never affect B)
```

- The **entire sidebar, search index, notification center, branding, and data
  scope** recompute for the newly active workspace (`USER_JOURNEYS.md` Journey 5,
  `UI_GUIDELINES.md` §2–§3, §5).
- A screen open in A has **no equivalent deep-link** carried into B: switching
  returns the user to a safe landing (the workspace `Dashboard`), because an A
  entity has no meaning under B's tenant scope (`SECURITY_MATRIX.md` §1.3).
- Permissions, subscription, and enabled modules are **per workspace**; the same
  user MAY see *Offers* in A and not in B (`USER_JOURNEYS.md` Journey 5,
  `PERMISSION_MODEL.md` §6).

### 8.2 Platform ↔ Workspace

```
WORKSPACE CONTEXT  ──(if user holds system.*)──▶  PLATFORM CONTEXT
  Dashboard, Recruitment, Members, …               Overview, Companies / Workspaces,
  (tenant-scoped, workspace_id set)                 Users, Subscriptions, AI Providers,
                                                     System Analytics, Audit Logs, …
        ▲                                            (global data, no workspace_id)
        └──────────── switch back to a workspace ────┘
```

- The **Platform Context is offered only** when the user holds `system.*`
  (`USER_JOURNEYS.md` Journey 4, `SECURITY_MATRIX.md` §2). For everyone else it
  does not exist in the switcher.
- Platform screens operate on **global** data and carry **no** `workspace_id`;
  any reach into a tenant's business data is an explicit, permission-gated,
  audited **bypass** (e.g. impersonation from *Workspace Detail*),
  not normal navigation (`SECURITY_MATRIX.md` §1.3, §2).
- The **same screen names never collide across contexts**: *Analytics* (workspace,
  `report.view`) is distinct from *System Analytics* (platform,
  `system.observability.view`); *Members* (workspace) is distinct from *Users*
  (platform). The context root in the breadcrumb (§7) keeps them unambiguous.
- A Platform screen MUST NOT render in a Workspace Context, and a Workspace screen
  MUST NOT render in the Platform Context (`UI_GUIDELINES.md` §3).

### 8.3 The no-workspace state

```
New User, no Membership ──▶ exactly two paths:
        ┌──────────────────┐     ┌──────────────────┐
        │ Create Workspace │     │  Join Workspace  │
        └──────────────────┘     └──────────────────┘
              (nothing else in navigation)
```

A user with no membership has **no** Workspace Context to render; the only
screens reachable are *Create Workspace* and *Join Workspace*
(`USER_JOURNEYS.md` Journey 1 step 4 & Invariant 2, `USER_EXPERIENCE.md` §7).

---

## 9. Relationship Self-Review Checklist (Phase 5)

A screen-relationship design is conformant only if **all** hold:

- [ ] **Graph, not silo.** Each screen's parents, children, and cross-links are
      defined and reachable; no orphan screens (§1, §3, §4).
- [ ] **Right container.** Full page / tab / drawer / modal follows §2; tabs are
      facets of one entity, modals/drawers never replace destinations.
- [ ] **Canonical chain intact.** Jobs → Job Detail ▸ Applications → Candidate
      Profile → Interview Detail → Offer Detail → Employee drills and reverses,
      each hop independently gated (§4).
- [ ] **Cross-links are references & gated.** Lateral links open the same entity,
      honor the target's gate, and never cross tenants (§3).
- [ ] **Global jumps re-root.** Search and Notifications enter the graph at the
      target with its canonical breadcrumb and re-checked gate (§6, §7).
- [ ] **Breadcrumb = drill-down chain.** Context-rooted, tab-stable, gated
      per segment, RTL-mirrored; modals/drawers leave it unchanged (§7).
- [ ] **Context switch re-derives everything.** Sidebar, search, notifications,
      data, and branding recompute; no cross-context or cross-tenant leakage; safe
      landing on switch (§8).
- [ ] **No-workspace state.** Only Create / Join Workspace render (§8.3).

---

### Related Documents

`NAVIGATION_MAP.md` · `SCREEN_CATALOG.md` · `USER_EXPERIENCE.md` ·
`UI_GUIDELINES.md` · `USER_JOURNEYS.md` · `APPLICATION_FLOW.md` ·
`STATE_DIAGRAMS.md` · `SECURITY_MATRIX.md` · `PERMISSION_CATALOG.md` ·
`PERMISSION_MODEL.md` · `WORKSPACE_MODEL.md` · `MODULES.md` ·
`NAVIGATION_ARCHITECTURE.md` · `SIDEBAR_MODEL.md` · `LAYOUT_SYSTEM.md` ·
`PAGE_STANDARDS.md`
