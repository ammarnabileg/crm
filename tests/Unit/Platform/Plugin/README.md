# tests/Unit/Platform/Plugin

## Purpose
Unit tests for the Plugin Platform (`Nizam\Platform\Plugin\*`): the versioning, manifest, dependency,
permission, sandbox and lifecycle machinery, exercised in isolation without a database.

## Responsibilities
- `SemanticVersionTest` — strict SemVer 2.0.0 parse, precedence (incl. pre-release), build-metadata rules.
- `VersionConstraintTest` — caret / tilde / range / x-range / wildcard satisfaction and parse failures.
- `PluginManifestTest` — `fromArray`/`toArray` round-trip and self-validation failures.
- `PluginValidatorTest` — kind-vs-entry-point reflection, platform compatibility, health-check checks.
- `PluginDependencyResolverTest` — topological order, cycle detection, missing/incompatible/optional deps.
- `RegisteredPluginTest` — legal/illegal state transitions and recorded domain events.
- `PluginPermissionGateTest` — a plugin acts only within its granted permissions.
- `PluginSandboxTest` — a throwing plugin call becomes a failure `Result` plus a `PluginFailed` event.
- `PluginLifecycleManagerTest` — enable/disable/uninstall events, dependency gating, hook fault isolation.
- `PluginRegistryTest` — lookups by name/kind/enabled over the repository port.

## Dependencies
- PHPUnit 11 (attribute-based tests).
- `Nizam\Platform\Plugin\*` (the module under test) and its in-memory adapters / `Testing\ReferencePlugin`.
- Local helpers: `FixedTestClock`, `CollectingEventPublisher`, `PluginTestFactory`, and the `Fixture/`
  plugin classes.

## Public interfaces
Test classes only; no production code lives here. Helpers are `final` and used solely by these tests.
