# SYSTEM BLUEPRINT — HaHireAI

> **Status:** Adopted (Canon) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md`, `ARCHITECTURE.md`, `MODULES.md`,
> `DOMAIN_MODEL.md`.
> **Companion deliverables (Phase 2):** `NAVIGATION_MAP.md`, `USER_JOURNEYS.md`,
> `STATE_DIAGRAMS.md`, `PERMISSION_MATRIX.md`, `FEATURE_SPECIFICATIONS/`.

---

## 1. Purpose

This is the **complete engineering map** of HaHireAI — the one picture that lets
any engineer understand the system *before* reading a single PHP file. It ties
together the layers (`ARCHITECTURE.md`), the modules (`MODULES.md`), and the
domain (`DOMAIN_MODEL.md`) into a coherent whole, and defines the rules for how
the system grows.

We design **business domains**, not pages; **capabilities**, not CRUD.

## 2. System Layers (top to bottom)

```
┌─────────────────────────────────────────────────────────────────────┐
│ PRESENTATION   HTTP (single front controller), controllers, views,    │
│                view-models, CLI. Server-rendered + Alpine/Vanilla JS.  │
├─────────────────────────────────────────────────────────────────────┤
│ APPLICATION    Use cases, command/query handlers, app services,        │
│                authorization boundary, transactions orchestration.     │
├─────────────────────────────────────────────────────────────────────┤
│ DOMAIN         Entities, value objects, domain services, domain        │
│                events, and module CONTRACTS (the public surface).      │
├─────────────────────────────────────────────────────────────────────┤
│ INFRASTRUCTURE Repository implementations, capability adapters         │
│                (AI/Integration/Queue), tenant guard.                   │
├─────────────────────────────────────────────────────────────────────┤
│ PERSISTENCE    MySQL 8 via the bespoke data layer (no ORM).            │
└─────────────────────────────────────────────────────────────────────┘
        ▲ dependencies point inward/downward only — never the reverse
```

Layer-skipping that violates the dependency rule is forbidden (e.g. a controller
running SQL). See `ARCHITECTURE.md` §2.

## 3. Business Domains

The system is partitioned into business domains; each maps to one module (or, for
Recruitment, one bounded context with internal sub-domains).

| Domain | Responsibility | Primary module(s) |
|---|---|---|
| **Identity & Access** | Who you are; what you may do | Authentication, Users, Permissions, Workspaces, Memberships |
| **Recruitment** | The hiring operating system | Recruitment (Jobs, Applications, Candidates, Pipeline, Interviews, Offers, Talent Pool, Templates) |
| **Intelligence** | AI capabilities & analytics | AI Engine, Reports/Analytics |
| **Process** | Automation & long-running work | Workflow Engine |
| **Integration** | The outside world | Integration Platform |
| **Commerce** | Money & entitlements | Subscriptions, Billing, Licensing |
| **Operations** | Keeping it healthy | Observability |
| **Administration** | Running the platform | System Administration |
| **Platform Services** | Shared building blocks | Files, Notifications, Search, Audit, Settings |
| **Foundation** | The runtime | Core Kernel, Database, Installer |

## 4. Module Boundaries

- A module's **public surface** = its `Contracts/` namespace + the events it
  publishes. Everything else (entities, repositories, handlers) is **internal**.
- A module **owns its tables**. No other module reads or writes them.
- A module is **independent**: it can be developed, tested, and reasoned about in
  isolation, and disabled without breaking unrelated modules.
- The authoritative module list, layering, and one-line responsibilities live in
  `MODULES.md`. Per-module detail lives in `FEATURE_SPECIFICATIONS/`.

## 5. Dependencies (acyclic)

The module dependency graph **MUST** be a DAG. The canonical edges and the rule
for breaking would-be cycles (publish an event instead of taking a dependency)
are defined in `MODULES.md` §5. Summary of the law:

- Depend on **contracts**, resolved by the container — never on implementations.
- Foundation ← Identity/Access ← Platform Services ← Business/Intelligence ←
  Process/Integration/Commerce/Operations/Administration (higher layers may use
  lower; never the reverse).
- Cross-cutting **engines** (AI, Workflow, Integration, Observability,
  Licensing) are *requested as capabilities*; business modules never embed
  providers, queues, external calls, monitoring, or plan logic.

## 6. Communication Rules

Three sanctioned channels only (see `ARCHITECTURE.md` §4):

1. **Contracts (sync)** — call another module's published interface for an
   immediate answer.
2. **Events (async)** — publish a domain event; interested modules subscribe.
   This is how we decouple reactors from actors and avoid cycles. The external
   Event Bus / webhooks (Phase 13) subscribe here too.
3. **Shared Services** — consume a cross-cutting capability via its contract.

```
Recruitment ──emit──▶ applications.application.submitted
                          ├──▶ Workflow Engine (run AI screening)
                          ├──▶ Notifications (notify hiring team)
                          ├──▶ Search (index candidacy)
                          └──▶ Audit (record event)
   Recruitment depends on NONE of these — they subscribe.
```

## 7. Shared Services (defined once)

These exist exactly once and are consumed via contracts; modules **MUST NOT**
re-implement them: Authentication, Permissions, Files, Notifications, Search,
AI Provider Layer (AI Engine), Event Bus, Logging, Cache, Validation,
Workflow/Automation, Integration/External Connectivity, Observability. See
`MODULES.md` §4.

## 8. Two Contexts, One Product

- **Platform Context** — for System Owners (manage tenants, plans, global AI
  providers, diagnostics, audit). Reached only with `system.*` permissions.
- **Workspace Context** — the daily work environment, scoped to one workspace,
  gated by permissions + subscription + enabled modules.

Navigation is generated dynamically for the active context (see
`NAVIGATION_MAP.md`, and Phase 5's `NAVIGATION_ARCHITECTURE.md`). There is
exactly **one** sidebar.

## 9. Future Expansion Strategy

Adding a capability is **additive**: create a module that declares its manifest
(`module.php`), registers permissions/events/routes, exposes contracts, and ships
its spec + tests. It **MUST NOT** require changing the database engine, the
navigation engine, the permission engine, or other modules' internals. Because
boundaries are explicit and communication is contract/event-based, a hot module
can later be extracted into its own service behind the same contract without
rewriting its consumers.

Although Recruitment is the first business domain, the Workspace platform is
**domain-agnostic** — future domains (e.g. HR, CRM) can be added as modules atop
the same foundation.

## 10. Self-Review (Phase 2 gate)

- [ ] Every module is independent and single-responsibility.
- [ ] No duplicated capability (shared services used instead).
- [ ] Dependency graph is acyclic; reactions use events.
- [ ] A new module can be added without redesigning nav/permissions/DB.
- [ ] The user experiences one product, not a set of pages.

---

### Related Documents
`ARCHITECTURE.md` · `MODULES.md` · `DOMAIN_MODEL.md` · `STATE_DIAGRAMS.md` ·
`NAVIGATION_MAP.md` · `USER_JOURNEYS.md` · `PERMISSION_MATRIX.md` ·
`FEATURE_SPECIFICATIONS/`
