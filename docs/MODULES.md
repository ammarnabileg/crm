# MODULES — HaHireAI

> **Status:** Adopted (Canon) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md`, `ARCHITECTURE.md`, `DOMAIN_MODEL.md`.
> This is the **canonical module map**. Every module name used anywhere in the
> codebase or docs MUST match this list exactly.

---

## 1. Module Layers

Modules are organized into layers. Lower layers never depend on higher ones.

```
┌── ADMINISTRATION ──────────────────────────────────────────────┐
│  System Administration (Platform Context / System Owner console) │
├── OPERATIONS ───────────────────────────────────────────────────┤
│  Observability / Diagnostics / Operations                        │
├── COMMERCE ─────────────────────────────────────────────────────┤
│  Subscriptions · Billing · Licensing                             │
├── INTEGRATION ──────────────────────────────────────────────────┤
│  Integration Platform (API Gateway, Webhooks, Connectors, SSO)   │
├── PROCESS ──────────────────────────────────────────────────────┤
│  Workflow Engine (Automation, Approvals, Scheduler, Jobs)        │
├── INTELLIGENCE ─────────────────────────────────────────────────┤
│  AI Engine · Reports/Analytics                                    │
├── BUSINESS DOMAIN ──────────────────────────────────────────────┤
│  Recruitment (Jobs, Applications, Candidates, Pipeline,          │
│               Interviews, Offers, Talent Pool, Templates)         │
├── PLATFORM SERVICES (shared) ───────────────────────────────────┤
│  Files · Notifications · Search · Audit · Settings               │
├── IDENTITY & ACCESS ────────────────────────────────────────────┤
│  Authentication · Users · Workspaces · Memberships · Permissions  │
├── FOUNDATION ───────────────────────────────────────────────────┤
│  Core Kernel · Database · Installer                               │
└──────────────────────────────────────────────────────────────────┘
```

## 2. Module Catalog

| Module | Layer | Responsibility (single) | Phase |
|---|---|---|---|
| **Core Kernel** | Foundation | Boot, container, router, config, env, logger, errors, events, module registry, health | 7 |
| **Database** | Foundation | Connection manager, schema builder, migration engine, transactions, base repository (no ORM) | 8 |
| **Installer** | Foundation | Zero-touch browser installation, first System Owner, health check | 8 |
| **Authentication** | Identity & Access | Register, login/logout, password reset, remember-me, sessions, MFA-ready | 8 |
| **Users** | Identity & Access | The single `User` identity & profile lifecycle | 8 |
| **Permissions** | Identity & Access | Permission catalog, roles, assignment, authorization checks | 8 |
| **Workspaces** | Identity & Access | Workspace lifecycle, settings, branding, isolation root | 9 |
| **Memberships** | Identity & Access | User↔Workspace links, invitations, status | 9 |
| **Settings** | Platform Services | Workspace & system settings registry | 9 |
| **Files** | Platform Services | Upload, storage, ownership, visibility, retention | 9 |
| **Notifications** | Platform Services | Unified notification center (realtime-ready) | 9 |
| **Search** | Platform Services | Unified workspace-scoped search index | 9 |
| **Audit** | Platform Services | Immutable activity & audit trail | 9 |
| **Recruitment** | Business Domain | Full hiring operating system (see §3) | 10 |
| **Learning** | Business Domain | Training/onboarding/development programs: sections→items, assignment, enrollment + progress, self/manager to-dos, polymorphic comments, collaboration (see `LEARNING_PROGRAMS.md`) | 10 |
| **AI Engine** | Intelligence | Central, multi-provider AI capability layer | 11 |
| **Reports / Analytics** | Intelligence | Cross-module metrics, dashboards, saved views | 10–15 |
| **Workflow Engine** | Process | Central automation, triggers, conditions, actions, approvals, scheduler, background jobs | 12 |
| **Integration Platform** | Integration | API Gateway, REST API, webhooks, event bus exposure, connectors, OAuth/SSO/SCIM-ready, developer portal | 13 |
| **Subscriptions** | Commerce | Per-workspace subscription lifecycle & status | 14 |
| **Billing** | Commerce | Payments, invoices, coupons, providers (Stripe/Moyasar) | 14 |
| **Licensing** | Commerce | Feature flags, tenant limits, usage entitlements | 14 |
| **Observability** | Operations | Health, metrics, logs, error tracking, monitors, backups, maintenance | 15 |
| **System Administration** | Administration | Platform Context for System Owners (manage tenants, plans, global config) | 8–15 |

> **Recruitment is one bounded context, not many top-level modules.** Phase 10
> mandates that Jobs/Applications/Candidates/etc. live **inside** the Recruitment
> module as cohesive sub-domains — they are *not* separate inter-dependent
> modules. (This resolves the earlier "proposed modules" list in favor of the
> latest, most explicit instruction.)

## 3. Recruitment Sub-Domains (inside one module)

`Jobs` · `Applications` · `Candidate Profiles` · `Pipeline` · `Interviews`
(AI + Human) · `Offers` · `Talent Pool` · `Templates` · `Hiring Analytics` ·
`Activity Timeline` · `Automation Hooks`. They share the Recruitment context and
communicate internally; externally the module exposes a single `Contracts`
surface.

## 4. Shared Services (provided once, never duplicated)

Per the Constitution, these cross-cutting capabilities exist **once** and are
consumed via contracts; modules MUST NOT re-implement them:

`Authentication` · `Permissions` · `Files` · `Notifications` · `Search` ·
`AI Provider Layer` (AI Engine) · `Event Bus` · `Logging` · `Cache` ·
`Validation` · `Workflow/Automation` · `Integration/External Connectivity` ·
`Observability/Logging-Metrics`.

## 5. Dependency Map (must be acyclic)

```
Core Kernel  ◄── everything
Database     ◄── all data-owning modules
Permissions  ◄── all protected actions
Workspaces   ◄── all workspace-scoped modules
Memberships  ──► Workspaces, Users, Permissions
Recruitment  ──► Workspaces, Permissions, Files, Notifications, Search, Audit,
                 AI Engine (capabilities), Workflow (hooks), Reports
