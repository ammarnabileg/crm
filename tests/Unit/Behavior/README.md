# tests/Unit/Behavior

## Purpose
Unit tests for the **Behavior** bounded context (`Nizam\Behavior\*`): the pure-domain aggregates,
value objects, enums, and stateless domain/application services that model professional behavior
profiles. These tests run entirely in memory with no database or I/O.

## Responsibilities
- `BehaviorTraitsTest` — equality, per-trait `diff`, copy-on-write immutability, and the
  policy-gated elevated-risk guard on `BehaviorTraits`.
- `BehaviorProfileTest` — draft/activate/`applyApprovedChange` (version increment, appended
  revision, emitted events), insufficient-evidence guard, append-only and reversible `rollbackTo`,
  and the immutable per-role binding.
- `BehaviorEnumTest` — case counts, unique lowercase string values, `from()` round-trips, and the
  enum helper predicates.
- `BehaviorProfileConsolidatorTest` — the role-vs-person rule: one consolidated profile derived from
  the approved practice of MULTIPLE employees; determinism, evidence-bar derivation and cap, risk
  clamping, and rejection of unapproved / foreign-role input.
- `BehaviorRecommendationServiceTest` — explainability: every recommendation carries a reason,
  non-empty supporting evidence, an in-range confidence, and `howToModify` guidance.
- `BehaviorChangeProposalTest` — approve/reject/withdraw transitions, guards, and the invariant that
  approval auto-applies nothing to a profile.
- `BehaviorLearningServiceTest` — the learning loop produces a single PENDING proposal only and
  never mutates the profile.

## Dependencies
- `phpunit/phpunit` ^11 (PHPUnit 11 attributes: `#[CoversClass]`, `#[DataProvider]`).
- The Behavior domain/application code under test, plus the in-memory Infrastructure adapters.
- Shared test support in this directory:
  - `BehaviorFixtures` (trait) — valid `BehaviorTraits`, `EvidenceReference`, and approved
    `BehaviorObservation` factories.
  - `MutableTestClock` — a controllable `Clock` for deterministic timestamps/ordering.

## Public interfaces
Test classes only; no production code lives here. Helpers are `internal` test support and are not
part of the module's public surface.
