# NEXT_PHASE — Phase 2 Entry Plan

> The scoped, gated plan for what happens *after* Phase 1 — so the next phase can begin the moment approval is given, without re-deriving intent.

**Status:** Approved (Phase 1) | **Version:** 1.0.0 | **Last updated:** 2026-07-01 | **Owner:** Architecture (Nizam Core)

---

## 0. Gate Notice

> **Phase 1 is complete and this project is STOPPED at the phase gate.**
> Phase 2 must **not** begin until the owner gives explicit approval. This document
> describes Phase 2 so it is ready to start on approval — it is **not** authorization
> to start.

## 1. Phase 2 Objective

**Build the foundation runtime: the Core Kernel and Identity & Access, on a
production-grade multi-tenant substrate.**

Phase 2 turns the Phase 1 architecture into the first running (but not yet
feature-complete) slice of Nizam: a bootable modular-monolith skeleton where a tenant
and user can exist, authenticate, be authorized, and where the cross-cutting plumbing
(events, outbox, RLS, observability) is real and verifiable — with **no** business
features yet.

## 2. Scope (In)

Aligned with `15-Project-Roadmap.md` Phase 2:

1. **Repository & tooling foundation**
   - Monorepo per `14-Folder-Structure.md` (`public/`, `src/Kernel` + `src/Modules/<Context>`, `bin/` workers, `config/`, `migrations/`, `infra/`).
   - PHP 8.3+ (strict types), PHP-CS-Fixer + PHPStan/Psalm, Conventional Commits, CI pipeline (build,
     lint, test, migrations check) per `13-Coding-Standards.md`.
2. **Core (Kernel) bounded context**
   - Shared kernel: base Entity/Aggregate/Value-Object, `Result`/typed-error types,
     Clock, UUID v7 ID generation, tenant context (per-request RequestContext/TenantContext service), domain-event
     base + in-process dispatcher.
   - Transactional **Outbox** table + relay to **NATS JetStream**; idempotent consumer
     scaffold; DLQ.
3. **Identity & Access (IAM) bounded context**
   - Aggregates: Tenant, User, Organization, Role, Permission, Session.
   - OAuth2/OIDC + JWT (access/refresh), password/MFA, RBAC + ABAC policy evaluation.
   - Tenant provisioning/suspension lifecycle (state machine from `09-Multi-Tenant.md`).
4. **Multi-tenant data substrate**
   - PostgreSQL 16 with **forced RLS**, `app.current_tenant` GUC propagation, per-table
     `tenant_id`, soft delete, audit columns — realized as **migrations** (expand/contract).
   - Pool tier only in Phase 2; Bridge/Silo deferred.
5. **Cross-cutting foundations**
   - OpenTelemetry tracing/metrics/logs; Monolog (PSR-3) structured logging; correlation IDs.
   - OpenAPI 3.1 surface for IAM; error envelope; idempotency-key middleware.
   - Secrets accessed via an abstracted provider (backend chosen — see entry criteria).

## 3. Scope (Out — deferred to Phase 3+)

- Agent Framework runtime, Tool Registry, Automation Engine / n8n integration.
- External Integrations connectors, Billing/metering, Notifications, Settings UI,
  Administration back-office, product UI screens.
- Bridge/Silo tenant tiers; cross-region residency.

## 4. Entry Criteria (must be true before Phase 2 starts)

1. **Owner approval** of Phase 1 and authorization to build.
2. **Q-01 resolved:** the Bayan **intent contract** shape is confirmed (needed for
   Phase 3, but confirming direction de-risks Kernel event modeling).
3. **Q-05 resolved:** secrets backend selected (HashiCorp Vault vs cloud KMS).
4. **SLO targets** for auth and core APIs set to concrete numbers.
5. Target cloud/runtime (Kubernetes flavor, managed Postgres) confirmed.

Items 2–5 are tracked in `19-Assumptions.md`. Where a decision is still open at start,
it becomes an ADR in `16-ADR.md` before the affected code is written.

## 5. Exit Criteria (Phase 2 is done when)

- A tenant and user can be provisioned, authenticated, and authorized end-to-end.
- RLS provably prevents cross-tenant reads/writes (automated isolation test in CI).
- A domain event flows: aggregate change → outbox → JetStream → idempotent consumer,
  observable in traces.
- Migrations run clean forward; expand/contract pattern demonstrated.
- Coverage targets met (domain ≥ 90%); CI green; every module ships a `README.md`.
- Docs updated in the same tasks that add behavior (constitution rule).

## 6. Constitution Reminders for Phase 2

- Never skip architecture; every decision becomes an ADR.
- SOLID, DDD, EDA, Clean Architecture — non-negotiable.
- No placeholder/TODO/fake code; production-ready or not merged.
- Update related docs in the same task; every module documented.
- Do not advance to Phase 3 until Phase 2 exit criteria pass and approval is given.

## 7. First Concrete Steps (on approval)

1. Scaffold monorepo and CI (`14-Folder-Structure.md`, `13-Coding-Standards.md`).
2. Implement Core Kernel primitives + Outbox/JetStream plumbing with tests.
3. Implement IAM aggregates, AuthN/AuthZ, and tenant provisioning behind RLS.
4. Write the automated cross-tenant isolation test as the Phase-2 quality gate.
5. Update `20-Project-State.md` and open `21`/context docs deltas as behavior lands.

## Related Documents

- [15-Project-Roadmap.md](./15-Project-Roadmap.md) — Full phase plan
- [20-Project-State.md](./20-Project-State.md) — Current state
- [05-Bounded-Contexts.md](./05-Bounded-Contexts.md) — Core & IAM context specs
- [09-Multi-Tenant.md](./09-Multi-Tenant.md) — RLS & tenancy foundation
- [12-Event-Architecture.md](./12-Event-Architecture.md) — Outbox & JetStream
- [19-Assumptions.md](./19-Assumptions.md) — Open questions to resolve at entry

## Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0.0 | 2026-07-01 | Architecture (Nizam Core) | Initial Phase 2 entry plan |
