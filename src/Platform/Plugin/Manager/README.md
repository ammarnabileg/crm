# Platform\Plugin\Manager

**Purpose.** The application (use-case) layer of the Plugin Platform: it orchestrates the domain
services and ports into the platform's plugin lifecycle — discover, install, update, enable, disable,
uninstall, health-check — and exposes a single tenant-aware façade (`PluginManager`) over the whole
substrate. These classes hold no persistence or dispatch logic of their own; they compose the
`Service` layer and the `Port` interfaces, so all I/O stays behind ports in Infrastructure.

**Responsibilities.**
- `PluginRegistry` — the read-oriented index over the `PluginRepository`: `register()` (persist),
  `find()`/`get()` (by name; `get()` throws `PluginNotFoundException`), `all()`, `byKind()`,
  `enabled()`, `has()`. Every lookup concerns live installs only.
- `PluginDiscoveryService` — `discover(list<PluginSource>): list<DiscoveredPlugin>`. Merges what every
  source exposes, de-duplicates by plugin name + version (first source wins, stable order), and records
  and publishes a `PluginDiscovered` event per distinct discovery. Read-only.
- `PluginInstaller` — `install(DiscoveredPlugin, platformVersion, ?TenantId): RegisteredPlugin`
  (validate via `PluginValidator` → resolve required dependencies via `PluginDependencyResolver` →
  persist in `Installed` → publish `PluginInstalled`); `update(name, DiscoveredPlugin, platformVersion):
  UpdateOutcome` (aggregate enforces strictly-newer SemVer; publishes `PluginUpdated`; the returned
  `UpdateOutcome` retains the superseded manifest/source); `rollback(UpdateOutcome): RegisteredPlugin`
  (reverses an update by retiring the newer record and re-installing the retained prior version).
- `PluginLifecycleManager` — `enable()`/`disable()`/`uninstall()`, each returning a `Result`. Guards the
  transition (`enable()` requires every **required** dependency installed, enabled and version-compatible;
  optional dependencies never block), mutates the aggregate, runs the plugin's optional
  `PluginLifecycleHooks` callback **inside the `PluginSandbox`** so a throwing hook is contained, then
  persists and publishes. A faulted hook marks the plugin `Failed` and yields a failure `Result`.
- `PluginManager` (façade) — the public surface: `discover`, `install`, `update`, `rollback`, `enable`,
  `disable`, `uninstall`, `health`, `get`/`find`, `all`, `byKind`, `enabled`. Tenant-aware: install/update
  take an optional owning `TenantId` (null = global); `health()` runs within a supplied or derived
  `PluginContext` carrying the plugin's tenant scope and config-schema defaults.
- `UpdateOutcome` — the value returned by `update()`: the updated `RegisteredPlugin` paired with the
  `previousManifest`/`previousSource` it superseded, consumed by `rollback()`.

**Dependencies.** `Nizam\Platform\Plugin\Service\{PluginValidator,PluginDependencyResolver,
PluginSandbox,PluginHealthChecker}`; the ports `Nizam\Platform\Plugin\Port\{PluginRepository,
PluginSource,PluginEventPublisher,PluginInstantiator}`; the domain
`Nizam\Platform\Plugin\{RegisteredPlugin,PluginManifest,PluginContext,PluginId,PluginKind,
PluginHealthStatus,PluginDependency,PluginState,SemanticVersion}` and its events/exceptions;
`Nizam\Kernel\Domain\{Clock,TenantId}`; `Nizam\Platform\Support\Result`. No direct I/O — persistence and
event dispatch live behind ports.

**Public interfaces.** `PluginManager` (the façade all callers use); `PluginRegistry`,
`PluginDiscoveryService`, `PluginInstaller`, `PluginLifecycleManager` and `UpdateOutcome` for wiring and
finer-grained composition.
