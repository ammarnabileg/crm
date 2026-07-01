# Plugin Platform — Implementation Audit

**Module:** Plugin Platform (`Nizam\Platform\Plugin\*`, `src/Platform/Plugin/`)
**Date:** 2026-07-01
**Governing decisions:** ADR-0016 (everything is a plugin), ADR-0020 (plugin platform
mechanics). Doc: `docs/25-Plugin-Platform.md`.
**Verified on:** PHP 8.4.19, PHPUnit 11.5.55 — full suite re-run green under
`failOnWarning`/`failOnRisky` (347 tests / 967 assertions; 178 of them Plugin), all 70
`src/Platform/Plugin/` files `php -l` clean, `composer dump-autoload -o` clean.

---

## 1. Completed work

Delivered the plugin extensibility substrate — the SDK contracts plus the loader/registry/
discovery/validation/versioning/dependency/permission/lifecycle/health/isolation machinery
— as a pure Hexagonal/DDD module. Concrete business plugins remain out of scope; a single
real, test-only `ReferencePlugin` exercises the substrate end-to-end.

**Domain / contracts** — `PluginKind`, `PluginState`, `SemanticVersion` (strict SemVer
2.0.0), `VersionConstraint`/`VersionBound`, `PluginId`, `PluginPermission`,
`PermissionSet`, `PluginDependency`, `PluginManifest` (self-validating, `fromArray`/
`toArray` round-trip), `PluginInterface`, `PluginLifecycleHooks`, `PluginContext`,
`RegisteredPlugin` (guarded state machine + events), `PluginHealthStatus`/`HealthLevel`;
the nine SDK kind contracts under `Contract/`; seven domain events under `Event/`; eight
typed exceptions (codes `PLUGIN.*`) under `Exception/`; seven ports under `Port/`.

**Services** — `PluginValidator` (fields, SemVer, kind↔entry-point contract by reflection,
platform compatibility, permissions/capabilities, config-schema shape),
`PluginDependencyResolver` (topological order, cycle detection, missing/incompatible
required-dep detection, optional skipping), `PluginHealthChecker`, `PluginPermissionGate`,
`PluginSandbox` (fault-catching, no eval/process-spawn).

**Application** — `PluginRegistry`, `PluginDiscoveryService`, `PluginInstaller` (+
`UpdateOutcome` for rollback), `PluginLifecycleManager`, `PluginManager` façade
(tenant-aware).

**Infrastructure** — in-memory + PDO (SQLite/PostgreSQL) repositories with a hydrator;
`DirectoryPluginSource` + `ArrayPluginSource`; `DispatchingPluginEventPublisher`;
container-driven instantiator + health-check resolver; PostgreSQL migration +
`SqliteSchema`; `PluginServiceProvider`; `PersistenceDriver`; test-only `ReferencePlugin`.

**Counts:** 70 PHP source files under `src/Platform/Plugin/`, 19 test files; every folder
(source and test) carries a `README.md`.

---

## 2. Architecture validation

- **Open/Closed (ADR-0016).** The Core depends only on SDK contracts; capabilities install,
  enable, disable, update, replace and uninstall at runtime with no core edit. ✔
- **Isolation.** Two enforced layers: `PluginPermissionGate` (a plugin acts only within its
  granted `PermissionSet`) and `PluginSandbox` (any `Throwable` from a plugin call or
  lifecycle hook is caught → failure `Result` + `Failed` state + `PluginFailed` event). No
  `eval`, no process spawning. ✔ (in-process only — see §4)
- **Versioning.** Strict SemVer 2.0.0 + constraint satisfaction drive platform-compatibility
  and the strictly-newer-update rule; updates keep the prior version for rollback. ✔
- **Tenant-aware.** Install/update accept an optional owning `TenantId` (`null` = global);
  the registry/repository scope reads accordingly; `tenant_id` is nullable in the schema. ✔
- **Hexagonal/DDD (ADR-0007).** Contracts depend only on Kernel + PHP; all I/O lives in
  `Infrastructure/` behind ports. Domain events recorded and published via a port (ADR-0004).
  UUIDv7 PKs (ADR-0003); soft-delete + audit columns + optimistic version (ADR-0002/0011). ✔
- **Framework-independent (ADR-0015).** No framework; native PHP 8.4, `declare(strict_types=1)`,
  PHPDoc, typed everything, `final` where possible. ✔

---

## 3. Test results (honest, from a real run)

`vendor/bin/phpunit` with `failOnWarning=true` and `failOnRisky=true`
(`phpunit.xml.dist`):

| Scope | Tests | Assertions | Result |
|---|---|---|---|
| Plugin — Unit (`tests/Unit/Platform/Plugin`) | 160 | 292 | OK |
| Plugin — Integration (`tests/Integration/Plugin`) | 18 | 47 | OK |
| **Plugin total** | **178** | **339** | **OK** |
| **Full suite (all modules)** | **347** | **967** | **OK** |

No failures, errors, warnings, risky or skipped tests. Every plugin source and test file
passes `php -l`. `composer dump-autoload -o` is clean.

Coverage of the spec's required tests is present: `SemanticVersionTest`,
`VersionConstraintTest`, `PluginManifestTest`, `PluginValidatorTest`,
`PluginDependencyResolverTest`, `RegisteredPluginTest`, `PluginPermissionGateTest`,
`PluginSandboxTest`, `PluginLifecycleManagerTest`, `PluginRegistryTest` (Unit);
`PdoPluginRepositoryTest` and `DirectoryPluginSourceTest` (Integration), all referencing
the `ReferencePlugin`.

---

## 4. Remaining risks & tech debt

- **In-process isolation only (deferred).** Current isolation is a permission gate + a
  fault-catching sandbox. **True OS-process / container isolation is deferred** (ADR-0020;
  ADR-0016's microkernel-with-services path remains open). A fully untrusted plugin could
  still consume CPU/memory in-process. Acceptable for the trusted-but-scoped plugins loaded
  today; revisit before admitting untrusted third-party plugins.
- **PDO validated on SQLite in tests.** The PDO repository targets both SQLite and
  PostgreSQL and the PostgreSQL migration is the design source of record, but integration
  tests run against in-memory SQLite (`SqliteSchema`). PostgreSQL should be exercised in CI
  when a server is available.
- **Config-schema validation is shape-level.** `configSchema` is validated for shape, not as
  a full JSON-Schema validator; deep config validation can be added when config-driven
  plugins arrive.
- **SDK contract stability.** The whole model depends on keeping the SDK kind contracts
  stable (ADR-0016). Contract changes are breaking for installed plugins and must be
  versioned deliberately.

---

## 5. Recommendations

1. Add a PostgreSQL integration lane in CI to run `PdoPluginRepositoryTest` against a real
   server, keeping `001_create_plugin_tables.sql` and `SqliteSchema` in verified lockstep.
2. When untrusted third-party plugins become a requirement, implement OS-process/container
   isolation behind the existing sandbox seam (no contract change expected).
3. Wire the `PluginManager` façade into the HTTP/Interface layer when it lands, exposing the
   "Marketplace / Plugins" screen (`docs/25-Plugin-Platform.md` §10).
4. Introduce full config-schema validation once config-driven plugin kinds exist.

---

## 6. Verdict

The Plugin Platform is **delivered and test-green**: it compiles, lints clean, the full
suite is green under strict flags, every folder is documented, and the module doc + ADR-0020
+ this audit are updated in the same change (Constitution §10). Isolation is in-process by
design decision (ADR-0020), with OS-level isolation explicitly deferred.
</content>
