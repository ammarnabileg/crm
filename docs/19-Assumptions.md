# Assumptions & Open Questions Register — Nizam AIOS

One-line purpose: The register of validated assumptions and explicitly deferred open questions for **Nizam — the Bayan AI Operating System**, so that anything not yet decided is recorded here (never as a silent invention or a TODO).

> **Status: Approved (Phase 1) | Version: 1.0.0 | Last updated: 2026-07-01 | Owner: Architecture (Nizam Core)**

---

## 1. Purpose

Per the canon, where a gap exists it is recorded here rather than invented silently. This document holds two registers:

- **(a) Assumptions** — things we take as true to proceed, with rationale, blast radius if wrong, and a validation plan.
- **(b) Open Questions / Decisions Deferred** — things deliberately not decided in Phase 1, why they were deferred, and the phase by which each must be answered.

Both are reviewed at every phase gate (see `15-Project-Roadmap.md`).

---

## 2. (a) Assumptions

| ID | Assumption | Rationale | Impact if Wrong | Validation Plan |
|----|-----------|-----------|-----------------|-----------------|
| A-01 | **Bayan exists and exposes a stable, versioned Intent contract** that Nizam can consume through the Bayan Gateway. | Canon fixes Bayan as external and already built; Nizam is the execution layer receiving intents. | If the contract is unstable or absent, intent intake breaks and the Gateway ACL cannot be finalized; Phase 3 is blocked. | Obtain/agree the Intent contract spec with the Bayan team; add contract tests; version the contract (see Q-01). |
| A-02 | **Tenants are B2B organizations** (not individual consumers), each with users, roles, and orgs. | Drives IAM, tenancy tiers, and the RBAC+ABAC model. | A B2C model would change identity, isolation, pricing, and UX assumptions significantly. | Confirm target-customer model with Product; validate against onboarding flows. |
| A-03 | **Self-hosted n8n is acceptable** as the automation execution substrate. | Canon fixes n8n (self-hosted) behind the Automation Engine; large connector ecosystem, operator-friendly. | If self-hosting is not viable (ops burden, licensing), the substrate must change behind the adapter, delaying Phase 4. | Prototype n8n HA + sandboxing; validate licensing and operational cost; confirm adapter isolates it (ADR-0005). |
| A-04 | **PostgreSQL RLS provides sufficient tenant isolation at target scale** for the Pool tier. | Canon fixes shared-schema + RLS as the default isolation boundary. | If RLS is insufficient (performance or isolation), tenants must move to Bridge/Silo sooner, raising cost/complexity. | Isolation and load tests in Phase 2/8; tier-promotion path validated (ADR-0013); target scale must be quantified (see Q-05). |
| A-05 | **Claude models via the Anthropic API are available and adequate** for Nizam's own auxiliary LLM needs (e.g., tool-arg synthesis). | Canon fixes Claude behind the `LlmProvider` port; reasoning proper lives in Bayan. | Availability/latency/cost issues degrade auxiliary features; port allows swapping but requires an alternative. | Validate latency/cost/rate limits; the `LlmProvider` port keeps the provider replaceable (ADR-0009). |
| A-06 | **The primary operator persona is non-technical**, using Basic mode and wizards. | Canon fixes the UX rules and Basic/Advanced modes. | If users are mostly technical, heavy wizard/help investment is partly wasted (still low harm). | Persona research with Product; usability testing of wizards in Phase 7. |
| A-07 | **NATS JetStream meets throughput, persistence, and replay needs** as the primary event broker. | Canon fixes NATS JetStream (Redis Streams fallback for dev). | If throughput/features fall short, migration to Kafka behind the broker abstraction is needed. | Load-test JetStream; keep broker behind an abstraction (ADR-0004); benchmark against expected event volume. |
| A-08 | **A Vault-style secrets manager (HashiCorp Vault or cloud KMS) is available** and abstracted. | Canon fixes an abstracted secrets manager. | Without it, credential handling is insecure; blocks Integrations (Phase 5). | Confirm platform choice (see Q-02); implement the secrets abstraction; secret-scanning in CI. |
| A-09 | **Kubernetes (Helm) with HPA is the deployment target**, enabling horizontal scaling. | Canon fixes Docker/K8s/Helm/HPA. | If the target environment lacks K8s, deployment/scaling assumptions change. | Confirm hosting environment with Platform/SRE; validate Helm charts in Phase 2/8. |
| A-10 | **At-least-once delivery with idempotent consumers is acceptable** (no exactly-once requirement globally). | Follows from Transactional Outbox + broker semantics (ADR-0004). | If any flow truly needs exactly-once, additional dedup/coordination is required. | Identify flows needing stronger guarantees; enforce idempotency keys; test duplicate delivery (R-07). |
| A-11 | **The modular monolith can be later extracted into services** without redesign, thanks to enforced boundaries. | Canon fixes modular-monolith-first with extractability (ADR-0001/0007). | If boundaries erode, extraction becomes costly (R-18). | Architecture tests enforcing boundaries; periodic boundary audits. |
| A-12 | **Bilingual AR/EN with RTL/LTR** covers the required localization scope for Phase 1–7. | Canon fixes bilingual UI. | Additional languages/locales would expand i18n scope. | Confirm locale requirements with Product; design i18n to be extensible. |

