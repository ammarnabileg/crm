# Plugin\Infrastructure\Persistence

**Purpose.** Concrete `PluginRepository` adapters that persist and load `RegisteredPlugin` aggregates.

**Responsibilities.**
- `InMemory/InMemoryPluginRepository` — a real, seedable, process-local repository. Models the live-
  vs-uninstalled (soft-delete) distinction and name/kind/enabled lookups exactly as the durable
  adapter does, so code depending on the port behaves identically against either. Not durable.
- `Pdo/PdoPluginRepository` — a durable repository for SQLite and PostgreSQL: parameterised upsert,
  live reads filtered by `deleted_at IS NULL`, id lookups reaching soft-deleted rows for history,
  ordered listing.
- `Pdo/PluginHydrator` — the stateless row ⇄ aggregate translator: encodes the manifest as a single
  JSON document, maps the last-known health to three columns, derives `deleted_at` from the
  uninstalled state, and rebuilds aggregates via `RegisteredPlugin::reconstitute()` (no events).

**Dependencies.** `Nizam\Platform\Plugin\{Port\PluginRepository,RegisteredPlugin,PluginManifest,...}`,
`Nizam\Platform\Support\Json`, `Nizam\Platform\Exception`, `Nizam\Kernel\Domain\TenantId`, PDO, PHP.

**Public interfaces.** `InMemoryPluginRepository` (+ `seed()`), `PdoPluginRepository`, `PluginHydrator`
(`toRow()` / `fromRow()`) — all implementing or serving `PluginRepository`.
