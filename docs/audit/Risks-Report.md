# Risks Report — Phase 1

> An executive-level consolidation of the Phase 1 risk landscape, drawn from the full register in `18-Risks.md`, with the top exposures and their mitigation posture called out.

**Status:** Approved (Phase 1) | **Version:** 1.0.0 | **Last updated:** 2026-07-01 | **Owner:** Architecture (Nizam Core)

---

## 1. Purpose

`18-Risks.md` is the authoritative, detailed register (20 risks, full mitigations,
owners). This report is the audit-time roll-up: severity distribution, the risks that
most shape the architecture, and confirmation that each top risk is mitigated *by
design* rather than deferred.

## 2. Severity Distribution

| Severity | Count | Risk IDs |
|----------|-------|----------|
| Critical | 1 | R-01 |
| High | 12 | R-02, R-03, R-04, R-05, R-06, R-07, R-08, R-09, R-11, R-12, R-13, R-15, R-16 |
| Medium | 7 | R-10, R-14, R-17, R-18, R-19, R-20 |

> Note: the register lists 20 risks; the High band above includes R-16 (saga
> compensation) as counted in `18-Risks.md`. See that document for the definitive matrix.

Category spread: **security** dominates (as expected for an AI execution OS acting on
external systems), followed by technical, operational, vendor, business, and compliance.

## 3. Top Risks and Design Response

The following are the risks most likely to shape build decisions. Each is countered by a
concrete architectural mechanism already specified in Phase 1 — not by a future promise.

| Rank | Risk | Why it matters | Architectural mitigation (already designed) |
|------|------|----------------|---------------------------------------------|
| 1 | **R-01 Prompt injection** (Critical) | Agents act on untrusted content; adversarial text could trigger unintended actions. | Instructions-vs-data separation; per-agent tool allow-lists; human-in-the-loop gates for high-impact actions; action validation against the plan; guardrails at the Agent Framework and `LlmProvider` port. (`06`, `10`) |
| 2 | **R-03 Cross-tenant leakage** (High) | Isolation failure is catastrophic and hard to detect. | Forced RLS on every tenant table; fail-closed tenant-context guard; **automated CI isolation test** as a hard gate; DB-layer enforcement independent of app code. (`09`, `10`, `21`) |
| 3 | **R-02 Over-privileged tools** (High) | A misled/compromised agent with broad scopes causes outsized damage. | Least-privilege tool scopes in the Tool Registry; permission checks at invocation (RBAC+ABAC); sandboxed invocation; scope review in plugin approval. (`07`, `10`) |
| 4 | **R-06 Bayan coupling/availability** (High) | Nizam depends on an external brain and its contract. | Anti-Corruption Layer (Bayan Gateway) with a versioned intent contract; contract tests; circuit breakers; intent queue/backpressure. (`03`, `05`, ADR-0006) |
| 5 | **R-07 Duplicate side-effects** (High) | At-least-once delivery can double-charge or double-act. | Idempotency keys on all side-effecting consumers; dedup store; commutative/idempotent effects; outbox; sagas with compensation. (`12`, ADR-0004) |
| 6 | **R-05 n8n arbitrary code** (High) | Automation substrate could run dangerous logic. | Code nodes disabled/restricted; locked-down sandbox (egress limits, non-root, resource caps); curated node allow-list; workflow approval; secrets never exposed to nodes. (`08`, `10`) |
| 7 | **R-08 Cost overruns** (High) | Unbounded agent/tool/automation/token usage. | Metering + per-tenant quotas enforced *before* execution; budget alerts; cost dashboards; safe caching. (`02`, Billing context) |
| 8 | **R-11 User misconfiguration** (High) | Non-technical users wire integrations wrongly. | Wizard-driven UX with per-field Help Popups, examples, and security warnings; Basic-mode defaults; connection-health validation before activation. (`22`, ADR-0012) |
| 9 | **R-16 Saga compensation gaps** (High) | Partial failure across contexts leaves inconsistent state. | Explicit saga/process managers with idempotent compensations; event-sourced critical aggregates for reconstruction; reconciliation jobs. (`03`, `12`) |
| 10 | **R-12 Secret leakage** (High) | Credentials exposed via logs/events/nodes. | Abstracted secrets manager; log/trace redaction; scoped short-lived credentials; secret scanning in CI. (`10`) |

## 4. Cross-Cutting Observations

- **Security is the dominant risk class.** The architecture responds with *defense in
  depth*: RLS at the data layer, RBAC/ABAC at the app layer, capability scoping at the
  tool layer, sandboxing at the automation layer, and human-in-the-loop at the action
  layer. No single control is load-bearing alone.
- **AI-specific risks (R-01, R-19) are treated as first-class**, not bolted on — agents
  are executors with guardrails, budgets, and kill-switches, and all external content is
  untrusted by default.
- **Vendor risk is bounded by ports & adapters** (R-10): n8n, the LLM provider, the
  broker, and the secrets store are all replaceable behind stable contracts.
- **Every top risk is mitigated by a Phase-1 design decision**, most traceable to an ADR
  in `16-ADR.md`. Status "Open" in the register means *not yet implemented* (Phase 1 has
  no code) — the mitigation is *designed*, and becomes *verified* as the relevant phase
  builds and tests it.

## 5. Residual & Deferred Risk

- Compliance/residency (R-15) depends on open questions (regions, DPA) tracked in
  `19-Assumptions.md`; the Silo tier is the designed lever.
- Concrete SLOs and error budgets (relevant to R-04, R-09, R-14) are directional in
  Phase 1 and must be quantified at Phase 2 entry.

## 6. Recommendation

The risk posture is acceptable to exit Phase 1. Carry the following into Phase 2 as
**mandatory quality gates**, since they defend the highest-severity risks:

1. Automated cross-tenant isolation test in CI (defends R-03).
2. Instructions-vs-data separation + tool allow-lists in the Agent Framework (defends R-01/R-02).
3. Idempotent, metered, quota-enforced execution path (defends R-07/R-08).
4. Secret redaction + scanning wired into logging and CI from day one (defends R-12).

## Related Documents

- [../18-Risks.md](../18-Risks.md) — Full risk register (authoritative)
- [Architecture-Audit-Report.md](./Architecture-Audit-Report.md) — Architecture audit
- [Missing-Items-Report.md](./Missing-Items-Report.md) — Gap analysis
- [../10-Security-Strategy.md](../10-Security-Strategy.md) — Security controls
- [../16-ADR.md](../16-ADR.md) — Decisions behind mitigations

## Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0.0 | 2026-07-01 | Architecture (Nizam Core) | Initial consolidated risk report |
