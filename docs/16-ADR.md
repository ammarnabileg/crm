# Architecture Decision Records (ADR) Log — Nizam AIOS

One-line purpose: The authoritative, append-only log of significant architecture decisions for **Nizam — the Bayan AI Operating System**, capturing context, decision, consequences, and rejected alternatives for every fixed choice.

> **Status: Approved (Phase 1) | Version: 1.0.0 | Last updated: 2026-07-01 | Owner: Architecture (Nizam Core)**

---

## How to Read This Log

Each record follows a standard ADR format: **Title, Status, Context, Decision, Consequences, Alternatives considered.** All records below reflect *fixed* canonical decisions and carry status **Accepted**. New decisions append new IDs; superseding decisions references the ID it replaces. ADRs are immutable once Accepted — a reversal is a new ADR, not an edit.

### Index

| ID | Title | Status |
|----|-------|--------|
| ADR-0001 | Modular monolith first (NestJS) | Accepted |
| ADR-0002 | PostgreSQL 16 + RLS shared-schema multi-tenancy | Accepted |
| ADR-0003 | UUID v7 primary keys | Accepted |
| ADR-0004 | Event-driven with Transactional Outbox + NATS JetStream | Accepted |
| ADR-0005 | n8n as automation execution substrate behind an Automation Engine | Accepted |
| ADR-0006 | Bayan accessed via an Anti-Corruption Layer (Bayan Gateway) | Accepted |
| ADR-0007 | Clean Architecture + DDD + Hexagonal per module | Accepted |
| ADR-0008 | RBAC + ABAC authorization | Accepted |
| ADR-0009 | Claude (Anthropic API) behind an `LlmProvider` port | Accepted |
| ADR-0010 | OpenAPI 3.1 REST + AsyncAPI events + optional GraphQL BFF | Accepted |
| ADR-0011 | Soft delete + audit columns + event sourcing for critical aggregates | Accepted |
| ADR-0012 | Basic/Advanced mode + Wizard-driven UX | Accepted |
| ADR-0013 | Tenant tiers: Pool / Bridge / Silo | Accepted |

---

## ADR-0001 — Modular Monolith First (NestJS)

**Status:** Accepted

**Context.** Nizam spans twelve bounded contexts. A distributed system from day one would multiply operational surface (networking, deployment, distributed tracing, partial-failure handling) while the domain model is still stabilizing. The team needs fast iteration, transactional consistency within a context, and low operational overhead, without foreclosing later extraction to services.

**Decision.** Build a **modular monolith** in **NestJS** (Node.js 22 LTS, TypeScript 5.x strict). Each bounded context is a NestJS module with strict internal boundaries (Clean Architecture layering, ports & adapters). Modules communicate through explicit contracts and integration events, never by reaching into each other's internals, so any module can later be extracted into an independent service with minimal churn.

**Consequences.**
- (+) Single deploy unit; in-process calls; local transactions within a context; simpler local dev.
- (+) Boundaries enforced in code (module APIs + events) preserve the option to extract services later.
- (−) A shared runtime means a fault can affect the whole process until modules are hardened/isolated.
- (−) Requires discipline: without enforced boundaries a monolith degrades into a "big ball of mud."

**Alternatives considered.**
- **Microservices-first:** rejected — premature; high operational cost and distributed-systems complexity before the domain is proven.
- **Serverless functions per use case:** rejected — cold starts, fragmented transactions, and weak local dev ergonomics for a stateful, event-driven core.

---

## ADR-0002 — PostgreSQL 16 + RLS Shared-Schema Multi-Tenancy

**Status:** Accepted

**Context.** Nizam is multi-tenant B2B. It needs strong tenant isolation, relational integrity, JSON flexibility, and vector search (for agent memory/semantic features). Isolation must be enforceable at the data layer, not only in application code, to defend against application bugs.

**Decision.** Use **PostgreSQL 16** with **Row-Level Security (RLS)** as the enforcing isolation boundary on a **shared database, shared schema**. Every tenant-scoped table carries `tenant_id UUID NOT NULL`; RLS policies bind queries to the current tenant context. Enable `pgvector` for embeddings. Offer schema-per-tenant and database-per-tenant as scaling tiers (see ADR-0013).

