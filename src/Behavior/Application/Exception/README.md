# Behavior\Application\Exception

**Purpose.** Typed failures raised by the Behavior application layer when a use case cannot be carried
out for orchestration reasons — as distinct from domain invariant violations, which surface as
`Nizam\Behavior\Domain\Exception\BehaviorDomainException`.

**Responsibilities.**
- `BehaviorApplicationException` — a single, `final` exception with named constructors for each
  orchestration failure and a stable, dotted error code under `BEHAVIOR.APPLICATION.*`:
  - `profileNotFound` / `profileForRoleNotFound` — the referenced profile (by id or by role) does not
    exist for the acting tenant.
  - `proposalNotFound` — the referenced proposal does not exist for the acting tenant.
  - `profileAlreadyExistsForRole` — a draft was requested for a role that already has a profile.
  - `noChangeToPropose` — the role's approved practice already matches its profile, so no proposal can
    be raised.
  Callers branch on `errorCode()` without matching on messages.

**Dependencies.** PHP `\RuntimeException` only — the application layer stays coupled to PHP, the
Kernel, and its own domain, honoring the hexagonal boundary.

**Public interfaces.** `BehaviorApplicationException` with its named constructors and
`errorCode(): string`. Because tenant-scoped repository misses raise these instead of returning
another tenant's data, they double as the layer's tenant-isolation guard for the read/write paths.
