# PROJECT_STATE — Nizam (Bayan AI Operating System)

> Executive, top-level snapshot of where the project stands. For the detailed
> deliverable-by-deliverable status, see [docs/20-Project-State.md](./docs/20-Project-State.md).

**Status:** Phase 1 (Architecture) — **COMPLETE & AUDITED** | **Version:** 1.0.0 | **Last updated:** 2026-07-01 | **Owner:** Architecture (Nizam Core)

---

## Where we are

**Phase 1 — Foundation & Enterprise Architecture is 100% complete.** This phase produced
**architecture and documentation only** — zero application code, by constitutional
mandate. The project is **paused at the Phase 1 gate, awaiting explicit approval** before
any Phase 2 (build) work begins.

- **Product:** Nizam — the execution AI Operating System that runs decisions made by the
  external **Bayan** brain. It is *not* a chatbot, *not* a RAG, and does *not* build the brain.
- **Internal execution chain:**
  `Bayan → Nizam → Department Manager Agent → Worker Agents → Tool Registry → Automation Selector → n8n → Results → Manager Audit → Return Response`
- **Deliverables:** 30 documents (README, `docs/00`–`docs/23`, `NEXT_PHASE.md`, this file,
  and 3 audit reports). All cross-links resolve; no code; no TODO/placeholder.

## What was decided (canonical)

- Modular monolith (service-extractable), Clean Architecture + DDD + Hexagonal per module,
  Event-Driven by default.
- PostgreSQL 16 with shared-schema **RLS** multi-tenancy (Pool/Bridge/Silo tiers), UUID v7,
  soft delete, audit + event sourcing for critical aggregates.
- Transactional Outbox → **NATS JetStream**; **n8n** as automation substrate behind an
  Automation Engine + **Automation Selector**; Bayan reached via an Anti-Corruption Layer.
- **Two-tier agent hierarchy**: Department Manager Agents (HR, Marketing, Sales, Finance,
  Support, Developer, CEO) → Worker Agents, governed by the **Manager Audit** loop
  (Approve / Reject / Retry / Request More Information / Run Another Worker). Only a Manager
  returns the final result upward.
- 12 bounded contexts; RBAC+ABAC; OpenAPI 3.1 REST + AsyncAPI events; non-technical-first
  UX (Basic/Advanced mode, Wizards, mandatory Help Popups), bilingual AR/EN.

Full rationale is recorded as ADRs in [docs/16-ADR.md](./docs/16-ADR.md).

## What does NOT exist yet (by design)

Application code, runnable schema/migrations, service implementations, CI/CD, IaC, product
UI screens, and the Bayan intent-contract implementation are all **Phase 2+** scope.
Building any of them now would violate the Phase 1 boundary.

## Open items for Phase 2 entry

Bayan intent-contract schema (Q-01), secrets backend (Q-05), concrete SLO numbers, and
data-residency regions — tracked in [docs/19-Assumptions.md](./docs/19-Assumptions.md).

## Quality gate

Cross-document consistency audit **passed** — see
[docs/audit/Architecture-Audit-Report.md](./docs/audit/Architecture-Audit-Report.md).

> **STOP.** Phase 1 is complete. Await explicit approval before starting Phase 2
> ([docs/NEXT_PHASE.md](./docs/NEXT_PHASE.md)).

## Related Documents

- [README.md](./README.md) — Entry point and full index
- [docs/20-Project-State.md](./docs/20-Project-State.md) — Detailed state
- [docs/NEXT_PHASE.md](./docs/NEXT_PHASE.md) — Phase 2 plan
- [docs/15-Project-Roadmap.md](./docs/15-Project-Roadmap.md) — All phases

## Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0.0 | 2026-07-01 | Architecture (Nizam Core) | Initial top-level project state |
