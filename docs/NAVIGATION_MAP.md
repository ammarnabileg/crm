# NAVIGATION MAP — HaHireAI

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `SYSTEM_BLUEPRINT.md`. **Deep UX:** Phase 5 (`NAVIGATION_ARCHITECTURE.md`, `SCREEN_RELATIONSHIPS.md`).

---

## 1. Purpose & Principles

This document is a **navigation / screen-relationship map**: *which* screens
exist, *how* they nest, and *how* they link — not how they look. Visual layout,
components, and interaction detail are deferred to Phase 5 (`SCREEN_CATALOG.md`,
`NAVIGATION_ARCHITECTURE.md`, `SIDEBAR_MODEL.md`, `SCREEN_RELATIONSHIPS.md`).
Terminology is bound to `DOMAIN_MODEL.md` and `MODULES.md`; the canon governs.

Governing principles (`SYSTEM_BLUEPRINT.md` §8, `WORKSPACE_MODEL.md` §7,
`PERMISSION_MODEL.md` §5):

1. **A system, not a stack of pages.** The UI is a *projection* of the domain
   (`SYSTEM_OVERVIEW.md` §1). Navigation expresses the relationships between
   bounded contexts and entities — never a sitemap of forms.
2. **One dynamic sidebar.** Exactly **one** sidebar, generated at runtime from
   the **active context**, the user's **permissions**, the workspace
   **subscription**, and the **enabled modules**. Never one sidebar per role,
   never a hard-coded menu.
3. **Two contexts, one product.** Navigation resolves into either the
   **Platform Context** (System Owners, `system.*`) or the **Workspace Context**
   (members of one active workspace). A `User` MAY switch workspaces freely and,
   if granted, into the Platform Context — all with one login.
4. **Gated visibility (deny-by-default).** A screen, sidebar entry, or
   drill-down link appears **only if** permission **AND** subscription **AND**
   enabled-module all allow it. Hiding UI never substitutes for server-side
   enforcement (`PERMISSION_MODEL.md` §5).
5. **Additive growth.** Adding a module adds nav entries through its manifest
   (§8); it never requires redesigning the navigation engine.

---

## 2. Top-Level Map (both contexts)

```
HaHireAI (one product, one sidebar — dynamically generated)
│
├── PLATFORM CONTEXT ............... requires system.*  (System Owners only)
│     │
│     └── platform-wide operation of the SaaS itself
│
└── WORKSPACE CONTEXT ............. requires Membership in the active workspace
      │
      ├── Workspace switcher ....... move between joined workspaces (one active)
      └── per-workspace operation, gated by permission + subscription + module

  Context resolution:
    User holds system.*  ──▶ MAY enter Platform Context (explicit switch)
    User has Membership  ──▶ enters Workspace Context for the active workspace
    Same User, one login ──▶ both contexts reachable; data never crosses tenants
```

Both contexts share the same shell and sidebar **engine**; they differ only in
the entries that engine emits for the resolved context.

---

## 3. Platform Context — Screen Tree

Scope: the platform itself (`SYSTEM_OVERVIEW.md` §6). Every entry requires a
`system.*` permission and is invisible in the Workspace Context. Owned by
**System Administration**, reading/managing other platform modules.

```
PLATFORM CONTEXT
│
├── Overview .................. platform health & KPIs landing
│
├── Companies / Workspaces .... all tenant workspaces (cross-tenant, system view)
│     └── Workspace Detail ..... members, subscription, status, lifecycle actions
│
├── Users ..................... global User directory (the single identity)
│     └── User Detail .......... profile, memberships, system flags
│
├── Subscriptions ............. plans & per-workspace subscription state
│     ├── Plan Detail .......... a global Plan (features + limits)
│     └── Subscription Detail .. one workspace's subscription lifecycle
│
├── AI Providers .............. global AI provider/model catalog & keys
│     └── Provider Detail ...... models, credentials, fallback config
│
├── System Analytics .......... cross-tenant platform metrics
│
├── Audit Logs ................ system-level immutable activity trail
│
├── Platform Settings ......... global defaults & system configuration
│
├── Diagnostics ............... health probes, maintenance, runtime checks
│
└── Developer Tools ........... HIDDEN unless explicitly enabled (feature flag)
```