---

## 3. (b) Open Questions / Decisions Deferred

| ID | Question | Why Deferred | Needed By Phase |
|----|----------|--------------|-----------------|
| Q-01 | **What is the exact Bayan Intent schema** (fields, versioning, error semantics)? | Owned jointly with the external Bayan team; not required to finish Phase 1 architecture, but required to build the Gateway. | Phase 3 (Bayan Gateway + Agent runtime). |
| Q-02 | **HashiCorp Vault vs. cloud KMS** for the secrets manager? | Depends on final hosting environment and ops model; abstraction (A-08) lets us decide later. | Phase 5 (Integrations — credential binding). |
| Q-03 | **Is the optional GraphQL BFF adopted**, and for which surfaces? | REST + AsyncAPI are sufficient for core contracts; BFF value is UI-driven and can be decided when UI needs are concrete (ADR-0010). | Phase 7 (UI). |
| Q-04 | **Which data-residency regions** must be supported, and under which regulations? | Regulatory/customer requirements not yet specified; Silo tier already provides the mechanism (R-15, ADR-0013). | Phase 5/6 (before onboarding region-pinned tenants); confirm by Phase 8 GA. |
| Q-05 | **Exact SLO targets** (availability, latency, run-throughput) per tier? | Requires product/customer commitments and baseline benchmarks not available in Phase 1. | Phase 6 (Monitoring/SLOs); enforced by Phase 8. |
| Q-06 | **Which external connectors are in the initial Integrations set**, and their priority (CRM, email, messaging, calendars, storage)? | Depends on customer demand; connectors are plugins and can be prioritized later. | Phase 5 (Integrations). |
| Q-07 | **Quota, metering units, and pricing model** (per agent run, tool call, automation execution, token) — exact definitions and limits? | Requires business/pricing decisions and usage data; metering design exists, values do not. | Phase 6 (Billing/metering). |
| Q-08 | **Human-in-the-loop policy** — which agent actions require explicit human approval, and thresholds? | Depends on tool risk classification and customer risk appetite; guardrail mechanism is designed, policy values are not. | Phase 3 (Agent guardrails); refined in Phase 5. |
| Q-09 | **Event schema registry choice and governance process** for AsyncAPI event versioning? | Tooling selection deferred; the versioning approach (AsyncAPI + registry) is fixed, the product is not. | Phase 2/3 (when events proliferate). |
| Q-10 | **Disaster-recovery targets (RPO/RTO)** and backup/restore strategy per tenant tier? | Requires SLO and residency inputs (Q-04/Q-05) and infra decisions. | Phase 8 (Hardening/GA). |
| Q-11 | **n8n code-node policy** — fully disabled, restricted allow-list, or approval-gated? | Security trade-off dependent on customer workflow needs; sandbox design exists (R-05), policy value deferred. | Phase 4 (Automation Engine + n8n). |
| Q-12 | **Default vs. configurable Claude model IDs/params** per feature, and multi-provider routing? | Model landscape evolves; the `LlmProvider` port abstracts it, so selection can be configured later (ADR-0009). | Phase 3 (when Nizam first calls the LLM). |
| Q-13 | **Tenant tier promotion/demotion process** (Pool↔Bridge↔Silo) — automated or operator-driven? | Provisioning/ops decision; the tiering model is fixed (ADR-0013), the migration workflow is not. | Phase 2 (provisioning), refined by Phase 8. |

---

## 4. Governance

- **No silent invention:** any gap discovered during a later phase is added here (or resolved via an ADR in `16-ADR.md`), never encoded as a TODO in code or docs.
- **Traceability:** Open Questions link to the phase that must answer them; assumptions link to the risks (`18-Risks.md`) they influence.
- **Closure:** an Open Question is closed by recording the decision as an ADR; an Assumption is closed when validated or converted into a decision/risk.

---

## Related Documents

- `docs/16-ADR.md` — Decisions; several Open Questions close as new ADRs here.
- `docs/18-Risks.md` — Risks influenced by these assumptions.
- `docs/15-Project-Roadmap.md` — Phases by which open questions must be answered.
- `docs/00-Vision.md` — Product context.

---

## Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0.0 | 2026-07-01 | Architecture (Nizam Core) | Initial register: assumptions A-01–A-12 and open questions Q-01–Q-13 recorded. |
