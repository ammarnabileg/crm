# FEATURE SPEC — Core Kernel

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Core Kernel · **Layer:** Foundation · **Implemented in:** Phase 7
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Core Kernel is the **bespoke runtime foundation** of HaHireAI: a minimal,
purpose-built core (not a general framework) on which every other module runs. It
orchestrates application boot, provides dependency injection, routes HTTP
requests through the five layers, loads configuration and environment, dispatches
domain events in-process, logs via a PSR-3 interface, handles errors safely, and
registers/discovers modules through an explicit registry. Per `ARCHITECTURE.md`
§6, every component MUST earn its place. The Kernel contains **no business
logic** of any kind; it is wiring, lifecycle, and cross-cutting plumbing only.

## 2. Scope

**In scope**
- Application Kernel: boot orchestration and request lifecycle (`ARCHITECTURE.md` §7).
- Service Container: singleton/transient bindings, constructor injection, interface→implementation resolution.
- Service Providers: per-module registration and boot hooks.
- Router + Dispatcher: GET/POST/PUT/PATCH/DELETE, named routes, groups, params, middleware registration.
- Request / Response: HTTP message abstractions.
- Configuration Loader and Environment Loader (`.env` parsing, validation, defaults).
- Event Dispatcher: dispatch + listeners, queue-ready.
- Logger: PSR-3 levels (debug→critical), extensible handlers.
- Error Handler: global exception handling with distinct dev vs prod behavior.
- Module Registry: explicit registration and lifecycle (no filesystem guessing).
- Health Checker: pluggable system health probes.

**Out of scope**
- Any domain/business behavior (jobs, applications, billing, AI, etc.).
- Database connectivity, schema, migrations, repositories — owned by the **Database** module (Phase 8).
- Authentication, permissions, sessions — owned by Identity & Access modules.
- The queue/worker runtime itself — the dispatcher is *queue-ready*, but background execution is the Workflow Engine (Phase 12).
- Facades or a service locator inside business logic (explicitly forbidden by `ARCHITECTURE.md` §6).

## 3. Inputs

- The inbound HTTP request via the single front controller `/public/index.php` (Constitution §4).
- Environment variables (`.env`) and configuration files under `/config` and per-module `Config/`.
- Module manifests (`module.php`) declaring providers, routes, events, permissions, dependencies.
- Console invocations from `/bin` (CLI entrypoints) routed through the same Kernel boot.

## 4. Outputs

- A fully booted application object graph with all bindings registered and modules booted in dependency order.
- A dispatched HTTP **Response** (or a safe error response) for each request.
- Structured log records emitted through the PSR-3 logger.
- Dispatched domain events delivered to subscribed listeners.
- Health-probe results aggregated by the Health Checker (consumed by Installer and Observability).

## 5. Dependencies (modules + contracts consumed; shared services used)

- **None at the module layer.** Core Kernel sits at the bottom of the Foundation
  layer; per `MODULES.md` §5, *everything depends on Core Kernel* and Core Kernel
  depends on no other module.
- Consumes the **Shared Kernel** (`/shared`) for primitives (ULID, `Result`,
  `Clock`, base contracts, helpers) — these are shared primitives, not a module.
- It **provides**, and does not consume, the cross-cutting capabilities **Event
  Bus**, **Logging**, and the container that all other shared services bind into.

## 6. Permissions (keys this module declares; resource.action grammar)

The Core Kernel performs **no authorization** and declares **no workspace
permissions** (deny-by-default checks happen at the Application boundary of each
business module, per `PERMISSION_MODEL.md` §5). It exposes operational probes
guarded by the System Administration console:

- `system.health.view` — view aggregated health-check results (Platform Context).
- `system.diagnostics.run` — execute on-demand health/diagnostic probes (Platform Context).

> These are consumed by System Administration / Observability; the Kernel only
> *publishes the probe surface*. It MUST NOT branch on role names (Constitution §15).

## 7. Events (Published / Subscribed)

**Published** (lifecycle/system events; `module.entity.event`, past tense)
- `kernel.application.booted` — emitted after all providers and modules have booted.
- `kernel.request.handled` — emitted after a response is produced for a request.
- `kernel.exception.captured` — emitted when the global error handler captures an unhandled throwable.

**Subscribed**
- None. The Kernel *provides* the Event Dispatcher; it does not subscribe to other modules' events.

## 8. Data Owned (conceptual entities only — defer detail to DATABASE_ARCHITECTURE.md, Phase 3)

The Core Kernel is **stateless** and owns **no database tables**. Its runtime
constructs are in-memory and configuration-driven, not persisted:

- **Module Registration** — the registered set of modules and their boot order (derived from manifests at boot).
- **Service Binding** — container binding descriptors (interface→implementation, singleton/transient).
- **Route Definition** — registered routes (method, path, name, middleware, handler).
- **Health Probe** — registered probe definitions and their last in-memory result.

Any persistence (e.g. a migrations ledger) belongs to the **Database** module.

## 9. Acceptance Criteria (checklist of testable outcomes)

- [ ] Booting from `/public/index.php` follows the exact lifecycle in `ARCHITECTURE.md` §7 (bootstrap → env → config → kernel → providers → modules → router → middleware → controller → response).
- [ ] The container resolves an interface to its bound implementation, supports **singleton** and **transient** lifetimes, and performs **constructor injection** without a service locator in business code.
- [ ] The router matches all of GET/POST/PUT/PATCH/DELETE, supports named routes, route groups, path parameters, and per-route middleware registration.
- [ ] Configuration is read **only** through the Configuration Loader; no business code reads constants/globals or `getenv()` directly.
- [ ] The Environment Loader parses `.env`, validates required keys, applies defaults, and fails fast with a clear message on missing required configuration.
- [ ] The Event Dispatcher delivers a published event to all subscribed listeners and is queue-ready (a listener MAY be marked for async handling).
- [ ] The Logger implements the PSR-3 interface across all levels (debug→critical) and supports pluggable handlers.
- [ ] In **production** the Error Handler renders a safe error page and **never leaks stack traces, secrets, or internal detail**; in **development** it shows diagnostic detail (`ARCHITECTURE.md` §6).
- [ ] Modules are discovered **only** via the Module Registry from declared manifests — never by filesystem guessing (`MODULES.md` §6) — and boot in dependency order with no cycles.
- [ ] The Health Checker runs all registered probes and returns an aggregated result consumable by the Installer (Phase 8) and Observability (Phase 15).
- [ ] Every Kernel PHP file declares `strict_types=1`, follows PSR-12, and uses `readonly` where possible; no facades and no service locator inside business logic.
- [ ] The Kernel contains no business logic and owns no database tables.

### Related Documents
`ARCHITECTURE.md` · `MODULES.md` · `SYSTEM_BLUEPRINT.md` · `PROJECT_CONSTITUTION.md` ·
`SERVICE_CONTAINER.md` · `ROUTING_GUIDE.md` · `BOOTSTRAP_FLOW.md` · `EVENT_BUS.md` ·
`Database.md` · `Installer.md`