> **Companies / Workspaces** is the tenant list. A *Company* is optional data
> inside a workspace's settings, never a separate entity (`WORKSPACE_MODEL.md`
> §1, §8); the entry manages **Workspaces**.

---

## 4. Workspace Context — Screen Tree

Scope: exactly one active workspace; all data tenant-isolated
(`WORKSPACE_MODEL.md` §3). Every entry is gated by permission + subscription +
enabled module. **Recruitment** is one bounded context with sub-areas
(`MODULES.md` §3), shown only when the Recruitment module is enabled.

```
WORKSPACE CONTEXT  (active workspace)
│
├── Dashboard ................. workspace landing / activity summary
│
├── Recruitment .............. [enabled module: Recruitment] one bounded context
│     ├── Dashboard ........... hiring overview for this workspace
│     ├── Jobs ................ requisitions list
│     │     └── Job Detail .... tabs: Overview · Applications · Pipeline ·
│     │                          Interviews · Offers · Hiring Team · Settings
│     ├── Talent Pool ......... saved / passive / past candidates
│     ├── Pipeline ........... cross-job Kanban of stages
│     ├── Interviews ......... scheduled AI + human evaluations
│     ├── Offers ............. extended offers & approvals
│     └── Analytics .......... hiring analytics (workspace-scoped)
│
├── Members .................. memberships, invitations, roles & permissions
│     ├── Member Detail ....... a membership: status, roles, grants
│     └── Roles ............... workspace role builder (roles are data)
│
├── Reports .................. saved views & workspace reporting
│
├── Files .................... workspace-scoped file storage
│
├── Search ................... unified workspace-scoped search (→ any entity)
│
├── Notifications ............ per-user, workspace-contextual notifications
│
├── Settings ................. workspace config, branding, AI, security, etc.
│
└── Billing .................. subscription, plan, invoices (this workspace)
```

Notes:
- **Candidate Profiles** are reached *through* Recruitment (from an Application,
  Talent Pool, or Search — §5/§6). They are a per-`(User, Workspace)` **view**,
  not a top-level account screen (`DOMAIN_MODEL.md` §3).
- **Employee** is a post-hire **context** of a `User`, reached from a `Hired`
  Application (§5) — not an account screen.
- Recruitment sub-areas appear only if the member holds the matching view
  permission *and* the sub-capability is within the subscription.

---

## 5. Drill-Down Relationships (parent → child)

Navigation deepens from context → bounded context → entity → sub-entity. Each
hop is itself permission-gated. The canonical example chain spans the whole
hiring operation:

```
System (Platform Context)
  └─▶ Workspace (Workspace Context — one active tenant)
        └─▶ Recruitment (enabled bounded context)
              └─▶ Jobs (list)
                    └─▶ Job Detail
                          └─▶ Applications (tab on the Job)
                                └─▶ Candidate Profile (view of the User in A)
                                      └─▶ Interview (attached to the Application)
                                            └─▶ Offer (extended on the Application)
                                                  └─▶ Employee (post-hire context)
```

Each arrow maps to a state transition in `STATE_DIAGRAMS.md` / `APPLICATION_FLOW.md`:
`Job.publish` → `Application` created (`Applied`) → interview stages →
`Offer.accept` → `Application = Hired` → **Employee** context created.

**Job Detail tabs** (the in-page children of one Job):

```
Job Detail
├── Overview ........ requisition summary + state (Draft→Published→…→Archived)
├── Applications .... candidacies bound to this Job ──▶ Candidate Profile
├── Pipeline ........ this Job's stages (Kanban) ──▶ Application card
├── Interviews ...... interviews scheduled for this Job's applications
├── Offers .......... offers extended on this Job's applications
├── Hiring Team ..... members assigned to this Job
└── Settings ........ stages, screening questions, required documents (Templates)
```

