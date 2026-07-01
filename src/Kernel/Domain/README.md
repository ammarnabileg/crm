# Kernel\Domain

**Purpose.** The shared DDD building blocks every bounded context reuses. No business rules, no I/O — depends only on PHP and `Platform\Support`/`Platform\Exception`.

**Responsibilities.**
- `Identifier` (abstract, immutable UUID v7) + `TenantId`, `UserId` — typed ids with `generate`/`fromString`/`toString`/`equals`.
- `AggregateRoot`, `Entity` — identity-based domain objects; aggregates use `RecordsDomainEvents`.
- `ValueObject` — structural-equality marker (`equals`).
- `DomainEvent` — `occurredAt`/`eventName`/`aggregateId`.
- `RecordsDomainEvents` — trait: `recordThat`/`pullDomainEvents`/`releaseEvents`.
- `Clock` — the time port (extends `Psr\Clock\ClockInterface`).

**Dependencies.** `psr/clock`; `Nizam\Platform\Support\Uuid`; `Nizam\Platform\Exception`.

**Public interfaces.** `Identifier`, `TenantId`, `UserId`, `AggregateRoot`, `Entity`, `ValueObject`, `DomainEvent`, `RecordsDomainEvents`, `Clock`.
