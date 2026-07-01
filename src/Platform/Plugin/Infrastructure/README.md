# Plugin\Infrastructure

**Purpose.** The I/O edge of the Plugin Platform: the concrete adapters that implement the module's
hexagonal ports (persistence, discovery sources, event publishing, entry-point instantiation, health-
check resolution), the database schema, the container wiring, and the single test-only reference
plugin. Nothing here is depended on by the domain, services or application layers — they depend only
on the ports in `Port/`, and this layer plugs adapters into those ports.

**Responsibilities.**
- `Persistence/InMemory/` — a real, seedable in-memory `PluginRepository`.
- `Persistence/Pdo/` — a durable `PluginRepository` for SQLite and PostgreSQL, plus its row/aggregate
  hydrator (JSON manifest, soft-delete aware, tenant/global scoping).
- `Source/` — the `PluginSource` adapters: a directory scanner reading `plugin.json`, and an array
  source for tests and programmatic registration.
- `Event/` — the dispatching `PluginEventPublisher` bridging pulled domain events onto the platform
  PSR-14 dispatcher.
- `Container/` — the container-backed `PluginInstantiator` and `HealthCheckResolver`.
- `Migration/` — the Postgres 16 schema (design source of record) and the runnable SQLite equivalent.
- `Testing/` — the reference `WorkerPlugin` used only by the test suite.
- `PluginServiceProvider` — binds every port to an adapter and registers the services and the
  `PluginManager` facade in the container.
- `PersistenceDriver` — selects in-memory vs PDO persistence at wiring time.

**Dependencies.** `Nizam\Platform\Plugin\{Port,Manager,Service,...}`, `Nizam\Platform\Container`,
`Nizam\Platform\Event`, `Nizam\Platform\Support`, `Nizam\Platform\Exception`, `Nizam\Kernel\Domain`,
PSR-3 logging, PDO, PHP.

**Public interfaces.** `PluginServiceProvider::register()` / `boot()`; `PersistenceDriver`; the adapter
classes in each sub-namespace (resolved through the container by their port interface).
