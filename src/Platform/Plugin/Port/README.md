# Platform\Plugin\Port

**Purpose.** The hexagonal ports of the Plugin Platform: the contracts the domain and application
layers depend on and that Infrastructure adapters implement. Keeping them here lets the platform
express *what* it needs — persistence, discovery, event egress, health, instantiation — without
knowing *how* it is provided, so no I/O leaks into the domain.

**Responsibilities.**
- `PluginRepository` — persist and load `RegisteredPlugin` aggregates (`save`, `ofId`, `ofName`,
  `all`, `byKind`, `enabled`); adapters soft-delete uninstalled plugins and exclude them from lookups.
- `PluginSource` — discover available plugins (`discover(): list<DiscoveredPlugin>`); implemented by a
  directory scanner, an array source, etc.
- `DiscoveredPlugin` — the value object a source returns: a `PluginManifest` plus its opaque source
  `locator` (also exposes `identityKey()` for name+version de-duplication).
- `PluginEventPublisher` — the egress for pulled domain events (`publish(list<DomainEvent>)`).
- `HealthCheck` — a plugin-supplied check of its own operational health
  (`check(PluginContext): PluginHealthStatus`).
- `HealthCheckResolver` — resolve a manifest's declared `healthCheckClass` into a live `HealthCheck`
  (`resolve(PluginManifest): ?HealthCheck`, null when none is declared), keeping resolution I/O out of
  the `PluginHealthChecker`.
- `PluginInstantiator` — resolve a manifest's `entryPointClass` into a live `PluginInterface`
  (`make(PluginManifest): PluginInterface`), isolating construction failures behind the port.

**Dependencies.** `Nizam\Kernel\Domain\DomainEvent`, and the Plugin domain types
(`RegisteredPlugin`, `PluginId`, `PluginKind`, `PluginManifest`, `PluginInterface`, `PluginContext`,
`PluginHealthStatus`). Interfaces + one pure value object — no I/O.

**Public interfaces.** The seven port interfaces above plus the `DiscoveredPlugin` value object.
Repository and source operations are tenant-aware by contract where a tenant scope applies; adapters
must never leak data across tenants.