AI Engine    ──► Core, Settings, Integration (provider transport), Audit
Workflow     ──► Event Bus, AI Engine, Notifications, (any module via contracts)
Integration  ──► Event Bus, Core; exposes REST over Application use cases
Subscriptions/Billing/Licensing ──► Workspaces, Audit, Integration (payments)
Observability ──► Event Bus, Logger; reads health from all (via probes)
System Admin ──► Permissions(system.*), all platform modules (read/manage)
```

**Rules enforced here:**
- No cycles. Reactions that would create a cycle use **events**, not direct
  dependencies (e.g. Recruitment publishes `application.submitted`; Workflow and
  Notifications *subscribe* — Recruitment does not depend on them).
- Modules depend on **contracts**, resolved by the container.
- Cross-cutting engines (AI, Workflow, Integration, Observability, Licensing)
  are *requested as capabilities*; business modules never embed providers, queue
  drivers, external calls, monitoring, or plan logic.

## 6. Module Manifest (`module.php`)

Each module self-registers via a manifest declaring: `name`, `version`,
`dependencies`, `permissions`, `events` (published + subscribed), `routes`,
`enabledBy` (subscription/feature flag), and service-provider class. Modules are
discovered **only** through the Module Registry — never by filesystem guessing
(see `SERVICE_CONTAINER.md`, `BOOTSTRAP_FLOW.md`).

## 7. Self-Review Checklist (Phase 1/2 gate)

- [ ] Every module has a single responsibility.
- [ ] No duplicated capability across modules (shared services used instead).
- [ ] Dependency graph is a DAG (no circular dependencies).
- [ ] A new module can be added without changing DB/nav/permission engines.
- [ ] Recruitment is one bounded context, not fragmented modules.

---

### Related Documents
`ARCHITECTURE.md` · `SYSTEM_BLUEPRINT.md` · `FEATURE_SPECIFICATIONS/` ·
`DOMAIN_MODEL.md` · `PERMISSION_CATALOG.md` · `DATABASE_ARCHITECTURE.md`
