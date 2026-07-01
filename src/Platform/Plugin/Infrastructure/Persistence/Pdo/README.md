# Plugin\Infrastructure\Persistence\Pdo

**Purpose.** A durable, soft-delete-aware `PluginRepository` for SQLite and PostgreSQL, and the
hydrator that translates between plugin aggregates and their persisted rows.

**Responsibilities.**
- `PdoPluginRepository` — one row per aggregate in `plugin_registry`; portable "update, else insert"
  upsert; every live read filters `deleted_at IS NULL`; `ofId()` reaches soft-deleted rows so history
  and re-install checks work; tenant-scoped and global (`tenant_id` NULL) plugins share the table with
  `name` unique among live rows.
- `PluginHydrator` — stateless translator: `toRow()` flattens an aggregate (manifest → JSON document,
  health → three columns, `deleted_at` derived from the uninstalled state); `fromRow()` rebuilds the
  aggregate via `RegisteredPlugin::reconstitute()` with no event emission.

**Dependencies.** `Nizam\Platform\Plugin\{Port\PluginRepository,RegisteredPlugin,PluginManifest,PluginId,PluginState,PluginKind,PluginHealthStatus,HealthLevel}`,
`Nizam\Platform\Support\Json`, `Nizam\Platform\Exception\PlatformException`,
`Nizam\Kernel\Domain\TenantId`, PDO, PHP.

**Public interfaces.** `PdoPluginRepository implements PluginRepository`; `PluginHydrator` (`toRow()`,
`fromRow()`).
