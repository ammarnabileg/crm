# ARCHITECTURE — HaHireAI

> **Status:** Adopted (Canon) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md`. **Companion:** `SYSTEM_BLUEPRINT.md`,
> `MODULES.md`.

---

## 1. Architectural Style

HaHireAI is a **Modular Monolith**: a single deployable application, internally
partitioned into **autonomous modules** with explicit boundaries. It is **not**
flat MVC, and **not** distributed microservices. We get module independence and
clear contracts *now*, with the option to extract a module into a service later
without rewriting its consumers.

Built on **Native PHP 8.3+** with a small, purpose-built **Core Kernel** (Phase
7) — not a general framework (no Laravel/Symfony).

## 2. The Five Layers

Every module is internally layered. Dependencies point **inward and downward
only**; an outer/upper layer may depend on an inner/lower one, never the reverse.

```
┌──────────────────────────────────────────────────────────┐
│ Presentation   Controllers, view-models, CLI, HTTP I/O    │
├──────────────────────────────────────────────────────────┤
│ Application    Use cases, command/query handlers, services │
├──────────────────────────────────────────────────────────┤
│ Domain         Entities, value objects, domain services,   │
│                domain events, **contracts (interfaces)**    │
├──────────────────────────────────────────────────────────┤
│ Infrastructure Repository impls, external adapters         │
├──────────────────────────────────────────────────────────┤
│ Persistence    Database, schema, query execution           │
└──────────────────────────────────────────────────────────┘
```

**Dependency rule.** Domain depends on nothing outward. Application depends on
Domain. Infrastructure *implements* Domain contracts. Presentation depends on
Application. Persistence is reached only through Infrastructure. **Layers MUST
NOT be skipped in violation of this rule** (e.g. Presentation must not run raw
SQL).

## 3. Module Anatomy

```
/modules/<Module>/
  module.php        Manifest: name, version, dependencies, permissions, events, routes, enabled-by
  Domain/           Entities, value objects, domain services, domain events, contracts
  Application/      Use cases, command/query handlers, application services
  Infrastructure/   Repository implementations, external adapters
  Presentation/     Controllers, view-models, CLI commands
  Contracts/        PUBLIC interfaces exposed to other modules
  Resources/        views/  assets/
  Routes/           Route definitions
  Config/           Module configuration
  Permissions/      Permission definitions (feed the catalog)
  Policies/         Access policies
  Events/           Event definitions + listeners
  Database/         Migrations + seeds
  Tests/            Unit, Integration, Feature
```

A module's **public surface is its `Contracts/` namespace plus the events it
publishes** — nothing else. Entities, repositories, and handlers are internal.

## 4. Module Communication

A module **MUST NOT** call another module's internal classes or read its tables.
The only sanctioned channels are:

1. **Contracts (synchronous):** depend on another module's published interface,
   resolved from the container. Use when you need an immediate answer.
2. **Events (asynchronous, decoupled):** publish a domain event to the in-process
   **Event Dispatcher**; interested modules subscribe. Use to react to something
   that happened without coupling the actor to the reactor.
3. **Shared Services:** cross-cutting capabilities (Auth, Permissions, Files,
   Notifications, Search, AI, Logging, Cache, Validation) provided once and
   consumed via their contracts.

```
Module A ──(depends on)──▶ Module B :: Contracts\SomeService        (sync)
Module A ──(publishes)───▶ Event Bus ──(notifies)──▶ Module C listener (async)
Any Module ──────────────▶ Shared Service contract                  (cross-cutting)
```

## 5. Dependency Rules

- A module declares every dependency in `module.php`. Undeclared use is a defect.
- **No circular dependencies.** If A needs B and B needs A, invert one direction
  with an event or extract a shared contract. The dependency graph MUST be a DAG.
- Modules depend on **contracts**, not implementations; the container binds
  interface → implementation at boot.
- Optional dependencies degrade gracefully when the other module is disabled.

## 6. The Core Kernel (Phase 7)

A minimal, bespoke foundation — every component must earn its place:

| Component | Responsibility |
|---|---|
| Application Kernel | Boot orchestration; no business logic |
| Service Container | DI: singleton/transient bindings, constructor injection, interface resolution |
| Service Providers | Register bindings/services per module |
| Router + Dispatcher | GET/POST/PUT/PATCH/DELETE, named routes, groups, params, middleware registration |
| Request / Response | HTTP message abstractions |
| Configuration Loader | Load config files; no constants/globals in code |
| Environment Loader | `.env` parsing, validation, defaults |
| Event Dispatcher | dispatch + listeners (queue-ready) |
| Logger | PSR-3 levels (debug→critical), extensible |
| Error Handler | Global exceptions; dev vs prod; never leak stack traces |
| Module Registry | Explicit module registration & lifecycle |
| Health Checker | Pluggable system health probes |

Rules: follow PSR-12; strict types; `readonly` where possible; interfaces only
when needed; avoid static classes; **no facades**; **no service locator inside
business logic** (inject dependencies).

## 7. Request Lifecycle

```
public/index.php
  → Bootstrap (autoload, error handler)
  → Environment (.env)
  → Configuration
  → Application Kernel
  → Service Providers (register + boot)
  → Registered Modules
  → Router (match) → Middleware → Controller (Presentation)
  → Application use case → Domain → Infrastructure → Persistence
  → Response
```

## 8. Cross-Cutting Concerns

- **Tenant guard:** every workspace-scoped query is filtered by `workspace_id`
  at the Infrastructure layer (see `DATABASE_ARCHITECTURE.md`).
- **AuthZ:** permission checks at the Application boundary (see
  `PERMISSION_MODEL.md`).
- **Auditing, Logging, Metrics:** emitted via shared services / events, never
  hand-rolled inside modules (see `OBSERVABILITY.md`, Phase 15).
- **AI, Integrations, Workflows:** modules request *capabilities* from the
  central engines; they never embed providers or external calls (Phases 11–13).
- **Async work:** long/heavy operations run as background jobs via the queue
  (see `BACKGROUND_JOBS.md`, Phase 12).

## 9. Future Expansion Strategy

A new capability is added by creating a module that: declares its manifest,
registers its permissions/events/routes, exposes contracts, and ships its docs +
tests. It MUST require **no** change to the database engine, navigation engine,
permission engine, or other modules' internals — only additive registration.
Because boundaries are explicit, a hot module can later be extracted to a
separate service behind the same contract.

---

### Related Documents
`PROJECT_CONSTITUTION.md` · `SYSTEM_BLUEPRINT.md` · `MODULES.md` ·
`SERVICE_CONTAINER.md` · `ROUTING_GUIDE.md` · `BOOTSTRAP_FLOW.md` ·
`EVENT_BUS.md` · `DATABASE_ARCHITECTURE.md`
