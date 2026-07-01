# Glossary — Ubiquitous Language Dictionary

> The single, alphabetical, authoritative dictionary of every key term used across Nizam — the Bayan AI Operating System. If a term is used in any document, code, or diagram, it is defined here.

**Status:** Approved (Phase 1) | **Version:** 1.0.0 | **Last updated:** 2026-07-01 | **Owner:** Architecture (Nizam Core)

---

## How to use this glossary

- This is the canonical **ubiquitous language**. Definitions here override any informal usage.
- One term = one meaning. Where a word means different things in different bounded contexts, the context is stated explicitly.
- New terms enter this glossary **before** they enter a diagram or a module.
- Product-name note (AR/EN): **Bayan** = بيان (a clear statement/exposition); **Nizam** = نظام (system/order). Internal code identifiers stay in English; user-facing surfaces are bilingual AR/EN with RTL/LTR support.

---

## A

**ABAC (Attribute-Based Access Control)** — Authorization model that grants or denies access based on evaluated *attributes* (of the user, resource, tenant, action, environment) via policy statements. Used alongside RBAC and enforced in IAM at both the API layer and the DB (RLS).

**ACL — see Anti-Corruption Layer.**

**Adapter** — An infrastructure-layer implementation of a **Port**. It translates between Nizam's domain model and an external technology or system (DB, broker, HTTP API). Adapters are replaceable; the domain depends only on the port.

**Advanced Mode** — The optional UI mode exposing power-user controls. Hidden by default. Contrast **Basic Mode**. Owned by the Settings context (`AppMode` VO).

**Aggregate** — A cluster of entities and value objects treated as a single **consistency unit**. External code references only the aggregate root. One aggregate is mutated per transaction; the root enforces all invariants.

**Aggregate Root** — The single entry-point entity of an aggregate. All external references and all invariant enforcement go through the root (e.g., `AgentRun`, `ToolDefinition`).

**Agent** — A configured actor in the **Agent Framework** that turns a validated **Intent** into executed work by planning and invoking **Tools** under guardrails. Defined by an `AgentDefinition`; each execution is an `AgentRun`.

**Agent Framework** — The **Agents** bounded context (a Core domain): agent definitions, planning-to-execution orchestration, memory scoping, guardrails, and run history.

**Agent Run** — A single execution instance of an agent against one Intent, modeled as the `AgentRun` aggregate. Event-sourced; progresses through plan steps and emits `AgentRunStarted/Completed/Failed`.

**AI Operating System (AIOS)** — See **Nizam**. The execution layer that receives intents from the Bayan brain and runs them safely, observably, and multi-tenant. Short form: **Nizam AIOS**.

**Anti-Corruption Layer (ACL)** — A translation boundary that protects Nizam's model from a foreign/external model. Mandated for the **Bayan Gateway** (toward the Bayan brain and the LLM vendor) and for **Integrations**/the **n8n adapter** (toward external systems). Foreign types stop at the ACL.

**Application Service** — A use-case orchestrator in the `application/` layer. It opens the transaction, loads aggregates via repositories, invokes domain behavior, and publishes events. It holds *no* business rules and is the only place that enforces one-aggregate-per-transaction.

**AsyncAPI** — The specification (version 2.6) used to describe Nizam's event/message contracts (the Published Language for events on NATS JetStream).

**Automation** — (1) The **Automation** bounded context, a.k.a. the **Automation Engine** (Core domain). (2) An `AutomationRun` — one execution of a workflow. Provides durable, idempotent, compensable execution over n8n.

**Automation Engine** — The internal service/context that owns and drives **n8n**: workflow definitions, triggers, retries, idempotency, run history, and compensation. n8n sits behind a `WorkflowExecutor` port so it is replaceable.

**Automation Run** — The `AutomationRun` aggregate: one execution of a `WorkflowDefinition`, with run steps, retries, and compensation steps.

**Audit Log** — An append-only record of significant actions (`audit_log` table) complementing domain events. Critical aggregates (agent runs, tool executions, automations) additionally use event sourcing.

## B

**Basic Mode** — The default UI mode for non-technical users: simplified, jargon-free, wizard-driven. Contrast **Advanced Mode**. Owned by Settings.

