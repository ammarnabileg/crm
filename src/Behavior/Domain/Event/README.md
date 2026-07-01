# Behavior\Domain\Event

**Purpose.** The domain events that announce every meaningful change in the Behavior domain. Each
implements `Nizam\Kernel\Domain\DomainEvent` and is buffered by an aggregate via `recordThat()`; the
application layer pulls them with `pullDomainEvents()` and publishes them through
`Port\BehaviorEventPublisher` after the unit of work commits.

**Responsibilities.**
- Profile lifecycle: `BehaviorProfileDrafted`, `BehaviorProfileActivated`,
  `BehaviorProfileVersionActivated` (an approved change appended a new version),
  `BehaviorProfileRolledBack`, `BehaviorProfileArchived`.
- Proposal lifecycle: `BehaviorChangeProposed`, `BehaviorChangeApproved`, `BehaviorChangeRejected`,
  `BehaviorChangeWithdrawn`.
- Each event carries the tenant, role, aggregate id, and the payload a subscriber needs (version,
  traits diff, reason, …), plus the stable `eventName()` used for routing and serialization.

**Dependencies.** `Nizam\Kernel\Domain\{DomainEvent,TenantId}`; the Behavior identifiers. No I/O.

**Public interfaces.** The nine event classes and their accessors; `eventName()` values are stable
(`behavior.profile_drafted`, `behavior.profile_activated`, `behavior.profile_version_activated`,
`behavior.profile_rolled_back`, `behavior.profile_archived`, `behavior.change_proposed`,
`behavior.change_approved`, `behavior.change_rejected`, `behavior.change_withdrawn`).
