# PROJECT_STATE — Nizam (Bayan AI Operating System)

> Executive, top-level snapshot of where the project stands. For the detailed
> deliverable-by-deliverable status, see [docs/20-Project-State.md](./docs/20-Project-State.md).

**Status:** Active incremental build — Phase 2 (Core Infrastructure Platform) in progress | **Version:** 2.0.0 | **Last updated:** 2026-07-01 | **Owner:** Architecture (Nizam Core)

---

## Where we are

The project has moved from **Phase 1 (architecture, docs-only)** into an **active, incremental
build** governed by the ratified [Constitution](./docs/CONSTITUTION.md). Work is delivered as
**verified, dependency-ordered increments**: each slice ships only when it is real, compiling,
test-green, and documented in the same change (Constitution §10). We favor a smaller
*fully-finished* slice over a larger *unfinished* one.

- **Product:** Nizam — the execution AI Operating System that lets a business build an **AI
  Workforce without programming**. It runs intents from the external **Bayan** brain; it is *not*
  a chatbot, *not* a RAG, and does *not* replace the brain.
- **Governing document:** the [Constitution](./docs/CONSTITUTION.md) sits above every ADR and
  document; non-technical stakeholders read [docs/PRODUCT_PRINCIPLES.md](./docs/PRODUCT_PRINCIPLES.md).
