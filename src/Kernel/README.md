# Kernel

**Purpose.** `Nizam\Kernel\*` — the shared DDD kernel that every bounded context reuses. It contains **no business rules and no I/O**; it depends only on PHP, the PSR clock interface, and `Nizam\Platform\Support`/`Exception`.

**Responsibilities.**
- `Domain/` — the DDD building blocks: `Identifier` (UUID v7) + `TenantId`/`UserId`, `AggregateRoot`, `Entity`, `ValueObject`, `DomainEvent`, `RecordsDomainEvents`, and the `Clock` port.
- `Application/` — the CQRS-style application contracts and simple in-memory command/query buses.
- `Tenancy/` — the per-request tenant carrier (`TenantContext`) and request snapshot (`RequestContext`).

**Dependencies.** `psr/clock`; `Nizam\Platform\Support`; `Nizam\Platform\Exception`. Nothing else — this is the innermost layer, and all dependencies point inward toward it.

**Public interfaces.** See `Domain/`, `Application/`, and `Tenancy/` READMEs.
