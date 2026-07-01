# Platform\Plugin\Contract

**Purpose.** The plugin SDK: the nine kind-specific contracts a concrete plugin implements. Each
extends the base `Nizam\Platform\Plugin\PluginInterface` and adds the minimal, real, typed method(s)
that its kind must expose so the platform can treat the plugin polymorphically without knowing its
concrete type. `PluginKind::contract()` maps each kind to exactly one of these interfaces, and the
validator checks (by reflection) that a manifest's `entryPointClass` implements the interface its
declared kind requires.

**Responsibilities.** Declare the kind contracts:
- `ManagerPlugin` — `managedRoles(): list<string>`.
- `TeamLeaderPlugin` — `coordinatedCapabilities(): list<string>`.
- `WorkerPlugin` — `describe(): array<string, mixed>`.
- `ToolPlugin` — `toolName(): string`, `inputSchema(): array<string, mixed>`.
- `IntegrationPlugin` — `externalSystem(): string`.
- `AutomationPlugin` — `triggers(): list<string>`.
- `DepartmentPlugin` — `providedRoles(): list<string>`.
- `ProviderPlugin` — `providedCapability(): string`.
- `KnowledgeSourcePlugin` — `knowledgeDomains(): list<string>`.

**Dependencies.** `Nizam\Platform\Plugin\PluginInterface` (and, transitively, `PluginManifest`).
Interfaces only — no I/O, no framework.

**Public interfaces.** The nine interfaces above. These are the stable surface third-party plugin
authors code against.