**Bayan** — (Arabic **بيان**, "a clear statement/exposition.") The **AI Brain**. It is **external and pre-existing** — Nizam does not build it. Bayan performs reasoning, planning, and NLU, and emits structured **Intents**. Nizam speaks to it only through the **Bayan Gateway** ACL.

**Bayan Gateway** — The **AI (Bayan Gateway)** bounded context: the ACL to the Bayan brain plus the `LlmProvider` port. It validates intents against the Published Language, assembles context, and shapes responses. It performs no reasoning of its own.

**Bounded Context** — An explicit boundary within which a domain model and its ubiquitous language are consistent. Nizam has exactly **12** bounded contexts, each a NestJS module. See [`05-Bounded-Contexts.md`](./05-Bounded-Contexts.md).

**Broker** — The event backbone that carries integration events between contexts. Primary: **NATS JetStream**; lightweight local/dev fallback: Redis Streams.

**BullMQ** — The Redis-backed queue/job library used for intra-system jobs (scheduling, delivery, retries).

## C

**Causation ID** — The ID of the message/command that directly caused the current event, enabling precise event lineage. Distinct from Correlation ID.

**Clean Architecture** — The layering discipline applied per module: `domain/` → `application/` → `infrastructure/` → `interface/`, with the **Dependency Rule** pointing inward.

**Compensation** — The corrective action that semantically undoes a previously completed step when a later step in a multi-step flow fails. Coordinated by a **Saga** and executed via the Automation Engine (`CompensationStep`).

**Conformist** — A DDD integration pattern where a downstream context accepts the upstream model as-is with no translation. In Nizam, generic consumers (Monitoring, Billing, Notifications) conform to the event contracts they consume.

**Connection** — In Integrations, a `Connection` aggregate binding a tenant to an external system via a `Connector`, holding only a `CredentialRef` (never a raw secret) and a `HealthStatus`.

**Connector** — In Integrations, a versioned ACL to one class of external system (CRM, email, calendar, storage). Registered as a plugin behind a manifest.

**Core (Kernel)** — The **Shared Kernel** bounded context: base entities/VOs, event-bus and Outbox abstractions, result/error types, clock, ID generation, and tenant context. Contains no business rules.

