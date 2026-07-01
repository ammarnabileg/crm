# Behavior\Domain\Port

**Purpose.** The domain-facing contracts (hexagonal ports) that the Behavior domain and application
layers depend on, and that Infrastructure adapters implement. Keeping these here lets the domain
express *what* it needs (persistence, approved practice, event egress) without knowing *how* it is
provided.

**Responsibilities.**
- `BehaviorProfileRepository` — persist and load `BehaviorProfile` aggregates
  (`save`, `ofId`, `ofRole`, `nextIdentity`, `existsForRole`); tenant-scoped.
- `BehaviorChangeProposalRepository` — persist and load `BehaviorChangeProposal` aggregates
  (`save`, `ofId`, `pendingForTenant`, `nextIdentity`); tenant-scoped.
- `ObservationSource` — the sole channel for reading **approved** observations for a role
  (`approvedObservationsForRole`), optionally since a point in time.
- `BehaviorEventPublisher` — the egress for pulled domain events (`publish`).

**Dependencies.** The Behavior domain aggregates, entities, and identifiers;
`Nizam\Kernel\Domain\{TenantId,DomainEvent}`. No I/O — these are interfaces only.

**Public interfaces.** The four interfaces above. Every operation that reads or writes tenant data
is tenant-scoped by contract; adapters must never leak data across tenants.