**Consequences.**
- (+) Defense in depth: isolation enforced in the database even if application code is flawed.
- (+) One schema to migrate; efficient resource pooling for many small tenants.
- (+) Relational integrity, transactions, JSONB, and vectors in one engine.
- (−) RLS misconfiguration is a critical cross-tenant leakage risk (tracked in `18-Risks.md`); demands rigorous testing and a mandatory tenant-context guard.
- (−) Very large tenants may contend for shared resources until moved to a higher tier.

**Alternatives considered.**
- **MongoDB / document store:** rejected — weaker relational integrity and transactional guarantees; RLS-equivalent isolation is not first-class; separate vector solution needed.
- **Schema-per-tenant only:** rejected as the default — migration and connection overhead at high tenant counts; retained as a *tier*, not the baseline.
- **Application-only tenant filtering (no RLS):** rejected — a single missing `WHERE tenant_id` becomes a breach.

---

## ADR-0003 — UUID v7 Primary Keys

**Status:** Accepted

**Context.** Primary keys must be globally unique across tenants and future extracted services, safe to generate client- or app-side, and index-friendly. Random UUIDv4 keys fragment B-tree indexes and hurt insert locality; sequential integers leak counts, are guessable, and collide across services.

**Decision.** Use **UUID v7** (time-ordered) as the primary key type for all aggregates, column name `id`. UUIDv7 embeds a timestamp prefix, giving monotonic-ish ordering and good index locality while remaining globally unique and non-guessable in the random suffix.

**Consequences.**
- (+) Better index locality and insert performance than v4; time-sortable for range scans and pagination.
- (+) Safe to generate before a DB round-trip; no cross-service collision; no enumeration of counts.
- (−) 16 bytes vs 8 for bigint (larger indexes); acceptable trade-off.
- (−) Some tooling still assumes v4/v1; requires a vetted generation library.

**Alternatives considered.**
- **Sequential/auto-increment integers:** rejected — enumeration leaks, cross-service collisions, coupling to a single sequence.
- **UUID v4:** rejected — random ordering fragments indexes and worsens write amplification at scale.
- **ULID / KSUID:** viable and similar in spirit, but UUIDv7 is a standard UUID variant with native `uuid` column support; chosen for ecosystem fit.

---

## ADR-0004 — Event-Driven with Transactional Outbox + NATS JetStream

**Status:** Accepted

**Context.** State changes in one context must reliably reach others (Monitoring, Billing metering, Notifications, Audit) without dual-write inconsistency (updating the DB and publishing an event non-atomically risks lost or phantom events). The system is async-by-default with saga-based cross-context flows.

**Decision.** Adopt the **Transactional Outbox** pattern: domain state changes and their outbound events are written in the *same* database transaction; a relay publishes committed outbox rows to the broker. The broker is **NATS JetStream** (primary), with **Redis Streams** as a lightweight local/dev fallback. Integration events are versioned via AsyncAPI + a schema registry.

**Consequences.**
- (+) Atomic state-change-plus-event; no lost or ghost events; at-least-once delivery with idempotent consumers.
- (+) NATS JetStream is lightweight, high-throughput, with persistence, replay, and consumer groups.
- (−) At-least-once implies duplicates: consumers must be idempotent (idempotency keys), and side effects must tolerate re-delivery (tracked in `18-Risks.md`).
- (−) Outbox relay adds a moving part; ordering guarantees are per-subject/partition, not global.

**Alternatives considered.**
- **Apache Kafka:** rejected for now — heavier to operate; NATS JetStream meets throughput/persistence needs with far lower operational weight. Revisitable if ecosystem/connector needs grow.
- **Direct publish (no outbox):** rejected — dual-write inconsistency.
- **RabbitMQ:** rejected — capable, but NATS fits the lightweight, cloud-native, multi-pattern (pub/sub + streams + KV) profile better.

---

## ADR-0005 — n8n as Automation Execution Substrate Behind an Automation Engine

**Status:** Accepted

**Context.** Non-technical operators need to compose and run integrations/workflows. Building a bespoke workflow engine is costly; a mature, extensible, self-hostable engine accelerates delivery. But coupling the whole system to a third-party engine is risky.

**Decision.** Use **self-hosted n8n** as the workflow executor, wrapped by an internal **Automation Engine** service. Nizam code talks only to the Automation Engine's contract; n8n sits behind an adapter (ACL). The Automation Engine owns triggers, run history, retries, idempotency, and compensation; n8n is a replaceable execution substrate.

