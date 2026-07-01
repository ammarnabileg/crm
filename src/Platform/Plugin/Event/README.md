# Platform\Plugin\Event

**Purpose.** The domain events the Plugin Platform records as plugins move through their lifecycle.
Each implements `Nizam\Kernel\Domain\DomainEvent` (`occurredAt()`, `eventName()`, `aggregateId()`) and
carries a stable dotted `eventName()` under the `plugin.*` namespace. `RegisteredPlugin` buffers these
via `recordThat()`; the application layer pulls them after the unit of work commits and publishes them
through the `PluginEventPublisher` port.

**Responsibilities.** Represent the seven plugin facts:
- `PluginDiscovered` (`plugin.discovered`) — a source found a plugin name+version (keyed by name).
- `PluginInstalled` (`plugin.installed`) — a validated plugin entered the registry.
- `PluginEnabled` (`plugin.enabled`) — a plugin began participating.
- `PluginDisabled` (`plugin.disabled`) — a plugin stopped participating.
- `PluginUpdated` (`plugin.updated`) — a plugin moved to a strictly newer version (carries both).
- `PluginUninstalled` (`plugin.uninstalled`) — a plugin was retired (terminal).
- `PluginFailed` (`plugin.failed`) — a hook/health/sandboxed call failed, or the plugin was marked
  incompatible (carries the reason).

**Dependencies.** `Nizam\Kernel\Domain\{DomainEvent,TenantId}`,
`Nizam\Platform\Plugin\{PluginId,PluginKind}`, PHP `DateTimeImmutable`. Immutable value objects — no
I/O.

**Public interfaces.** The seven event classes. All lifecycle-recorded events (all but
`PluginDiscovered`) expose `pluginId(): PluginId` and a nullable `tenantId(): ?TenantId`.