**Application → child relationships:**

```
Application (the spine: User + Job + Workspace)
├── Activity Timeline .. append-only history (Audit-backed)
├── Documents .......... referenced Files (CV owned by the User, not copied)
├── Candidate Profile .. workspace-scoped projection of the applying User
├── Interview(s) ....... Scheduled→InProgress→Completed→Evaluated
└── Offer (0..1) ....... Draft→Approved→Sent→Accepted (→ Hired → Employee)
```

---

## 6. Cross-Links (lateral navigation)

Beyond the parent→child tree, several entities link **sideways**. These are
relationships, not a separate menu:

```
Candidate Profile ◀───────────▶ Applications ◀───────────▶ Job
        │  (same User, this workspace)        │  (this requisition)
        ▼                                     ▼
   Talent Pool entry                    Job Detail (Applications tab)

Interview ──▶ Application ──▶ Job            (breadcrumb chain, both directions)
Offer ─────▶ Application ──▶ Candidate Profile
Employee ──▶ originating (Hired) Application ──▶ Candidate Profile

Global Search ─────────────▶ ANY workspace-scoped entity
   (Job · Application · Candidate Profile · Interview · Offer · File · Member)

Notifications ─────────────▶ the entity that raised the event
   (e.g. "application submitted" ──▶ that Application)

Reports / Analytics ───────▶ drill from a metric ──▶ underlying Jobs/Applications
```

Cross-links honor the same gates: a link renders only if the target is
permitted, subscribed, and module-enabled, and only within the active
workspace's data (no cross-tenant traversal — `WORKSPACE_MODEL.md` §3).

---

## 7. Screen → Permission · Module · Entry Points

Permission keys use the `resource.action` grammar of `PERMISSION_MODEL.md` §2
(`system.*` for Platform Context). Keys shown are representative view-gates;
the authoritative catalog is `PERMISSION_CATALOG.md` (Phase 4). "Enabling
module" names the owner from `MODULES.md`. **Every** Workspace-Context row is
*additionally* gated by the workspace **subscription** + **enabled module**.

### 7.1 Platform Context

| Screen | Required permission | Enabling module | Entry points |
|---|---|---|---|
| Overview | `system.dashboard.view` | System Administration | Context switch → Platform |
| Companies / Workspaces | `system.workspaces.manage` | System Administration | Sidebar; Overview |
| Workspace Detail | `system.workspaces.manage` | System Administration | Companies/Workspaces row |
| Users | `system.users.manage` | System Administration | Sidebar |
| User Detail | `system.users.manage` | System Administration | Users row; Workspace Detail (member) |
| Subscriptions | `system.subscriptions.manage` | Subscriptions | Sidebar; Workspace Detail |
| Plan Detail | `system.subscriptions.manage` | Subscriptions | Subscriptions list |
| Subscription Detail | `system.subscriptions.manage` | Subscriptions | Subscriptions list; Workspace Detail |
| AI Providers | `system.ai.manage` | AI Engine | Sidebar |
| Provider Detail | `system.ai.manage` | AI Engine | AI Providers row |
| System Analytics | `system.observability.view` | Reports / Analytics | Sidebar; Overview |
| Audit Logs | `system.audit.view` | Audit | Sidebar; any platform record |
| Platform Settings | `system.settings.manage` | Settings | Sidebar |
| Diagnostics | `system.diagnostics.run` | Observability | Sidebar; Overview |
| Developer Tools | `system.dashboard.view` + feature flag | System Administration | Sidebar **(hidden unless enabled)** |

### 7.2 Workspace Context

