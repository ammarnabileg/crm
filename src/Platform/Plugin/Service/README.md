# Platform\Plugin\Service

**Purpose.** The stateless domain services that carry the Plugin Platform's cross-cutting policy —
validation, dependency resolution, health checking, permission enforcement and fault isolation. Each
depends only on the plugin contracts/domain and the platform ports; none performs I/O directly (any
I/O is behind a port), so they are pure, deterministic and unit-testable.

**Responsibilities.**
- `PluginValidator` — `validate(PluginManifest, string platformVersion): Result`. Checks (by
  reflection, never by instantiation) that the entry-point class exists and implements the SDK
  contract its `PluginKind` demands, that the platform version satisfies the manifest's platform
  constraint, that a declared health-check class implements the `HealthCheck` port, and that the
  declared permissions, capabilities and config schema are well-formed. Returns a success carrying the
  manifest, or a failure with code `PLUGIN.VALIDATION_FAILED` and a reason.
- `PluginDependencyResolver` — `resolveOrder(list<PluginManifest>): list<PluginManifest>`. Produces a
  deterministic topological install order (dependencies first) via depth-first search. Throws
  `PluginDependencyException` on a required-dependency cycle, a missing required dependency, or a
  required dependency whose available version is incompatible; optional dependencies that are missing
  or unsatisfiable are skipped (but satisfiable optionals still constrain the order).
- `PluginHealthChecker` — `run(RegisteredPlugin, PluginContext): PluginHealthStatus`. Resolves the
  plugin's declared health check through the `HealthCheckResolver` port and runs it inside a
  fault-catching boundary: a throwing check becomes an `Unhealthy` status, a plugin with no declared
  check is reported `Healthy`. Records the status on the plugin and returns it.
- `PluginPermissionGate` — `authorize(RegisteredPlugin, string permissionKey, PermissionSet granted):
  void`. Enforces that a plugin acts only within its granted permissions; throws
  `PluginPermissionDeniedException` when the granted set lacks the required key. `allows()` is the
  non-throwing companion.
- `PluginSandbox` — `run(RegisteredPlugin, callable): Result`. Executes a plugin call and converts any
  thrown `Throwable` into a failure `Result` (code `PLUGIN.EXECUTION_FAILED`) plus a `PluginFailed`
  domain event, so a plugin crash is contained and observable without propagating into the Core. No
  `eval`, no process spawn — software fault isolation only.

**Dependencies.** `Nizam\Platform\Plugin\{PluginManifest,PluginKind,PluginPermission,PermissionSet,
PluginContext,PluginHealthStatus,SemanticVersion,RegisteredPlugin}`; the ports
`Nizam\Platform\Plugin\Port\{HealthCheck,HealthCheckResolver,PluginEventPublisher}`; the plugin
exceptions; `Nizam\Kernel\Domain\Clock`; `Nizam\Platform\Support\Result`; PHP `Reflection*`. No direct
I/O.

**Public interfaces.** `PluginValidator::validate()`, `PluginDependencyResolver::resolveOrder()`,
`PluginHealthChecker::run()`, `PluginPermissionGate::authorize()`/`allows()`, `PluginSandbox::run()`.
