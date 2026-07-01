# Behavior\Infrastructure

**Purpose.** The outward-facing (driven) adapters that make the Behavior domain and application layers
run against real technology — databases, the platform event pipeline, and the DI container. Every
class here implements a domain port or wires the module; the domain and application layers never
depend on anything in this folder, only on the ports it satisfies.

**Responsibilities.**
- **Persistence adapters** (`Persistence/InMemory`, `Persistence/Pdo`) — concrete
  `BehaviorProfileRepository`, `BehaviorChangeProposalRepository`, and `ObservationSource`
  implementations, plus the `RiskTolerancePolicy` adapter, all tenant-scoped.
- **Event egress** (`Event`) — `DispatchingBehaviorEventPublisher` adapts the `BehaviorEventPublisher`
  port to the platform PSR-14 `EventDispatcher`.
- **Schema** (`Migration`) — the Postgres 16 migration (design source of record) and the runnable
  SQLite equivalent for integration tests.
- **Wiring** — `BehaviorServiceProvider` binds every port to an adapter and registers the command/
  query handlers on the platform buses; `PersistenceDriver` selects in-memory vs. PDO storage.

**Dependencies.** The Behavior `Domain` and `Application` layers; `Nizam\Kernel\{Domain,Application}`;
`Nizam\Platform\{Container,Event,Support,Exception}`; and PHP's `PDO`. No other bounded context.

**Public interfaces.** `BehaviorServiceProvider` (installed via the `BehaviorModule` facade) and the
`PersistenceDriver` enum are the entry points a host uses. The adapter classes are internal detail —
consumers depend on the domain ports, which the container resolves to the adapters bound here.
