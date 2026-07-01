# Behavior\Application\Command

**Purpose.** The write side of the Behavior CQRS layer: the intents that change state. Each use case
is one immutable command DTO plus one handler; handlers orchestrate the domain and never encode
business rules themselves.

**Responsibilities.**
- `DraftBehaviorProfile` / `DraftBehaviorProfileHandler` — draft a new role profile at version 1,
  enforcing the one-profile-per-role rule; returns the new profile id.
- `ProposeBehaviorChange` / `ProposeBehaviorChangeHandler` — raise a proposal from a role's approved
  practice by delegating to `BehaviorLearningService`; **proposal only**, nothing applied.
- `ApproveBehaviorChange` / `ApproveBehaviorChangeHandler` — approve a pending proposal and apply it
  to its target profile as a new appended revision (rollback proposals restore an earlier version);
  publishes events from both aggregates.
- `RejectBehaviorChange` / `RejectBehaviorChangeHandler` — reject a pending proposal; leaves the
  profile untouched.
- `RollbackBehaviorProfile` / `RollbackBehaviorProfileHandler` — restore an earlier version's traits
  as a new, reversible revision.
- `ArchiveBehaviorProfile` / `ArchiveBehaviorProfileHandler` — retire a profile, retaining history.

**Dependencies.** `Nizam\Kernel\Application\{Command,CommandHandler}`; the domain ports
(`BehaviorProfileRepository`, `BehaviorChangeProposalRepository`, `BehaviorEventPublisher`); the
domain aggregates and value objects; `BehaviorLearningService`; `Nizam\Kernel\Domain\Clock`;
`Nizam\Platform\Support\Assert`; the `Dto/` read models and `BehaviorApplicationException`.

**Public interfaces.** The six command DTOs above (dispatched on a `CommandBus`) and their handlers
(registered against the bus keyed by command class). Handlers return either the new aggregate id
(draft) or a read-model view of the affected aggregate.

**Rules.** Every handler is tenant-scoped, loads via a repository port, calls domain behavior, saves,
then pulls and publishes the recorded domain events. No handler mutates behavior implicitly — a
change is applied only by an explicit approve/rollback/archive command.
