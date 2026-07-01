# Missing Items Report — Phase 1

> A deliberate gap analysis: what is intentionally absent, what is deferred, and what (if anything) is genuinely missing from the Phase 1 architecture.

**Status:** Approved (Phase 1) | **Version:** 1.0.0 | **Last updated:** 2026-07-01 | **Owner:** Architecture (Nizam Core)

---

## 1. Purpose

Phase 1 is architecture-only. This report separates three categories so that "missing"
is never ambiguous:

- **Intentionally out of scope** — must NOT exist in Phase 1 (would violate the gate).
- **Deferred decisions** — known unknowns, tracked as open questions for a later phase.
- **True gaps** — anything the architecture *should* have addressed but did not.

## 2. Verdict

**No true gaps.** All mandated Phase 1 deliverables are present and internally
consistent. Everything else falls into "intentionally out of scope" or "deferred
decision," each tracked in the appropriate document.

## 3. Intentionally Out of Scope (correctly absent)

| Item | Why absent | Where it lands |
|------|-----------|----------------|
| Application / business code | Phase 1 is docs-only per constitution | Phase 2+ |
| Runnable database schema / migrations | Design only in Phase 1 | Phase 2 (`21` → migrations) |
| Service implementations (Core, IAM, etc.) | No code in Phase 1 | Phase 2–7 per roadmap |
| n8n workflow definitions | Automation is designed, not built | Phase 4 |
| Integration connectors | Designed as ACLs; not implemented | Phase 5 |
| CI/CD pipelines & IaC (Helm/K8s manifests) | Described, not provisioned | Phase 2 onward |
| Product UI screens | UX rules/templates only | Phase 7 |
| Bayan intent-contract implementation | Bayan is external; contract is consumed | Phase 2/3 |

These are **not** missing — their absence is required by the Phase 1 boundary.

## 4. Deferred Decisions (tracked open questions)

Authoritative source: `19-Assumptions.md` (Q-01…Q-13). Highest-impact items:

| Ref | Deferred decision | Needed by | Impact if unresolved |
|-----|-------------------|-----------|----------------------|
| Q-01 | Exact Bayan **intent contract** schema & versioning | Phase 2/3 | Blocks Agent Framework wiring; affects Kernel event shapes |
| Q-05 | **Secrets backend**: HashiCorp Vault vs cloud KMS | Phase 2 | Affects IAM/Integrations credential handling |
| — | Concrete **SLO numbers** per flow (currently directional) | Phase 2 | Needed to set alerting and error budgets |
| — | **Data-residency** regions & per-tenant policy | Phase 5/6 | Affects tenancy tier and storage topology |
| Q-* | GraphQL BFF adoption timing; event schema-registry product | Phase 3+ | Non-blocking; defaults documented |

## 5. Documentation Completeness Detail

| Required architecture element | Covered in |
|-------------------------------|-----------|
| High/Low-level architecture | 03 |
| System components, execution/data/sequence/communication flow | 03, 01 |
| Module responsibilities & dependency rules | 03, 04, 05 |
| Integration / plugin / scaling / caching / queue strategy | 03 |
| Logging / monitoring / error-handling / versioning strategy | 03 |
| ERD, entities, relationships, indexes, constraints | 21 |
| UUID / audit / soft-delete / tenant-isolation strategy | 21, 09 |
| DDD bounded contexts & responsibilities | 04, 05 |
| Folder structure (every folder explained) | 14 |
| UI/UX rules (Help Popup, Wizard, Basic/Advanced) | 22 |
| Security & multi-tenancy | 10, 09 |
| API & event architecture | 11, 12 |
| Roadmap, ADRs, risks, assumptions, glossary | 15, 16, 18, 19, 17 |

No required element is unaddressed.

## 6. Minor Enhancement Opportunities (optional, non-blocking)

These would strengthen the set but are **not** required for Phase 1 sign-off:

1. A dedicated deployment/observability runbook doc could later split out of `03` as the
   system grows (currently consolidated there — sufficient for Phase 1).
2. An explicit error-code catalog could later split out of `03`'s error-handling section
   once concrete codes exist (Phase 2+).
3. Bidirectional cross-linking could be enriched (some contexts link outward more than
   they are linked back to) — cosmetic only; all links resolve.

## 7. Recommendation

No blocking gaps. Resolve the Phase 2 entry-critical open questions (Q-01, Q-05, SLOs,
residency) before starting build. Track any newly discovered gaps as open questions or
ADRs — never as silent TODOs.

## Related Documents

- [Architecture-Audit-Report.md](./Architecture-Audit-Report.md) — Full audit
- [Risks-Report.md](./Risks-Report.md) — Consolidated risks
- [../19-Assumptions.md](../19-Assumptions.md) — Assumptions & open questions
- [../15-Project-Roadmap.md](../15-Project-Roadmap.md) — Phase scoping
- [../NEXT_PHASE.md](../NEXT_PHASE.md) — Phase 2 entry criteria

## Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0.0 | 2026-07-01 | Architecture (Nizam Core) | Initial gap analysis |
