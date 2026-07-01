# Behavior\Infrastructure\Persistence\InMemory

**Purpose.** Real, tenant-scoped, in-memory implementations of the Behavior persistence and policy
ports. They let the module run and be exercised end-to-end without a database, while faithfully
modelling the tenant isolation and approval filtering the PDO adapters enforce.

**Responsibilities.**
- `InMemoryBehaviorProfileRepository` — stores profiles in a per-tenant map; `ofId`/`ofRole`/
  `existsForRole` are tenant-scoped.
- `InMemoryBehaviorChangeProposalRepository` — stores proposals per tenant; `pendingForTenant`
  returns only pending proposals, ordered by `proposedAt`.
- `InMemoryObservationSource` — seedable; returns only approved observations for the requested
  tenant/role, honoring the optional `since` lower bound and ordering by occurrence time.
- `ConfigurableRiskTolerancePolicy` — grants/denies elevated-risk allowances per (tenant, role),
  denying by default.

**Dependencies.** The Behavior `Domain` aggregates, entities, value objects, enums, and ports;
`Nizam\Kernel\Domain\TenantId`. No I/O.

**Public interfaces.** Implements `BehaviorProfileRepository`, `BehaviorChangeProposalRepository`,
`ObservationSource`, and `RiskTolerancePolicy`. The observation source and policy add seeding helpers
(`seed`/`add`/`clear`, `allow`/`revoke`) for tests and fixtures.
