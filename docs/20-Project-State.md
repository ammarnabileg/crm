# 20 — Project State

> The authoritative snapshot of what exists, what is decided, and what remains — the single place to answer "where is this project right now?"

**Status:** Active incremental build — Phase 2 in progress | **Version:** 2.0.0 | **Last updated:** 2026-07-01 | **Owner:** Architecture (Nizam Core)

> **Summary update (v2.0.0):** The project has moved from **Phase 1 (docs-only)** into an
> **active, incremental build** governed by the ratified [Constitution](./CONSTITUTION.md), which
> is now the supreme governing document. The delivery plan is the **12-phase program** in
> [15-Project-Roadmap.md](./15-Project-Roadmap.md); the stack is a **self-built native PHP 8.4
> platform** ([ADR-0015](./16-ADR.md#adr-0015)). **Phase 1 is complete; Phase 2 (Core
> Infrastructure Platform) is in progress with its foundation spine implemented and test-green.**
> The executive snapshot is the root [PROJECT_STATE.md](../PROJECT_STATE.md); this document retains
> the detailed Phase-1 deliverable inventory below. Where the two differ in framing, the root
> PROJECT_STATE and the Constitution take precedence.

---

## 1. Current Phase

**Phase 1 — Architecture Foundation: COMPLETE. Phase 2 — Core Infrastructure Platform: IN PROGRESS.**

Phase 1 produced **architecture and documentation only** (inventoried in §2 below). The project has
since entered Phase 2 build under the [Constitution](./CONSTITUTION.md): the self-built native
PHP 8.4 platform's **foundation spine** (Support, Exception, Container, Config, Event, Logging, the
DDD Kernel, command/query buses, `TenantContext`, and the `Bootstrap\Application` composition root)
is **implemented and test-green** (76 tests / 151 assertions on PHP 8.4.19 + PHPUnit 11). The
remaining Phase-2 platform infrastructure and Phases 3–12 are the forward plan.

- Product: **Nizam — the Bayan AI Operating System** — the execution OS that lets a business build
  an **AI Workforce without programming**, running intents from the external Bayan brain.
- Scope delivered in Phase 1: complete strategic and technical architecture, domain model,
  bounded-context map, cross-cutting strategies, database design, security &
  multi-tenancy strategy, engineering standards, roadmap, decision log, and the
  Phase-1 audit.
- Scope in progress in Phase 2: the self-built native-PHP platform (foundation spine done); see the
  root [PROJECT_STATE.md](../PROJECT_STATE.md) for the delivered-package detail.

## 2. Deliverables Status