**Consequences.**
- (+) Fast time-to-value; large connector ecosystem; visual workflows suit the non-technical persona.
- (+) The adapter boundary makes n8n replaceable (e.g., with Temporal or a native engine) without touching callers.
- (−) n8n becomes a critical dependency and potential single point of failure; must be run HA and sandboxed. n8n's code-node capability is an arbitrary-code risk requiring hardening (tracked in `18-Risks.md`).
- (−) Version/API drift in n8n must be absorbed by the adapter.

**Alternatives considered.**
- **Temporal:** rejected as the primary substrate — excellent for durable orchestration but code-first, less suited to operator-composed visual workflows; retained as a candidate replacement behind the same adapter.
- **Build a native engine now:** rejected — high cost, reinventing a mature category during early phases.
- **AWS Step Functions / cloud-proprietary:** rejected — vendor lock-in and self-hosting requirement.

---

## ADR-0006 — Bayan Accessed via an Anti-Corruption Layer (Bayan Gateway)

**Status:** Accepted

**Context.** **Bayan** (the AI Brain) already exists and is external to Nizam. It performs reasoning/planning/NLU and emits *intents*. Nizam is the execution layer. Coupling Nizam to Bayan's internal models would make both brittle and hard to evolve independently.

**Decision.** Access Bayan exclusively through the **Bayan Gateway**, an **Anti-Corruption Layer (ACL)** in the AI bounded context. The Gateway validates a stable **Intent contract**, attaches tenant context, assembles execution context, and shapes responses. Nizam never depends on Bayan internals; it depends only on the Intent contract.

**Consequences.**
- (+) Bayan and Nizam evolve independently; the Intent contract is the only coupling point.
- (+) A single, testable boundary for validation, tenancy injection, and translation.
- (−) The Intent contract must be stable and versioned; its exact schema is an Open Question deferred to a later phase (see `19-Assumptions.md`).
- (−) Bayan availability directly affects intent intake; Gateway must degrade gracefully (tracked in `18-Risks.md`).

**Alternatives considered.**
- **Direct integration with Bayan internals:** rejected — tight coupling, brittle, violates DDD context boundaries.
- **Merging Bayan into Nizam:** rejected — Bayan is external, already exists, and is explicitly out of scope to build.

---

## ADR-0007 — Clean Architecture + DDD + Hexagonal per Module

**Status:** Accepted

**Context.** Twelve contexts must remain independently understandable, testable, and replaceable. Business rules must not depend on frameworks, databases, or transport. The system must be technology-agnostic at its core.

**Decision.** Apply **Clean Architecture** layering inside every module: `domain/` (entities, value objects, domain events, ports) → `application/` (use cases, command/query handlers, DTOs) → `infrastructure/` (DB repos, brokers, HTTP clients) → `interface/` (controllers, resolvers, event consumers, CLI). The **Dependency Rule** holds: dependencies point inward only; domain depends on nothing; outer layers depend inward via **ports (Hexagonal)**. Use **DDD** tactical patterns and **CQRS** where read/write asymmetry warrants it.

**Consequences.**
- (+) Framework/DB independence; domain is unit-testable without infrastructure.
- (+) Adapters (DB, broker, LLM, n8n) are swappable behind ports — enables ADR-0005/0006/0009.
- (−) More boilerplate and indirection; a learning curve for contributors new to the style.
- (−) Requires enforcement (lint rules / architecture tests) to prevent inward-rule violations.

**Alternatives considered.**
- **Layered/transaction-script or anemic MVC:** rejected — business logic leaks into controllers/ORM, poor testability, framework coupling.
- **Framework-driven structure (organize by NestJS convention only):** rejected — couples domain to the framework, undermines extractability (ADR-0001).

---

## ADR-0008 — RBAC + ABAC Authorization

**Status:** Accepted

**Context.** Authorization spans tenants, orgs, roles, and fine-grained, context-dependent rules (e.g., "an operator may run a tool only on resources in their org, in Advanced mode, during business hours"). Roles alone are too coarse; attributes alone are hard to administer.

**Decision.** Combine **RBAC** (roles → permissions, easy to administer) with **ABAC** (attribute/policy-based rules evaluated against subject, resource, action, and environment attributes). Enforce at **two layers**: the API layer (policy checks in the application layer) and the **DB layer via RLS** (tenant boundary, ADR-0002). AuthN is OAuth2/OIDC with JWT access+refresh and mTLS service-to-service.

