# Behavior\Infrastructure\Persistence

**Purpose.** The persistence adapters for the Behavior module's repository and read ports, in two
interchangeable flavors that satisfy the exact same domain contracts: an ephemeral in-memory set for
tests and no-database wiring, and a durable PDO set for SQLite and PostgreSQL.

**Responsibilities.**
- `InMemory/` — real, tenant-scoped, process-local adapters and the configurable risk-tolerance
  policy. Seedable where a source of data is needed.
- `Pdo/` — real PDO adapters (parameterized queries, tenant scoping, soft-delete awareness,
  JSON-encoded aggregates) plus the shared `BehaviorMapper` that translates rows to and from
  aggregates.

Both flavors honor the same invariants: every read is scoped to its tenant, so data never leaks
across tenants; only approved observations are ever returned by the observation source; and proposal/
profile writes are upserts that keep exactly one row per aggregate.

**Dependencies.** The Behavior `Domain` (aggregates, value objects, enums, ports);
`Nizam\Kernel\Domain\TenantId`; `Nizam\Platform\Support\Json`; `Nizam\Platform\Exception`; PHP `PDO`.

**Public interfaces.** The adapters implement `BehaviorProfileRepository`,
`BehaviorChangeProposalRepository`, `ObservationSource`, and `RiskTolerancePolicy`. Consumers depend
on those ports; the `BehaviorServiceProvider` binds them to the concrete adapters here.