**Correlation ID** — An ID propagated across all events/spans of a single logical flow (e.g., one Intent's end-to-end execution), enabling tracing across contexts.

**CQRS (Command Query Responsibility Segregation)** — Separating write (command) and read (query) models where read/write asymmetry warrants it (Monitoring, Billing usage, Agent runs).

**Customer/Supplier** — A DDD relationship where the downstream (customer) has negotiating influence over the upstream (supplier) interface. Example: Agents (customer) ↔ Tools (supplier).

## D

**Dependency Rule** — Core rule of Clean Architecture: source-code dependencies point *only* inward. The domain depends on nothing; infrastructure/interface depend on application/domain via ports.

**DLQ (Dead-Letter Queue)** — A queue that receives messages/jobs which have exhausted their retries, so failures are inspectable rather than silently lost (used in Notifications and event consumers).

**Domain Event** — An immutable record, named in the past tense, that *something happened* in the domain (e.g., `AgentRunCompleted`). Emitted by aggregates; internal to a context until translated to an **Integration Event**.

**Domain Service** — Stateless domain logic that doesn't naturally belong to a single aggregate (e.g., `PlanResolver`, `PermissionEvaluator`, `RetryPolicyEvaluator`). Lives in `domain/`.

## E

**EDA (Event-Driven Architecture)** — The style in which state changes emit events (via the Outbox) and other contexts react asynchronously over the broker. Async by default; synchronous only for direct request/response user actions.

**Entity** — A domain object with a stable identity (`id`, UUID v7) and a lifecycle; compared by identity, not attribute values.

**Event Sourcing** — Persisting an aggregate's state as an ordered sequence of events. Used for critical aggregates: agent runs, tool executions, automations.

## F

**Factory** — A pattern that encapsulates the complex creation of an aggregate or value object, guaranteeing a valid initial state (e.g., `AgentRun.start(...)`).

**Feature Flag** — A toggle (tenant- or user-scoped) governing feature availability, owned by Settings. Tenant-disabled flags cannot be enabled at user level.

## H

**Hexagonal Architecture (Ports & Adapters)** — The pattern behind the Dependency Rule: the domain exposes **Ports**; external technologies plug in as **Adapters**. Synonymous in intent with Clean Architecture's boundaries.

## I

**Idempotency** — The property that performing an operation more than once has the same effect as performing it once. Enforced via an **Idempotency Key** so retries are safe (Tools, Automation, Notifications).

**Idempotency Key** — A unique key attached to an operation so duplicate deliveries/retries are deduplicated (VO on `ToolExecution`, `AutomationRun`).

**Integration** — See **Integrations** context, or an individual external-system connection.

**Integration Event** — The externally-published subset of domain events, described via AsyncAPI and carried on the broker. Contexts react to integration events; they never subscribe to another context's internal domain events directly.

**Intent** — The structured, versioned instruction emitted by **Bayan** describing *what the user wants done*. The **Published Language** at the boundary between Bayan and Nizam. Validated and wrapped as an `IntentEnvelope` by the Bayan Gateway before reaching Agents.

## L

**LlmProvider (port)** — The Core/Gateway port abstracting LLM access for when *Nizam itself* calls a model (e.g., tool-argument synthesis). Default adapter targets the **Anthropic Claude API** (latest Claude models) and is replaceable.

## M

**Manifest** — The declarative descriptor (metadata + JSON Schema + capability/permission info) that registers a plugin — a Tool, Integration connector, or agent skill — against a stable contract. Versioned via SemVer.

**Multi-tenancy** — Serving many isolated tenants from shared infrastructure. Nizam uses shared DB / shared schema with **RLS** as the enforcing boundary, offering pool-/schema-per-tenant as scaling tiers (Silo/Bridge/Pool).

## N

**n8n** — The self-hosted workflow executor that the **Automation Engine** owns and drives via an adapter (ACL). It talks to external systems. Kept behind a port so it is replaceable.

**NATS JetStream** — The primary event **broker** carrying integration events between contexts (Redis Streams is the dev fallback).

**NestJS** — The backend framework; Nizam is a modular monolith of NestJS modules (one per bounded context), service-extractable later.

**Nizam** — (Arabic **نظام**, "system/order.") The **AI Operating System** this project builds — the **execution layer** of the Bayan AI Operating System. Full name: *"Nizam — the Bayan AI Operating System."* Short: **Nizam AIOS** / **Nizam**. It receives intents from Bayan and executes them safely, observably, and multi-tenant.

## O

**OHS — see Open Host Service.**

**OIDC / OAuth2** — The authentication protocols used by IAM (JWT access + refresh tokens; mTLS for service-to-service).

**Open Host Service (OHS)** — A DDD pattern in which a context exposes a well-defined, stable protocol/API for many consumers. Used by IAM (authZ decisions), Settings (config/flags), and the Bayan Gateway (intent intake).

**OpenAPI** — The specification (version 3.1) describing Nizam's REST contracts (URL-versioned, e.g., `/v1`).

**OpenTelemetry (OTel)** — The instrumentation standard for traces/metrics/logs, exported to Prometheus + Grafana + Loki + Tempo. Instrumentation primitives live in Core; read-side aggregation lives in Monitoring.

**Optimistic Concurrency** — Conflict detection via a `version` column: a write fails if the version changed since read, preventing lost updates.

**Outbox (Transactional Outbox)** — The pattern that persists outgoing events in the *same database transaction* as the aggregate state change, then relays them to the broker. Guarantees "state changed ⇔ event emitted" with no lost or ghost events.

## P

**Plugin** — A hot-loadable, versioned, sandboxed unit (Tool, Integration connector, or agent skill) registered against a stable contract via a **Manifest**.

**Port** — An interface declared in the `domain/` (or `application/`) layer expressing a capability the domain needs (e.g., `AgentRunRepository`, `LlmProvider`, `WorkflowExecutor`). Implemented by **Adapters**.

**Process Manager — see Saga.**

**Published Language (PL)** — A shared, versioned interchange format used across a boundary. In Nizam: the **Intent** contract (Bayan↔Nizam), plus AsyncAPI event schemas and OpenAPI DTOs.

## R

**RBAC (Role-Based Access Control)** — Authorization by assigning permissions to roles and roles to users. Combined with **ABAC** in IAM.

**Repository** — A **Port** abstracting persistence of a single aggregate root, collection-like in interface. Declared in `domain/`, implemented (Postgres + RLS) in `infrastructure/`.

**RLS (Row-Level Security)** — PostgreSQL feature enforcing per-row access by `tenant_id`, making tenant isolation a *database-enforced* boundary, not just an application check. The primary multi-tenancy guarantee.

## S

**Saga (a.k.a. Process Manager)** — A coordinator for multi-step, cross-context flows (e.g., Intent → Agents → Tools → Automation → Integrations). It reacts to events, drives next steps, and triggers **Compensation** on failure. It owns no business invariants of its own.

**SemVer (Semantic Versioning)** — `MAJOR.MINOR.PATCH` versioning applied to modules and tool/agent/connector manifests.

**Settings** — The bounded context owning tenant/user configuration, feature flags, Basic/Advanced mode, and localization prefs; exposes an OHS for config reads.

**Shared Kernel** — A small model shared by multiple contexts by explicit agreement, with changes requiring all sharers' consent. **Core (Kernel)** is Nizam's Shared Kernel.

**Silo / Bridge / Pool** — The multi-tenancy isolation tiers: **Silo** (dedicated resources per tenant), **Bridge** (shared infra, isolated data path), **Pool** (fully shared with RLS). Nizam defaults to Pool with RLS and offers stricter tiers for scaling.

**SLA (Service-Level Agreement)** — A contractual commitment on service performance/availability offered to customers.

**SLO (Service-Level Objective)** — An internal, measurable target (e.g., success rate, latency) tracked with an **error budget** in Monitoring; typically stricter than the SLA.

**Soft Delete** — Marking a row deleted via `deleted_at TIMESTAMPTZ` rather than physically removing it; partial indexes exclude deleted rows.

**Specification** — An encapsulated, composable business rule/predicate reusable for both querying and validation (e.g., `PublishedToolVersionSpec`, `WithinQuotaSpec`).

## T

**Tenant** — An isolated customer organization within Nizam. The unit of multi-tenancy; every tenant-scoped table carries `tenant_id UUID NOT NULL`, enforced by RLS. Modeled as the `Tenant` aggregate in IAM.

**Tool** — A single, permissioned, versioned capability that an agent (or automation) can invoke, described by input/output JSON Schema. Defined by a `ToolDefinition`; each invocation is a `ToolExecution`.

**Tool Execution** — The `ToolExecution` aggregate: one invocation of a tool version, idempotent, event-sourced, validated against the tool's JSON Schema and IAM permission scope.

**Tool Registry** — The **Tools** bounded context (Core domain): the catalog of tool definitions, versions, schemas, capability metadata, permission scopes, and sandboxed invocation contracts.

## U

**Ubiquitous Language** — The single, shared, precise vocabulary used identically by domain experts, product, and code. This glossary is its dictionary.

**UUID v7** — Time-sortable universally unique identifier used for all primary keys (`id`) and event IDs, giving both uniqueness and chronological ordering.

## V

**Value Object (VO)** — An immutable, identity-less domain concept defined solely by its attributes and self-validating (e.g., `Money`, `TenantId`, `JsonSchema`, `IntentType`).

**Vault (Secrets Manager)** — The abstracted secrets store (HashiCorp Vault or cloud KMS) holding credentials. Integrations/IAM reference secrets only via a `CredentialRef` handle; raw secrets never enter aggregates.

## W

**Wizard** — A guided, multi-step UI flow used instead of long forms for complex settings, so non-technical users can complete configuration step-by-step. A UI/UX rule from the canon.

**Workflow** — A `WorkflowDefinition` in the Automation context: triggers plus ordered step bindings executed (via n8n) as an `AutomationRun`.

---

## Related Documents

- [`04-Domain-Driven-Design.md`](./04-Domain-Driven-Design.md) — strategic & tactical DDD
- [`05-Bounded-Contexts.md`](./05-Bounded-Contexts.md) — the 12 contexts and their contracts
- [`00-Vision.md`](./00-Vision.md) — product vision
- [`21-Database-Design.md`](./21-Database-Design.md) — persistence, RLS, UUID v7, soft delete, audit
- [`22-UIUX-Guidelines.md`](./22-UIUX-Guidelines.md) — Basic/Advanced mode, wizards, bilingual UI

## Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0.0 | 2026-07-01 | Architecture (Nizam Core) | Initial approved glossary / ubiquitous language dictionary for Phase 1. |
