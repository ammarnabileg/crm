# Behavior\Application\Dto

**Purpose.** The read models returned by the Behavior application layer. Each view is a transport-safe
projection of a domain object into scalars and plain arrays, so it can be serialized to JSON,
rendered by the (deferred) Interface layer, and returned across a bus without exposing a domain
aggregate, value object, or its behavior.

**Responsibilities.**
- `BehaviorProfileView` — a profile's identity, role binding, status, current version and traits,
  timestamps, optimistic version, and (optionally) its full revision history. Built via
  `fromDomain(BehaviorProfile, bool $includeHistory)`.
- `BehaviorRevisionView` — one point in a profile's append-only history: version, traits, change-log,
  approver, instant, evidence, and whether it was a rollback.
- `ProposalView` — a change proposal in full: proposed traits, rationale, supporting evidence,
  confidence, business impact, optional rollback target, status, and decision metadata.
- `RecommendationView` — one explainable trait recommendation: target trait, current/recommended
  values, reason, supporting evidence, confidence, and how-to guidance.

**Dependencies.** The domain objects they project (`Nizam\Behavior\Domain\*`) via their `toArray()`
and accessor methods; PHP `DateTimeInterface` for ISO-8601 formatting. Nothing else — no ports, no
I/O, no framework.

**Public interfaces.** Each view exposes public readonly properties, a static `fromDomain(...)`
factory, and a `toArray()` serialization. Views are immutable data holders with no behavior beyond
projection.