- **Stack:** a **self-built, framework-independent native PHP 8.4 platform** — no Laravel, no
  Symfony, no micro-framework on the runtime spine; only PSR **interface** packages, with every
  cross-cutting concern behind a PSR contract we own ([ADR-0015](./docs/16-ADR.md#adr-0015)).
- **Program:** a **12-phase**, dependency-ordered plan — see
  [docs/15-Project-Roadmap.md](./docs/15-Project-Roadmap.md).

## The 12-phase program (at a glance)

1. Architecture Foundation — **complete**.
2. Core Infrastructure Platform (self-built native-PHP framework) — **in progress**.
3. AI Runtime Engine + Plugin Architecture + Master Orchestrator.
4. Identity + Multi-Tenancy + Capability Engine.
5. Tool Platform / SDK.
6. Integration + Automation Platforms + n8n adapter.
7. Department Framework (SDK + empty templates).
8. AI Organization Designer (Manual / Suggestions / Assisted / Autonomous).
9. AI Providers + Knowledge Platform + Memory Engine.
10. Enterprise Platform + Marketplace + Billing + Observability + Release v1.0.
11. Business Operating System Intelligence (observe/recommend only).
12. Digital Employee Framework.

Full goals, deliverables, exit criteria, and dependencies are in
[docs/15-Project-Roadmap.md](./docs/15-Project-Roadmap.md).

## Current status

**Phase 1 — Architecture Foundation: COMPLETE.** The Constitution is ratified; the full document
set (`docs/00`–`docs/23`, plus the Constitution, product principles, roadmap, ADRs, and audits) is
approved. Rationale is recorded as ADRs in [docs/16-ADR.md](./docs/16-ADR.md).

**Phase 2 — Core Infrastructure Platform: IN PROGRESS. The foundation spine is implemented and
verified.** Delivered and test-green under `Nizam\Platform\*` and `Nizam\Kernel\*`:

- **Support** — `Result` (typed success/failure), `Uuid` (v7), `SystemClock`, `Assert`, `Str`, `Json`, helpers.
- **Exception** — typed exception hierarchy (`PlatformException` base, `ConfigException`, `InvalidArgumentException`).
- **Container** — PSR-11 container with constructor autowiring.
- **Config** — dot-access `Config` repository + `Env`.
- **Event** — PSR-14 `EventDispatcher` + `ListenerProvider` (priority, stoppable).
- **Logging** — PSR-3 `LogManager` + `Logger` with JSON-line handlers.
- **Kernel/Domain** — DDD base: `Identifier` (UUID v7), `TenantId`, `UserId`, `AggregateRoot`, `Entity`, `ValueObject`, `DomainEvent`, `RecordsDomainEvents`, `Clock` port.
- **Kernel/Application** — CQRS command/query buses (`SimpleCommandBus`, `SimpleQueryBus`).
- **Kernel/Tenancy** — `TenantContext` (+ `RequestContext`).
- **Bootstrap** — `Application` composition root wiring the foundation singletons via `CoreServiceProvider`.

**Professional Behavior Engine (Behavior bounded context) — DELIVERED and TEST-GREEN.** The
`Nizam\Behavior\*` module (`src/Behavior/`) is implemented as a full Clean/Hexagonal increment: a pure
Domain layer on the Kernel (role-bound `BehaviorProfile`/`BehaviorChangeProposal` aggregates, immutable
versioned revisions, 18 enums, self-validating `BehaviorTraits`, explainable `BehaviorRecommendation`,
consolidator/recommender domain services, ports, and `BEHAVIOR.*` exceptions); a CQRS Application layer
(draft/propose/approve/reject/rollback/archive commands, get/history/list/explain queries, read-model
DTOs, and a `BehaviorLearningService` that produces a **proposal only** and never auto-applies); and an
Infrastructure layer (tenant-scoped InMemory and PDO adapters with a JSON mapper, a Postgres-16 migration
plus runnable `SqliteSchema`, a dispatching event publisher, and the `BehaviorServiceProvider` /
`BehaviorModule` facade). Profiles are per-**role** (never per-person), evolve only from **approved**
observations, and are versioned, explainable, reversible, and approval-gated. The Interface (HTTP/Console)
layer is deferred until the HTTP platform lands. See
[docs/audit/PHASE_16_AUDIT.md](./docs/audit/PHASE_16_AUDIT.md).

**Verification (this snapshot):** the suite is **green — 169 tests, 628 assertions passing on
PHP 8.4.19 with PHPUnit 11.5.55** (`vendor/bin/phpunit`); the Behavior context added **93 tests** on top
of the prior 76-test foundation baseline; `composer` PSR-4 autoload is clean; every `src/`/`tests/` folder
carries a `README.md`.

## What is the forward plan

The remaining Phase-2 platform infrastructure — HTTP (PSR-7/17) + routing + middleware + HTTP
kernel (PSR-15), the database layer (connection, query builder, schema/migrations, repositories,
unit of work, tenant/RLS session), cache (PSR-16), queue, scheduler, validation, storage,
localization, notifications, and monitoring — plus **Phases 3–12**, are the forward work, built in
the same verified, dependency-ordered, test-green increments.

## What does NOT exist yet

Beyond the delivered foundation spine, the rest of the platform infrastructure and all of Phases
3–12 (AI Runtime, plugins, Orchestrator, identity/tenancy, tools, integrations/automation,
departments, the organization designer, providers/knowledge/memory, the enterprise
platform/marketplace/billing, business intelligence, and the digital employee framework) are the
forward plan — sequenced by dependency, each shipped only when compiled, test-green, and documented.

## Quality gate

Every increment must satisfy Constitution §10: it compiles (`php -l`), its tests are green
(`vendor/bin/phpunit`), each new folder has a `README.md`, and its documents are updated in the same
change. The Phase-1 cross-document consistency audit passed — see
[docs/audit/Architecture-Audit-Report.md](./docs/audit/Architecture-Audit-Report.md).

## Related Documents

- [README.md](./README.md) — Entry point and full index
- [docs/CONSTITUTION.md](./docs/CONSTITUTION.md) — Supreme governing document
- [docs/PRODUCT_PRINCIPLES.md](./docs/PRODUCT_PRINCIPLES.md) — Product/UX principles (non-technical)
- [docs/15-Project-Roadmap.md](./docs/15-Project-Roadmap.md) — The 12-phase program
- [docs/16-ADR.md](./docs/16-ADR.md) — Architecture Decision Records
- [docs/20-Project-State.md](./docs/20-Project-State.md) — Detailed state

## Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0.0 | 2026-07-01 | Architecture (Nizam Core) | Initial top-level project state |
| 2.0.0 | 2026-07-01 | Architecture (Nizam Core) | Moved from Phase 1 (docs-only, STOP gate) to active incremental build under the Constitution; recorded the 12-phase program, the self-built native PHP 8.4 stack (ADR-0015), and Phase-2 foundation spine implemented and verified (76 tests / 151 assertions green on PHP 8.4.19 + PHPUnit 11). |
| 2.1.0 | 2026-07-01 | Architecture (Nizam Core) | Recorded the **Professional Behavior Engine** (`Nizam\Behavior\*`, Behavior bounded context) as an implemented, test-green increment: role-bound, approval-gated, versioned, explainable, reversible profiles evolved only from approved observations; Domain/Application/Infrastructure delivered with InMemory + PDO (sqlite-validated) adapters. Suite green at **169 tests / 628 assertions** on PHP 8.4.19 + PHPUnit 11.5.55 (93 Behavior tests added). Interface/HTTP layer deferred until the HTTP platform lands. See docs/audit/PHASE_16_AUDIT.md. |
