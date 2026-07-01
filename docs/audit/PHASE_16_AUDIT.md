# Professional Behavior Engine — Implementation Audit

> **Phase:** 16 — Professional Behavior Engine (Behavior bounded context) | **Status: Delivered, test-green** | **Date: 2026-07-01** | **Owner: Architecture (Nizam Core)**

This audit records the delivery of the **Behavior** bounded context (`Nizam\Behavior\*`, `src/Behavior/`),
the Professional Behavior Engine: a transparent, configurable, reviewable, auditable engine that models
**how work is performed inside a company** as versioned professional behavior profiles, learned only from
**approved** business practice and bound to the **role**, never to a person. The engine **recommends**;
it never mutates production behavior without explicit approval.

---

## 1. Completed work

### Domain (`src/Behavior/Domain/`) — pure PHP on the Kernel

- **Identifiers** (extend `Nizam\Kernel\Domain\Identifier`): `RoleId`, `BehaviorProfileId`, `ProposalId`, `ObservationId`.
- **Enums** (native backed, `string`): `DecisionStyle`, `CommunicationStyle`, `ApprovalStyle`, `EscalationStyle`,
  `RiskTolerance`, `PriorityStrategy`, `DelegationStrategy`, `PlanningStrategy`, `FollowUpStrategy`,
  `DocumentationStyle`, `MeetingStyle`, `NegotiationStyle`, `CustomerInteractionStyle`, `QualityExpectation`,
  `ProfileStatus`, `ProposalStatus`, `ObservationSourceType`, plus `BehaviorTraitAxis` (the enumerated trait axes
  used by the consolidator/recommender for deterministic per-trait reasoning).
- **Value objects**: `BehaviorTraits` (14 style traits + `evidenceRequirements`, immutable, `with*()` copy-methods,
  `diff()`, `equals()`, self-validating with the elevated-risk factory guard), `EvidenceReference`,
  `BehaviorRecommendation` (explainability: target/current/recommended/reason/evidence/confidence/howToModify),
  `ChangeLogEntry` (version, changedAt/By, summary, traitsDiff, businessImpact, rollbackToVersion).
- **Entities / revisions**: `BehaviorObservation` (approved-only gating), `BehaviorProfileRevision` (immutable
  historical record: version, traits, changeLog, approvedBy/At, evidence).
- **Aggregates**: `BehaviorProfile` (`draft`, `activate`, `applyApprovedChange`, `rollbackTo`, `archive`;
  append-only revisions, optimistic `version`, role-bound) and `BehaviorChangeProposal`
  (`approve`/`reject`/`withdraw`; nothing auto-applies).
- **Domain events** (implement `Nizam\Kernel\Domain\DomainEvent`): `BehaviorProfileDrafted`,
  `BehaviorProfileActivated`, `BehaviorChangeProposed`, `BehaviorChangeApproved`, `BehaviorChangeRejected`,
  `BehaviorChangeWithdrawn`, `BehaviorProfileVersionActivated`, `BehaviorProfileRolledBack`, `BehaviorProfileArchived`.
