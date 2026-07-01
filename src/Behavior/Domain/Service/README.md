# Behavior\Domain\Service

**Purpose.** Stateless domain services that carry behavior-modeling logic which does not belong to a
single aggregate — chiefly the **role-vs-person** consolidation and the **explainable**
recommendation logic — plus the risk-policy port they rely on.

**Responsibilities.**
- `BehaviorProfileConsolidator` — folds the approved observations of *all* employees performing a
  role into one consolidated `BehaviorTraits`. Deterministic and explainable: only approved
  observations count, corroboration (distinct approved practices and their cumulative weight) sets
  the evidence bar within a sane range, style axes are anchored to the role's current approved
  behavior (or a conservative default), and risk is never elevated here.
- `BehaviorRecommendationService` — compares a profile's current traits against what the approved
  evidence supports (via the consolidator) and emits one `BehaviorRecommendation` per trait that
  should change, each with reason, non-empty supporting evidence, evidence-derived confidence, and
  concrete guidance. Recommends only; never mutates a profile.
- `RiskTolerancePolicy` — the port through which the domain asks whether a role may adopt elevated
  risk tolerance, without knowing how that governance decision is made.

**Dependencies.** `Behavior\Domain\{ValueObject,Enum,BehaviorProfile,BehaviorObservation,RoleId}`;
`Nizam\Kernel\Domain\TenantId`; `Nizam\Platform\Support\Assert`. No I/O.

**Public interfaces.** `BehaviorProfileConsolidator::consolidate()`,
`BehaviorRecommendationService::recommend()`, `RiskTolerancePolicy::allowsElevatedRisk()`.