| # | Document | Status | Notes |
|---|----------|--------|-------|
| — | `README.md` | ✅ Done | Entry point, index, constitution, Phase-1 status |
| 00 | `00-Vision.md` | ✅ Done | Vision, users, non-goals, success criteria |
| 01 | `01-System-Overview.md` | ✅ Done | Layer chain, capabilities, request walkthrough |
| 02 | `02-Business-Goals.md` | ✅ Done | Drivers, OKRs, KPIs, monetization alignment |
| 03 | `03-Architecture.md` | ✅ Done | Master architecture; all 18 required sections |
| 04 | `04-Domain-Driven-Design.md` | ✅ Done | Strategic + tactical DDD, context map |
| 05 | `05-Bounded-Contexts.md` | ✅ Done | All 12 contexts specified in detail |
| 06 | `06-Agent-Architecture.md` | ✅ Done | Agent Framework (executor, not reasoner) |
| 07 | `07-Tool-Architecture.md` | ✅ Done | Tool Registry, manifests, invocation contract |
| 08 | `08-Automation-Architecture.md` | ✅ Done | Automation Engine wrapping n8n |
| 09 | `09-Multi-Tenant.md` | ✅ Done | RLS tenancy, Pool/Bridge/Silo, context propagation |
| 10 | `10-Security-Strategy.md` | ✅ Done | STRIDE, AuthN/Z, secrets, agent/tool safety |
| 11 | `11-API-Strategy.md` | ✅ Done | REST/OpenAPI, webhooks, WS, gRPC, GraphQL BFF |
| 12 | `12-Event-Architecture.md` | ✅ Done | Outbox, JetStream, sagas, event sourcing |
| 13 | `13-Coding-Standards.md` | ✅ Done | Enforceable engineering standards |
| 14 | `14-Folder-Structure.md` | ✅ Done | Full monorepo tree, every folder explained |
| 15 | `15-Project-Roadmap.md` | ✅ Done | Phases 1–8, exit criteria, STOP gate |
| 16 | `16-ADR.md` | ✅ Done | ADR-0001…0014, all Accepted |
| 17 | `17-Glossary.md` | ✅ Done | Ubiquitous language dictionary |
| 18 | `18-Risks.md` | ✅ Done | 20-item risk register |
| 19 | `19-Assumptions.md` | ✅ Done | 12 assumptions + 13 open questions |
| 20 | `20-Project-State.md` | ✅ Done | This document |
| 21 | `21-Database-Design.md` | ✅ Done | ERD, entities, constraints, indexing — design only |
| 22 | `22-UIUX-Guidelines.md` | ✅ Done | Non-technical UX, Help Popup, Wizard, modes |
| 23 | `23-Agent-Hierarchy.md` | ✅ Done | Department Managers, Worker Agents, Automation Selector, Manager Audit loop |
| — | `PROJECT_STATE.md` (root) | ✅ Done | Executive state dashboard (points here for detail) |
| — | `NEXT_PHASE.md` | ✅ Done | Phase 2 plan and entry criteria |
| A | `docs/audit/Architecture-Audit-Report.md` | ✅ Done | Independent architecture audit |
| A | `docs/audit/Missing-Items-Report.md` | ✅ Done | Gap analysis |
| A | `docs/audit/Risks-Report.md` | ✅ Done | Consolidated risk report |

**Deliverable completion: 30 / 30 (100%)** — the original 28 plus `23-Agent-Hierarchy.md` and the root `PROJECT_STATE.md`.

## 3. Canonical Decisions Locked in Phase 1

These are fixed and recorded in `16-ADR.md`. They are the foundation every later
phase must obey.

- **Naming:** Bayan = external brain; **Nizam** = the AI Operating System (this project).
- **Stack (ADR-0014):** **native PHP 8.3+** (`declare(strict_types=1)`), framework-agnostic
  on **PSR** standards (PSR-4/7/15/11/3), Composer, PHPStan/Psalm, PHPUnit, Monolog;
  Redis-backed PHP queue workers behind a `Queue` port; decoupled Next.js operator console.
- **Architecture style:** Modular monolith first (native PHP), service-extractable; Clean
  Architecture + DDD + Hexagonal per module; Event-Driven by default.
- **Data:** PostgreSQL 16, shared-schema **RLS** multi-tenancy (Pool/Bridge/Silo tiers),
  **UUID v7** keys, soft delete, audit columns + `audit_log` + event sourcing for
  critical aggregates.
- **Eventing:** Transactional Outbox → **NATS JetStream** (Redis Streams fallback).
- **Automation:** self-hosted **n8n** as execution substrate behind an internal
  Automation Engine.
- **AI access:** Bayan via an Anti-Corruption Layer (Bayan Gateway); Nizam's own LLM
  calls via a replaceable `LlmProvider` port (Claude / Anthropic API, latest models).
- **Agent organization:** two-tier hierarchy inside the Agents context — one
  **Department Manager Agent** per department (HR, Marketing, Sales, Finance, Support,
  Developer, CEO) owning multiple **Worker Agents**; workers pick automations via the
  **Automation Selector**; the **Manager Audit** loop (Approve/Reject/Retry/Request-More-
  Info/Run-Another-Worker) gates the final response. See `23-Agent-Hierarchy.md`.
- **Bounded contexts (12):** Core, Identity & Access, Agents, Tools, Automation,
  Integrations, AI (Bayan Gateway), Billing, Monitoring & Observability, Settings,
  Notifications, Administration.
