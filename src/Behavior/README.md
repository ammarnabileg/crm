# Behavior — Professional Behavior Engine

**Purpose.** Model *how* work is performed inside a company as transparent, configurable, reviewable,
auditable **professional behavior profiles**, learned from **approved** business practice and bound to
the **role**, never to a person. The engine **recommends**; it never mutates production behavior
without explicit human approval. Profiles are versioned, explainable, reversible, and approval-gated.

## Layer map (Hexagonal / DDD)

```
Nizam\Behavior\
├── Domain/          Pure PHP on Nizam\Kernel — the model and its rules.
│   ├── (aggregates) BehaviorProfile, BehaviorChangeProposal; entity BehaviorObservation;
│   │                record BehaviorProfileRevision; identifiers RoleId/BehaviorProfileId/…
│   ├── Enum/        14 style axes + ProfileStatus/ProposalStatus/ObservationSourceType.
│   ├── ValueObject/ BehaviorTraits, EvidenceReference, ChangeLogEntry, BehaviorRecommendation.
│   ├── Event/       DomainEvents (drafted/activated/proposed/approved/rejected/version/rollback/archived).
│   ├── Service/     BehaviorProfileConsolidator, BehaviorRecommendationService, RiskTolerancePolicy (port).
│   ├── Port/        Repository + ObservationSource + BehaviorEventPublisher interfaces.
│   └── Exception/   BehaviorDomainException hierarchy (BEHAVIOR.* codes).
├── Application/     CQRS on the domain ports — depends only on Domain + Kernel.
│   ├── Command/     Draft/Propose/Approve/Reject/Rollback/Archive (+ handlers).
│   ├── Query/       Get / GetHistory / ListPending / Explain (+ handlers).
│   ├── Service/     BehaviorLearningService (observe → consolidate → recommend → PROPOSAL only).
│   └── Dto/         Read-model views (scalars only).
├── Infrastructure/  Driven adapters — the only layer that does I/O.
│   ├── Persistence/InMemory/  Real, tenant-scoped, seedable adapters + risk policy.
│   ├── Persistence/Pdo/       Real PDO adapters (SQLite + Postgres) + BehaviorMapper.
│   ├── Event/                 DispatchingBehaviorEventPublisher → platform EventDispatcher.
│   ├── Migration/             Postgres 16 SQL (source of record) + runnable SqliteSchema.
│   ├── BehaviorServiceProvider  Binds ports→adapters; registers handlers on the buses.
│   └── PersistenceDriver        InMemory | Pdo selector.
└── BehaviorModule   Facade: BehaviorModule::register(Container, ?PersistenceDriver).
```

**Dependency rule.** `Domain` depends only on `Nizam\Kernel\*` + PHP. `Application` depends on
`Domain` + `Nizam\Kernel\Application`. `Infrastructure` depends inward on both and outward on
`Nizam\Platform\{Container,Event,Support,Exception}` and `PDO`. Nothing points the other way.

## Core rules enforced

- **Role, not person.** A profile is bound to a `RoleId` at creation and can never be re-bound;
  consolidation folds the approved practice of *all* employees performing a role into one profile.
- **Approved practice only.** Only approved observations drive evolution; the `ObservationSource`
  port and its adapters return nothing else.
- **Recommend, don't mutate.** Learning produces a *pending proposal*; approving a proposal and
  applying it to a profile are separate, deliberate steps. Nothing auto-applies.
- **Versioned, append-only, reversible.** Every change appends an immutable revision; rollback
  restores an earlier version's traits as a *new* version. History is never rewritten.
- **Explainable.** Every recommendation carries a reason, supporting approved evidence, a confidence,
  and how-to guidance; every change carries a diff and stated business impact.
- **Evidence-gated & policy-gated.** A change must supply at least the traits' required evidence
  count; elevated risk tolerance is only adoptable when `RiskTolerancePolicy` permits it.
- **Tenant-scoped.** Every repository and read operation is scoped to its tenant.

## Public surface

A host installs the module with **`BehaviorModule::register($container)`** (optionally passing
`PersistenceDriver::Pdo` with a bound `PDO` for durable storage). After installation the module is
driven through:

1. **The command bus** — dispatch the `Application\Command` DTOs
   (`DraftBehaviorProfile`, `ProposeBehaviorChange`, `ApproveBehaviorChange`, `RejectBehaviorChange`,
   `RollbackBehaviorProfile`, `ArchiveBehaviorProfile`).
2. **The query bus** — dispatch the `Application\Query` DTOs
   (`GetBehaviorProfile`, `GetBehaviorProfileHistory`, `ListPendingProposals`, `ExplainBehaviorProfile`).
3. **`Application\Service\BehaviorLearningService`** — the learning loop that raises a proposal from a
   role's approved practice.

The `Interface` layer (HTTP/Console adapters) is the outermost pluggable layer and will sit on top of
this surface once the HTTP/Console platform lands; the module is architecturally complete without it.