**Consequences.**
- (+) Coarse-grained administration (roles) plus fine-grained control (policies) — best of both.
- (+) Defense in depth: even a policy bug cannot cross the RLS tenant boundary.
- (−) Two mechanisms to keep coherent; policy evaluation adds latency and needs caching.
- (−) ABAC policies can grow complex; require a clear policy model and testing.

**Alternatives considered.**
- **RBAC only:** rejected — role explosion to express fine-grained/contextual rules.
- **ABAC only:** rejected — hard to administer and reason about for non-technical admins.
- **ACLs per resource:** rejected — unmanageable at multi-tenant scale.

---

## ADR-0009 — Claude (Anthropic API) Behind an `LlmProvider` Port

**Status:** Accepted

**Context.** When Nizam itself needs an LLM (e.g., synthesizing tool arguments, shaping context), it must call a model. Reasoning/planning proper belongs to Bayan; Nizam's LLM use is auxiliary. The provider must be replaceable and must not couple the domain to a vendor SDK.

**Decision.** Use **Claude models via the Anthropic API** (defaulting to the latest Claude models) for Nizam's own LLM needs, accessed through an **`LlmProvider` port**. The concrete Anthropic adapter lives in infrastructure; the domain/application layers depend only on the port. Model IDs and parameters are configuration, not code constants baked into the domain.

**Consequences.**
- (+) Vendor-replaceable: swap Claude for another provider by implementing the port.
- (+) Centralized place for token metering (feeds Billing), rate limiting, retries, and prompt-injection guardrails.
- (−) External API dependency: availability, latency, cost, and rate limits must be managed (tracked in `18-Risks.md`).
- (−) Provider capability differences (tool use, context window) may leak through the port; the port must model a sensible common denominator.

**Alternatives considered.**
- **Direct SDK calls from domain/application code:** rejected — vendor coupling, no central metering/guardrails.
- **Self-hosted open model:** rejected for now — operational cost and quality trade-offs; the port keeps this option open.
- **Multiple providers with routing from day one:** deferred — added complexity before it is needed; the port permits it later.

---

## ADR-0010 — OpenAPI 3.1 REST + AsyncAPI Events + Optional GraphQL BFF

**Status:** Accepted

**Context.** Nizam exposes synchronous request/response APIs to clients and asynchronous events between contexts. Contracts must be machine-readable, versioned, and drive documentation, client generation, and validation. A rich, aggregating UI may benefit from a tailored query layer.

**Decision.** Use **OpenAPI 3.1** as the contract for **REST** (URL-versioned, `/v1`), and **AsyncAPI 2.6** as the contract for **events** (with a schema registry for event versioning). A **GraphQL Backend-for-Frontend (BFF)** is permitted as an **optional** aggregation layer for the UI, sitting in front of the REST/application layer — not as the primary inter-service contract.

**Consequences.**
- (+) Contract-first: generated clients/docs, request/response validation, event schema governance.
- (+) REST is simple and cache-friendly for core APIs; AsyncAPI documents the event backbone (ADR-0004).
- (+) An optional GraphQL BFF reduces over-/under-fetching for complex UI screens without imposing GraphQL system-wide.
- (−) Maintaining two (or three) contract styles requires discipline and tooling.
- (−) Whether the GraphQL BFF is adopted is an Open Question deferred to the UI phase (see `19-Assumptions.md`).

**Alternatives considered.**
- **GraphQL as the single primary API:** rejected as the baseline — caching, versioning, and simplicity favor REST for core contracts; GraphQL retained only as an optional BFF.
- **gRPC everywhere:** rejected for external/UI contracts — poorer browser and public-API ergonomics; may still be used internally between extracted services later.

---

## ADR-0011 — Soft Delete + Audit Columns + Event Sourcing for Critical Aggregates

**Status:** Accepted

**Context.** The platform must be auditable (who changed what, when), recoverable from accidental deletion, and able to reconstruct the exact history of high-stakes operations (agent runs, tool executions, automations) for debugging, compliance, and metering.

**Decision.** Standardize three mechanisms:
1. **Soft delete** — `deleted_at TIMESTAMPTZ NULL` with partial indexes excluding deleted rows (no hard deletes for tenant data by default).
2. **Audit columns + audit_log** — every table carries `created_at, updated_at, created_by, updated_by`; an append-only `audit_log` table records state changes; `version INT` provides optimistic concurrency where needed.
3. **Event sourcing** for **critical aggregates** (Agent runs, Tool executions, Automations) — their state is derived from an append-only event stream.