| Screen | Required permission | Enabling module | Entry points |
|---|---|---|---|
| Dashboard | `workspace.view` | Workspaces | Default landing; workspace switcher |
| Recruitment ▸ Dashboard | `job.view` | Recruitment | Sidebar; Dashboard |
| Recruitment ▸ Jobs | `job.view` | Recruitment | Sidebar; Recruitment Dashboard |
| Job Detail | `job.view` | Recruitment | Jobs row; Search; Notifications |
| Job Detail ▸ Applications | `application.view` | Recruitment | Job Detail tab; Pipeline card |
| Candidate Profile | `candidate.view` | Recruitment | Application; Talent Pool; Search |
| Recruitment ▸ Talent Pool | `candidate.view` | Recruitment | Sidebar; Candidate Profile (save) |
| Recruitment ▸ Pipeline | `application.view` | Recruitment | Sidebar; Job Detail (Pipeline tab) |
| Recruitment ▸ Interviews | `interview.view` | Recruitment | Sidebar; Application; Notifications |
| Interview Detail | `interview.view` | Recruitment | Interviews list; Application |
| Recruitment ▸ Offers | `offer.view` | Recruitment | Sidebar; Application |
| Offer Detail | `offer.view` | Recruitment | Offers list; Application |
| Recruitment ▸ Analytics | `report.view` | Reports / Analytics | Sidebar; Recruitment Dashboard |
| Members | `member.view` | Memberships | Sidebar; Settings |
| Member Detail | `member.view` | Memberships | Members row; Audit entry |
| Members ▸ Roles | `role.view` | Permissions | Members; Settings |
| Reports | `report.view` | Reports / Analytics | Sidebar; Analytics |
| Files | `files.view` | Files | Sidebar; Application (Documents) |
| Search | `workspace.view` | Search | Global top-bar (always present) |
| Notifications | `workspace.view` | Notifications | Global top-bar (always present) |
| Settings | `settings.view` | Settings | Sidebar |
| Billing | `billing.manage` | Billing / Subscriptions | Sidebar; Settings |

> Representative action-gates already in canon: `job.create`,
> `candidate.export`, `interview.ai.run`, `member.invite`, `billing.manage`
> (`PERMISSION_MODEL.md` §2, §6). These govern actions *within* a screen, not
> the screen's visibility, which uses the `*.view` gates above.

---

## 8. Rule — Adding a Module Adds Nav, Not Redesign

Navigation is **generated from module manifests**, never hand-maintained
(`SYSTEM_BLUEPRINT.md` §9, `MODULES.md` §6, `WORKSPACE_MODEL.md` §8).

```
New module ships module.php  ─┬─ declares permissions  ─┐
                              ├─ declares routes        ├─▶ Module Registry
                              ├─ declares enabledBy      │     │
                              └─ declares nav entries ───┘     ▼
                                                        Sidebar engine emits
                                                        entries IF: permission
                                                        AND subscription AND
                                                        module enabled
```

Consequences (binding):
- Adding a capability is **additive**: a new module contributes its sidebar
  entries and screens via its manifest. The **navigation**, **permission**, and
  **database** engines are **not** modified (`SYSTEM_BLUEPRINT.md` §9,
  `MODULES.md` §7, `WORKSPACE_MODEL.md` §8 invariant 5).
- The single dynamic sidebar absorbs new entries automatically for users who
  pass all three gates; everyone else never sees them.
- Because the workspace platform is **domain-agnostic** (`WORKSPACE_MODEL.md`
  §1), a future business domain (e.g. HR, CRM) appears as a new top-level
  bounded-context node exactly as **Recruitment** does today — with no change to
  this map's structure.

---

### Related Documents

`SYSTEM_BLUEPRINT.md` · `MODULES.md` · `DOMAIN_MODEL.md` · `WORKSPACE_MODEL.md` ·
`PERMISSION_MODEL.md` · `STATE_DIAGRAMS.md` · `APPLICATION_FLOW.md` ·
`SYSTEM_OVERVIEW.md` · `PERMISSION_CATALOG.md` (Phase 4) ·
`SCREEN_CATALOG.md` · `NAVIGATION_ARCHITECTURE.md` · `SIDEBAR_MODEL.md` ·
`SCREEN_RELATIONSHIPS.md` (Phase 5)
