# Platform\Plugin

**Purpose.** The Plugin Platform — the core extensibility substrate of the Nizam platform, where
*everything is a plugin* (ADR-0016, ADR-0020). It lets companies install, enable, disable, upgrade and
replace agents and capabilities as versioned plugins **without modifying the Core**. This directory
holds the plugin SDK **contracts** and the plugin **domain** (enums, value objects, the registered-plugin
aggregate, events, exceptions and hexagonal ports). Services, application orchestration and
Infrastructure adapters live in sibling namespaces built on top of these contracts.

**Responsibilities (this layer).**
- **Kinds & states.** `PluginKind` (the nine capability kinds; each maps to a `Contract/` interface)
  and `PluginState` (the lifecycle states with capability predicates).
- **Versioning.** `SemanticVersion` (strict SemVer 2.0.0 parse/precedence) and `VersionConstraint`
  (`^`, `~`, x-ranges, `*`, comparator conjunctions) with the `VersionBound` helper.
- **Identity & manifest.** `PluginId` (a Kernel `Identifier`); `PluginManifest` — the immutable,
  self-validating `plugin.json` descriptor with `fromArray()`/`toArray()`; the value objects it
  composes: `PluginPermission`, `PermissionSet`, `PluginDependency`.
- **SDK contracts.** `PluginInterface` (base), `PluginLifecycleHooks` (optional), and the nine kind
  contracts under `Contract/`.
- **Runtime.** `PluginContext` (tenant + granted permissions + config + logger handed to hooks/checks)
  and the health value objects `HealthLevel` / `PluginHealthStatus`.
- **Aggregate.** `RegisteredPlugin` — guarded lifecycle state machine that records domain events.
- **Events / Exceptions / Ports.** See the `Event/`, `Exception/` and `Port/` READMEs.

**Dependencies.** PHP 8.4, `Nizam\Kernel\Domain\*` (`Identifier`, `ValueObject`, `Entity`,
`AggregateRoot`, `DomainEvent`, `Clock`, `TenantId`), `Nizam\Platform\Support\{Assert}` and PSR-3
(`Psr\Log`) for the context logger. No framework, no I/O — this layer is pure domain + contracts.

**Public interfaces.** `PluginInterface` and the nine kind contracts (the plugin SDK);
`PluginManifest` (the published descriptor); `RegisteredPlugin` (the lifecycle aggregate); the
`Port\*` interfaces that Infrastructure implements. Isolation is enforced through the permission model
(`PluginPermission` / `PermissionSet` / `PluginContext`) plus the state guards on `RegisteredPlugin`.