- **Domain services**: `BehaviorProfileConsolidator` (role-vs-person: deterministic weighted consolidation across
  **all** employees' approved observations), `BehaviorRecommendationService` (explainable recommendations),
  `RiskTolerancePolicy` (port for elevated-risk gating).
- **Ports**: `BehaviorProfileRepository`, `BehaviorChangeProposalRepository`, `ObservationSource`, `BehaviorEventPublisher`.
- **Exceptions** (`BEHAVIOR.*` codes): `BehaviorDomainException` base, `UnknownRevisionException`,
  `InsufficientEvidenceException`, `ImmutableRoleBindingException`, `ElevatedRiskNotAllowedException`,
  `InvalidProfileTransitionException`, `InvalidProposalTransitionException`, `NoBehaviorChangeException`.

### Application (`src/Behavior/Application/`) — CQRS on domain ports

- **Commands + handlers**: `DraftBehaviorProfile`, `ProposeBehaviorChange`, `ApproveBehaviorChange`,
  `RejectBehaviorChange`, `RollbackBehaviorProfile`, `ArchiveBehaviorProfile`.
- **Queries + handlers**: `GetBehaviorProfile`, `GetBehaviorProfileHistory`, `ListPendingProposals`,
  `ExplainBehaviorProfile` (recommendations + evidence).
- **Read-model DTOs**: `BehaviorProfileView`, `BehaviorRevisionView`, `ProposalView`, `RecommendationView`.
- **Orchestration**: `BehaviorLearningService` (observe → consolidate → recommend → **create proposal only**,
  never auto-apply). `BehaviorApplicationException` for application-layer failures.
- Handlers pull domain events and publish them via `BehaviorEventPublisher`.

### Infrastructure (`src/Behavior/Infrastructure/`)

- **InMemory** adapters (tenant-scoped, seedable): `InMemoryBehaviorProfileRepository`,
  `InMemoryBehaviorChangeProposalRepository`, `InMemoryObservationSource`, `ConfigurableRiskTolerancePolicy`.
- **PDO** adapters (real, sqlite + pgsql dialect): `PdoBehaviorProfileRepository`,
  `PdoBehaviorChangeProposalRepository`, `PdoObservationSource`, and a `BehaviorMapper` hydrator/serializer
  (JSON-encoded traits/revisions/evidence, `tenant_id` scoping on every query, soft-delete aware).
- **Event**: `DispatchingBehaviorEventPublisher` (adapts the port to `Nizam\Platform\Event\EventDispatcher`).
- **Migration**: `001_create_behavior_tables.sql` (Postgres 16 design source: UUIDv7 PKs, `tenant_id`,
  audit/`deleted_at`/`version` columns, `(tenant_id, role_id)` partial indexes, FKs, JSONB trait columns) and
  `SqliteSchema::apply(PDO)` (runnable equivalent for integration tests). `PersistenceDriver` selects dialect.
- **Composition**: `BehaviorServiceProvider` (binds ports → adapters, registers bus handlers) and the
  `BehaviorModule` facade (register + bus registrations) so an Interface layer can be added when the HTTP platform lands.

### Tests (`tests/Unit/Behavior/`, `tests/Integration/Behavior/`)

- **Unit**: `BehaviorTraitsTest`, `BehaviorProfileTest`, `BehaviorEnumTest`, `BehaviorProfileConsolidatorTest`,
  `BehaviorRecommendationServiceTest`, `BehaviorChangeProposalTest`, `BehaviorLearningServiceTest`
  (plus `BehaviorFixtures` and `MutableTestClock` support doubles).
- **Integration**: `PdoBehaviorRepositoryTest` — PDO repositories round-trip on an in-memory sqlite PDO via
  `SqliteSchema`, tenant isolation (a second tenant cannot read the first tenant's rows), and the
  proposal → approve → persisted-profile-version flow.

### Documentation

- `README.md` in every new folder (Purpose / Responsibilities / Dependencies / Public interfaces).
- This audit (`docs/audit/PHASE_16_AUDIT.md`), plus change-log rows appended to `PROJECT_STATE.md` and
  `docs/15-Project-Roadmap.md` recording the Behavior Engine as an implemented, test-green increment.

---

## 2. Architecture validation

- **Clean / Hexagonal dependency direction.** The domain layer imports only `Nizam\Kernel\Domain\*`, its own
  `Nizam\Behavior\Domain\*`, and `Nizam\Platform\Support\Assert` / `Nizam\Platform\Exception\InvalidArgumentException`
  (verified by import scan). No domain class references Application or Infrastructure. I/O lives only in
  Infrastructure behind the domain ports. Application depends on domain ports, never on adapters.
- **Role vs person.** Profiles are bound to `RoleId` (immutable binding, enforced by
  `ImmutableRoleBindingException`); `BehaviorProfileConsolidator` derives a consolidated role profile from the
  **approved practices of all employees performing the role**, not from any individual.
- **Approval-gated.** Only `approved == true` observations may drive evolution; the learning service produces a
  **proposal only** and never mutates a profile; a proposal must be explicitly approved before
  `BehaviorProfile::applyApprovedChange` runs, which additionally requires evidence ≥ `evidenceRequirements`.
- **Versioned & reversible.** Every change appends an immutable `BehaviorProfileRevision`, increments
  `currentVersion`, and is reversible via `rollbackTo` (which restores prior traits as a new append-only version —
  history is never rewritten).
- **Explainable.** `BehaviorRecommendation` carries target trait, current and recommended value, reason,
  non-empty supporting approved evidence, confidence, and how-to-modify; `ExplainBehaviorProfile` surfaces this.
- **Tenant-scoped.** `TenantId` is present on aggregates, observations, and every persistence query; PDO adapters
  filter by `tenant_id` on every read/write, and the integration test proves cross-tenant reads return nothing.
- **Events on the bus.** Aggregate mutators `recordThat(...)`; handlers `pullDomainEvents()` and publish via
  `BehaviorEventPublisher`, adapted to the platform `EventDispatcher` by `DispatchingBehaviorEventPublisher`.

---

## 3. Test results (verified)

Read from a real run — `cd /home/user/crm && vendor/bin/phpunit`:

```
OK (169 tests, 628 assertions)
```

- **Full suite: 169 tests, 628 assertions — all green** on **PHP 8.4.19** with **PHPUnit 11.5.55**.
- The Behavior context contributed **93 tests** on top of the prior **76-test** foundation baseline (76 + 93 = 169).
- Both the unit suite (`tests/Unit/Behavior/`) and the sqlite-backed integration suite
  (`tests/Integration/Behavior/`) pass.

---

## 4. Remaining risks

- **Postgres parity is asserted from the design SQL, not yet executed.** PDO adapters are exercised on sqlite; the
  Postgres 16 dialect (JSONB, partial indexes, UUIDv7 PKs) in `001_create_behavior_tables.sql` has not been run
  against a live Postgres instance, so dialect-specific behavior (JSONB operators, partial-index selection) is
  validated by design review only.
- **No Interface (HTTP/Console) layer.** The module's public surface is currently its command/query buses and
  application services via `BehaviorModule`; there is no end-to-end HTTP path to exercise until the HTTP platform lands.
- **Consolidation heuristics are deterministic but simple.** The weighted per-trait consolidation is intentionally
  transparent; richer statistical signals (recency decay tuning, confidence calibration) are future refinements and
  could shift recommendations as real approved-observation volume grows.
- **Event delivery is in-process.** `DispatchingBehaviorEventPublisher` dispatches synchronously through the
  platform dispatcher; durable/async delivery depends on the not-yet-built queue and outbox infrastructure.

---

## 5. Technical debt (honest)

- **Interface / HTTP layer deferred.** By design, the Interface layer (HTTP/Console controllers) is deferred until
  the HTTP/Console platform layers exist. This is architecturally complete (Interface is the outermost pluggable
  layer), not a stub — but it is deferred work, tracked here explicitly.
- **PDO adapters validated on sqlite pending a Postgres integration environment.** The PDO repositories are proven
  round-tripping and tenant-isolating on an in-memory sqlite PDO; a Postgres integration environment is required
  to validate the production dialect end-to-end. The Postgres migration remains the design source of record.
- **No cross-module wiring yet.** `BehaviorServiceProvider`/`BehaviorModule` bind the context in isolation; they are
  not yet registered by a real application composition root beyond the module facade.

---

## 6. Recommendations before the next phase

1. **Stand up a Postgres integration environment** and re-run the PDO round-trip / tenant-isolation suite against
   Postgres 16 to promote the migration from design-validated to execution-validated.
2. **Add the Interface layer when the HTTP platform lands** — thin HTTP/Console adapters over the existing buses
   (the domain and application layers already expose the full surface via `BehaviorModule`), including the
   non-technical "Behavior Review" screen (Basic/Advanced, `(!)` help popups) described in the module spec.
3. **Author `docs/24-Professional-Behavior-Engine.md` and ADR-0019** to complete the governance record
   (module doc with Mermaid class/sequence/ER diagrams and the review-screen UX; ADR: "Professional Behavior modeled
   per-Role, evolved only via approved observations; profiles versioned, explainable, reversible, approval-gated").
4. **Wire the outbox/queue for durable event delivery** once the platform queue exists, so behavior events survive
   process boundaries.
5. **Register `BehaviorServiceProvider` in the application composition root** when a host application exists, and add
   a smoke test that boots the module through the container.

---

## Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0.0 | 2026-07-01 | Architecture (Nizam Core) | Initial Phase-16 audit: Professional Behavior Engine delivered and test-green (169 tests / 628 assertions on PHP 8.4.19 + PHPUnit 11.5.55; 93 Behavior tests). |
