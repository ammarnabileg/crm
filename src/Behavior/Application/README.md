# Behavior\Application

**Purpose.** The CQRS use-case layer of the Professional Behavior Engine. It orchestrates the pure
`Behavior\Domain` through its ports to carry out the platform's behavior use cases — draft a role's
profile, learn a proposal from approved practice, approve/reject that proposal, roll a profile back,
archive it, and read profiles, history, pending proposals, and explanations. It holds **no business
rules of its own**: every invariant lives in the domain; this layer only loads aggregates via
repository ports, invokes domain behavior, saves, and publishes the pulled domain events. The engine
**recommends**; nothing here ever auto-applies a change without an explicit approval command.

**Responsibilities.**
- `Command/` — one immutable command DTO (`Nizam\Kernel\Application\Command`) plus one
  `Nizam\Kernel\Application\CommandHandler` per use case:
  - `DraftBehaviorProfile` — draft a new role profile (one-profile-per-role enforced).
  - `ProposeBehaviorChange` — raise a proposal from approved practice (delegates to
    `BehaviorLearningService`; **proposal only**, never applied).
  - `ApproveBehaviorChange` — approve a pending proposal and, in the same unit of work, apply it to
    the target profile as a new appended revision (rollback proposals restore an earlier version).
  - `RejectBehaviorChange`, `RollbackBehaviorProfile`, `ArchiveBehaviorProfile`.
  Handlers save via the repository ports and publish pulled events via `BehaviorEventPublisher`.
- `Query/` — one query DTO (`Nizam\Kernel\Application\Query`) plus one
  `Nizam\Kernel\Application\QueryHandler` per read: `GetBehaviorProfile`, `GetBehaviorProfileHistory`,
  `ListPendingProposals`, `ExplainBehaviorProfile`. Handlers mutate nothing and return `Dto/` views.
- `Dto/` — read models (`BehaviorProfileView`, `BehaviorRevisionView`, `ProposalView`,
  `RecommendationView`) built of scalars and plain arrays only; safe to serialize and transport.
- `Service/BehaviorLearningService` — the observe→consolidate→recommend→**create-proposal-only** loop;
  returns a `ProposalView` and never mutates the profile.
- `Exception/BehaviorApplicationException` — typed orchestration failures (not-found, already-exists,
  nothing-to-propose) with stable `BEHAVIOR.APPLICATION.*` codes.

**Dependencies.** `Nizam\Behavior\Domain\*` (aggregates, entities, value objects, enums, ports,
domain services); `Nizam\Kernel\Application\{Command,Query,CommandHandler,QueryHandler}`;
`Nizam\Kernel\Domain\{Clock,TenantId,DomainEvent}`; `Nizam\Platform\Support\Assert`; and PHP itself.
No framework, no persistence, no HTTP — all I/O is behind the domain ports, whose concrete adapters
live in `Behavior\Infrastructure`.

**Public interfaces (for the layers above).**
- Commands: `DraftBehaviorProfile`, `ProposeBehaviorChange`, `ApproveBehaviorChange`,
  `RejectBehaviorChange`, `RollbackBehaviorProfile`, `ArchiveBehaviorProfile` (+ their handlers).
- Queries: `GetBehaviorProfile`, `GetBehaviorProfileHistory`, `ListPendingProposals`,
  `ExplainBehaviorProfile` (+ their handlers).
- Read models: `BehaviorProfileView`, `BehaviorRevisionView`, `ProposalView`, `RecommendationView`.
- Application service: `BehaviorLearningService`.
- Exception: `BehaviorApplicationException`.

**Key rules honored.**
- Learning produces a **proposal only** — never a direct behavior mutation.
- A profile change is applied **only** on an explicit `ApproveBehaviorChange` (or a direct
  `RollbackBehaviorProfile`/`ArchiveBehaviorProfile` operator command).
- Every use case is tenant-scoped: repositories are queried with the acting `TenantId`, so a tenant
  can never read or change another tenant's behavior.
- After each committed change, handlers pull the aggregates' recorded domain events and publish them
  through `BehaviorEventPublisher`.
