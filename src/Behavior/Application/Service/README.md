# Behavior\Application\Service

**Purpose.** Application services that coordinate multiple domain collaborators for a single, coherent
use case that is larger than one command handler. Today this is the engine's learning loop.

**Responsibilities.**
- `BehaviorLearningService` — the observe→consolidate→recommend→**create-proposal-only** orchestration:
  1. *observe* — read the role's approved observations through the `ObservationSource` port;
  2. *consolidate* — derive the supported trait set with the deterministic
     `BehaviorProfileConsolidator` (role-vs-person: the practice of all employees in the role);
  3. *recommend* — compute the explainable per-trait changes with `BehaviorRecommendationService`;
  4. *create a proposal only* — when a defensible change exists, raise one pending
     `BehaviorChangeProposal`, persist it, publish its events, and return a `ProposalView`.

  The service **reads** the profile but never saves it: it never activates a version, never rolls
  back, and never mutates behavior. A human must approve the proposal before anything changes.

**Dependencies.** The domain ports (`BehaviorProfileRepository`, `BehaviorChangeProposalRepository`,
`ObservationSource`, `BehaviorEventPublisher`); the domain services (`BehaviorProfileConsolidator`,
`BehaviorRecommendationService`); `Nizam\Kernel\Domain\{Clock,TenantId}`; the `Dto/ProposalView`
read model and `BehaviorApplicationException`.

**Public interfaces.** `BehaviorLearningService::learn(string $tenantId, string $roleId,
string $proposedBy, ?string $since = null): ProposalView`. Raises `BehaviorApplicationException` when
no profile exists for the role or the approved practice warrants no change.
