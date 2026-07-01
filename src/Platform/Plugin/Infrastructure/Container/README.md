# Plugin\Infrastructure\Container

**Purpose.** Container-backed adapters that resolve plugin-declared class names into live instances,
isolating construction faults behind their ports.

**Responsibilities.**
- `ContainerPluginInstantiator` — implements `PluginInstantiator`: resolves a manifest's
  `entryPointClass` via the platform `Container` (autowiring its dependencies), verifies the result is
  a `PluginInterface`, and converts any construction `Throwable` into a `PluginValidationException`
  naming the plugin — so a broken plugin cannot crash the Core.
- `ContainerHealthCheckResolver` — implements `HealthCheckResolver`: resolves a manifest's
  `healthCheckClass` via the container, returning null when none is declared and raising a
  `PluginValidationException` when the class cannot be constructed or is not a `HealthCheck`.

**Dependencies.** `Nizam\Platform\Container\Container`,
`Nizam\Platform\Plugin\{Port\PluginInstantiator,Port\HealthCheck,Port\HealthCheckResolver,PluginInterface,PluginManifest}`,
`Nizam\Platform\Plugin\Exception\PluginValidationException`, PHP.

**Public interfaces.** `ContainerPluginInstantiator implements PluginInstantiator`;
`ContainerHealthCheckResolver implements HealthCheckResolver`.
