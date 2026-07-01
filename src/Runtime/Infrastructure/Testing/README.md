# Runtime — Infrastructure / Testing

## Purpose
Real implementations of the Plugin-kind contracts used only by the Runtime's integration tests and as
safe defaults. They are genuine plugins (valid manifests), not stubs — the runtime planning/decision and
execution behaviours are supplied by the `ManagerAgent` / `WorkerInvoker` seams, keeping the SDK
contracts minimal.

## Responsibilities
- `FakeManagerPlugin` — a `ManagerPlugin` publishing a `PluginKind::Manager` manifest and its managed roles.
- `FakeWorkerPlugin` — a `WorkerPlugin` publishing a `PluginKind::Worker` manifest, its capability, and a
  required permission that exercises the coordinator's permission gate.

## Dependencies
- `Nizam\Platform\Plugin\{Contract\ManagerPlugin, Contract\WorkerPlugin, PluginManifest, PluginKind,
  PluginPermission, SemanticVersion, VersionConstraint}`.

## Public interfaces
- `FakeManagerPlugin`, `FakeWorkerPlugin`.
