# Behavior\Application\Query

**Purpose.** The read side of the Behavior CQRS layer: requests for data that never change state.
Each read is one immutable query DTO plus one handler that loads through a domain port and returns a
`Dto/` view built of scalars and plain arrays.

**Responsibilities.**
- `GetBehaviorProfile` / `GetBehaviorProfileHandler` — the current state of a profile (no history),
  as a `BehaviorProfileView`.
- `GetBehaviorProfileHistory` / `GetBehaviorProfileHistoryHandler` — a profile with its full,
  ordered, append-only revision history materialized as `BehaviorRevisionView`s.
- `ListPendingProposals` / `ListPendingProposalsHandler` — the tenant's review queue as a list of
  `ProposalView`s, in proposed-at order.
- `ExplainBehaviorProfile` / `ExplainBehaviorProfileHandler` — the engine's transparency surface: the
  explainable `RecommendationView`s the role's approved practice supports, each with reason, evidence,
  confidence, and how-to guidance. Produces no proposal.

**Dependencies.** `Nizam\Kernel\Application\{Query,QueryHandler}`; the domain ports
(`BehaviorProfileRepository`, `BehaviorChangeProposalRepository`, `ObservationSource`); the domain
service `BehaviorRecommendationService`; `Nizam\Kernel\Domain\TenantId`;
`Nizam\Platform\Support\Assert`; the `Dto/` read models and `BehaviorApplicationException`.

**Public interfaces.** The four query DTOs above (dispatched on a `QueryBus`) and their handlers
(registered against the bus keyed by query class). Handlers return `Dto/` views or lists of them.

**Rules.** Every query is tenant-scoped and side-effect free. A missing tenant-scoped aggregate
raises `BehaviorApplicationException` rather than leaking another tenant's data.