- **APIs:** OpenAPI 3.1 REST (`/v1`), AsyncAPI 2.6 events, webhooks, WS/SSE, internal
  gRPC, optional GraphQL BFF.
- **AuthZ:** RBAC + ABAC; **AuthN:** OAuth2/OIDC, JWT, mTLS service-to-service.
- **UX:** Non-technical-first; Basic/Advanced mode; Wizards; mandatory Help Popups;
  bilingual AR/EN RTL/LTR.

## 4. What Does NOT Exist Yet

Beyond the delivered Phase-2 **foundation spine** (Support, Exception, Container, Config, Event,
Logging, the DDD Kernel, command/query buses, `TenantContext`, and the `Bootstrap\Application`
composition root — all test-green), the following are the **forward plan**, built in verified,
dependency-ordered increments per the [12-phase roadmap](./15-Project-Roadmap.md):

- The remaining Phase-2 platform infrastructure — HTTP/routing/middleware/HTTP kernel, the database
  layer (connection, query builder, schema/migrations, repositories, unit of work, tenant/RLS
  session), cache, queue, scheduler, validation, storage, localization, notifications, monitoring.
- All of Phases 3–12 (AI Runtime + plugins + Master Orchestrator, identity/tenancy/capability,
  tools, integrations/automation + n8n adapter, departments, the AI Organization Designer,
  providers/knowledge/memory, the enterprise platform/marketplace/billing, business intelligence,
  and the digital employee framework).
- Infrastructure provisioning (Kubernetes/Helm are described, not created), CI/CD, the Bayan
  intent-contract implementation (shape open — `19-Assumptions.md`, Q-01), and product UI screens.

Each is sequenced by dependency and shipped only when compiled, test-green, and documented in the
same change (Constitution §10).

## 5. Open Items Carried Forward

Tracked authoritatively in `19-Assumptions.md` (open questions Q-01…Q-13) and
`18-Risks.md`. The highest-priority items for Phase 2 entry:

1. **Q-01 — Bayan intent contract:** exact schema/versioning of intents Nizam consumes.
2. **Q-05 — Secrets backend:** HashiCorp Vault vs cloud KMS selection.
3. **SLO targets:** concrete numeric SLOs per flow (currently directional).
4. **Data residency:** target regions and per-tenant residency policy.

## 6. Quality & Consistency Status

- Cross-document consistency audit: **passed** (see `docs/audit/Architecture-Audit-Report.md`).
- All internal document cross-links resolve to existing files.
- No `TODO`/placeholder/demo/fake content; no application code present.
- Naming, tech stack, and the 12-context list are consistent across all documents.
- 86 Mermaid diagrams; all fenced code blocks balanced.

## 7. Phase Gate

> Phase 1 is 100% complete and Phase 2 is **in active, incremental build** under the
> [Constitution](./CONSTITUTION.md). Per Constitution §10, each increment ships only when it
> compiles, its tests are green, every new folder has a `README.md`, and its documents are updated
> in the same change. See [15-Project-Roadmap.md](./15-Project-Roadmap.md) for the dependency-ordered
> 12-phase plan and the root [PROJECT_STATE.md](../PROJECT_STATE.md) for the current build snapshot.

## Related Documents

- [README.md](../README.md) — Project entry point and full index
- [15-Project-Roadmap.md](./15-Project-Roadmap.md) — Phased plan and exit criteria
- [NEXT_PHASE.md](./NEXT_PHASE.md) — Phase 2 plan and entry criteria
- [19-Assumptions.md](./19-Assumptions.md) — Assumptions and open questions
- [docs/audit/Architecture-Audit-Report.md](./audit/Architecture-Audit-Report.md) — Audit findings

## Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0.0 | 2026-07-01 | Architecture (Nizam Core) | Initial Phase 1 completion snapshot |
| 2.0.0 | 2026-07-01 | Architecture (Nizam Core) | Reconciled top summary, §1 current phase, §4 forward plan, and §7 gate: project moved from Phase 1 (docs-only) into Phase 2 build under the Constitution as governing doc; Phase-2 foundation spine implemented and test-green. Detailed Phase-1 deliverable inventory retained. |