**Consequences.**
- (+) Full auditability and point-in-time reconstruction of critical flows; safe recovery from accidental deletes.
- (+) Event-sourced aggregates are a natural source for metering (Billing) and monitoring.
- (−) Soft delete complicates queries and uniqueness constraints (must account for `deleted_at`).
- (−) Event sourcing adds design complexity (projections, replay, schema evolution) and is therefore scoped to *critical* aggregates only, not the whole system.

**Alternatives considered.**
- **Hard delete + no audit:** rejected — irrecoverable data loss and no compliance trail.
- **Event sourcing everywhere:** rejected — excessive complexity for CRUD-style contexts (Settings, Notifications).
- **CDC/binlog-only auditing:** rejected as the sole mechanism — captures rows, not domain intent; domain events + `audit_log` capture meaning.

---

## ADR-0012 — Basic/Advanced Mode + Wizard-Driven UX

**Status:** Accepted

**Context.** Target users are **non-technical**. Complex configuration (integrations, credentials, automations) overwhelms them, while power users still need full control. Long forms and jargon cause errors and abandonment.

**Decision.** Provide a **Basic Mode** (default, simplified) and a hidden-by-default **Advanced Mode**. Complex settings are delivered through **Wizards** (guided, step-by-step) rather than long forms. Every screen has a clear title and one-line plain-language explanation; every input has a readable label and a (!) help icon opening a popup (what/why/example/required/where-to-get-it/common mistakes/security warning). UI is bilingual **AR/EN** with correct RTL/LTR.

**Consequences.**
- (+) Lower error rate and cognitive load for the primary persona; power remains available in Advanced mode.
- (+) Wizards encode correct configuration order and validation, reducing misconfiguration.
- (−) Two modes and rich help increase UI implementation and content effort (help text, translations).
- (−) Maintaining parity/consistency between modes requires design discipline.

**Alternatives considered.**
- **Single expert UI:** rejected — hostile to non-technical users; high error and support burden.
- **Fully guided only (no advanced):** rejected — blocks power users and edge-case configuration.

---

## ADR-0013 — Tenant Tiers: Pool / Bridge / Silo

**Status:** Accepted

**Context.** Tenants range from many small B2B orgs to a few large, isolation- or performance-sensitive customers. A single isolation strategy cannot serve both cost-efficiency and strong-isolation needs.

**Decision.** Offer three tenant tiers over the same codebase:
- **Pool** (default) — shared database, shared schema, isolation by RLS (ADR-0002). Most cost-efficient; best for many small tenants.
- **Bridge** — shared database, **schema-per-tenant**. Stronger isolation and per-tenant tuning at moderate overhead.
- **Silo** — **database-per-tenant** (or dedicated instance). Strongest isolation and performance; highest cost; for large or regulated tenants.

Tier is a provisioning/configuration concern; application code is tier-agnostic and resolves the tenant's connection/context at runtime.

**Consequences.**
- (+) One product serves cost-sensitive and isolation-sensitive customers; tenants can be promoted between tiers.
- (+) Supports data-residency and compliance needs at the Silo tier.
- (−) Migrations must run across N schemas/databases at higher tiers; tooling must fan out.
- (−) Connection management and routing grow more complex as Bridge/Silo tenants accumulate.

**Alternatives considered.**
- **Pool-only:** rejected — cannot satisfy strong-isolation, performance, or residency requirements of large/regulated tenants.
- **Silo-only:** rejected — prohibitively costly and operationally heavy for many small tenants.
- **Separate codebase per tier:** rejected — duplication and drift; tiers must share one code path.

---

## Related Documents

- `docs/00-Vision.md` — Product vision.
- `docs/15-Project-Roadmap.md` — Phased roadmap.
- `docs/18-Risks.md` — Risks arising from these decisions.
- `docs/19-Assumptions.md` — Assumptions and deferred/open questions referenced above.
- `docs/21-Database-Design.md` — Data model realizing ADR-0002/0003/0011/0013.
- `docs/22-UIUX-Guidelines.md` — UX realizing ADR-0012.

---

## Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0.0 | 2026-07-01 | Architecture (Nizam Core) | Initial ADR log: ADR-0001 through ADR-0013 recorded and Accepted. |
