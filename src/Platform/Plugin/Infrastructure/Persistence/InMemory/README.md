# Plugin\Infrastructure\Persistence\InMemory

**Purpose.** A real, non-durable `PluginRepository` for tests and for running the module without a
database.

**Responsibilities.**
- `InMemoryPluginRepository` — holds `RegisteredPlugin` aggregates in a process-local map keyed by
  `PluginId`. Uninstalled plugins are soft-deleted: excluded from `ofName()`, `all()`, `byKind()` and
  `enabled()` but still addressable by `ofId()`. Seedable via `seed()`. Not persistent, not
  concurrency safe.

**Dependencies.** `Nizam\Platform\Plugin\{Port\PluginRepository,PluginId,PluginKind,PluginState,RegisteredPlugin}`, PHP.

**Public interfaces.** `InMemoryPluginRepository implements PluginRepository` (adds `seed()`).
