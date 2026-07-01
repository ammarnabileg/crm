# Behavior\Domain

**Purpose.** The pure heart of the Professional Behavior Engine: it models *how* a role performs
work as a transparent, versioned, explainable, reviewable **behavior profile**, learned only from
approved business practice and bound to the **role**, never to a person. This layer contains no I/O
and depends only on PHP and `Nizam\Kernel` (plus the small, pure `Nizam\Platform\Support` guards).
The engine **recommends**; it never mutates production behavior without explicit approval.

**Responsibilities.**
- Define the role-scoped identifiers (`RoleId`, `BehaviorProfileId`, `ProposalId`, `ObservationId`).
- Express behavior as `BehaviorTraits` — fourteen orthogonal style axes plus an evidence bar —
  with immutable `with*()` copies, `diff()`, `equals()`, and a policy-gated elevated-risk guard.
- Own the two aggregates and their invariants:
  - `BehaviorProfile` — append-only, versioned history of revisions; draft → activate →
    apply approved change → roll back → archive, each gated and each emitting a domain event.
  - `BehaviorChangeProposal` — an approval-gated request to change a profile; nothing auto-applies.
- Record what happened as `Event/` domain events for the application layer to publish.
- Provide stateless domain `Service/`s: role-vs-person `BehaviorProfileConsolidator`, explainable
  `BehaviorRecommendationService`, and the `RiskTolerancePolicy` port.
- Declare the `Port/` interfaces the outside world implements (repositories, observation source,
  event publisher).
- Raise a typed `Exception/` hierarchy for every business-rule violation.

**Dependencies.** `Nizam\Kernel\Domain\{AggregateRoot,Entity,ValueObject,Identifier,DomainEvent,
RecordsDomainEvents,TenantId,Clock}`; `Nizam\Platform\Support\Assert` for precondition guards. No
framework, no persistence, no time source other than the injected `Clock` — zero I/O.

**Public interfaces (for the layers above).**
- Aggregates: `BehaviorProfile`, `BehaviorChangeProposal`.
- Entities/records: `BehaviorObservation`, `BehaviorProfileRevision`.
- Value objects: `BehaviorTraits`, `EvidenceReference`, `BehaviorRecommendation`, `ChangeLogEntry`.
- Enums: 14 style-trait enums + `ProfileStatus`, `ProposalStatus`, `ObservationSourceType`.
- Services: `BehaviorProfileConsolidator`, `BehaviorRecommendationService`, `RiskTolerancePolicy`.
- Ports: `BehaviorProfileRepository`, `BehaviorChangeProposalRepository`, `ObservationSource`,
  `BehaviorEventPublisher`.
- Exceptions: `BehaviorDomainException` and its subtypes (codes under `BEHAVIOR.*`).

**Key invariants.**
- Behavior is bound to a role; a profile's `roleId` is immutable.
- History is append-only: a past revision is never mutated; rollback restores earlier traits as a
  *new* version and is itself reversible.
- A change must differ from the current traits and carry at least `evidenceRequirements` approved
  evidences; only approved observations may drive evolution.
- Elevated risk tolerance requires an explicit policy allowance.
- Every state change is audited (change-log entry) and announced (domain event).
