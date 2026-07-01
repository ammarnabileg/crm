# tests/Unit/Platform/Plugin/Fixture

## Purpose
Small, real fixture classes used by the Plugin Platform unit tests to exercise reflection, lifecycle
hooks and instantiation against genuine implementations (not mocks of the platform).

## Responsibilities
- `ToolOnlyPlugin` — a plugin implementing only the `ToolPlugin` contract, used to prove the validator
  rejects a manifest whose declared kind does not match its entry point.
- `EchoHealthCheck` — a concrete `HealthCheck` implementation, used to validate `healthCheckClass` wiring.
- `HookedWorkerPlugin` — a `WorkerPlugin` implementing `PluginLifecycleHooks`, recording hook calls and
  optionally throwing on a chosen hook, to drive the lifecycle manager's happy and fault paths.
- `MapPluginInstantiator` — a `PluginInstantiator` test double returning pre-built instances by name.

## Dependencies
- `Nizam\Platform\Plugin\*` contracts, ports and value objects.

## Public interfaces
Fixture classes implementing the platform's SDK contracts / ports; consumed only by the unit tests.
