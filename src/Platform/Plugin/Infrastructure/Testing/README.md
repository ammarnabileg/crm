# Plugin\Infrastructure\Testing

**Purpose.** A single, real, minimal plugin used **only** by the test suite to exercise the platform
end-to-end. It is a genuine plugin, not a stub of the platform.

**Responsibilities.**
- `ReferencePlugin` — a real `WorkerPlugin` that publishes a valid `PluginManifest` (kind `worker`,
  entry point pointing at itself, one declared permission, a small config schema) and fulfils the
  worker contract via `describe()`. Tests use it to drive discovery, validation (its entry point truly
  implements the declared kind's contract), container instantiation, and the install → enable →
  disable → uninstall lifecycle. `manifestDescriptor()` returns the manifest without constructing the
  plugin, for array sources and `plugin.json` fixtures.

**Dependencies.** `Nizam\Platform\Plugin\{Contract\WorkerPlugin,PluginManifest,PluginKind,PluginPermission,SemanticVersion,VersionConstraint}`, PHP.

**Public interfaces.** `ReferencePlugin implements WorkerPlugin`; `ReferencePlugin::NAME`;
`ReferencePlugin::manifestDescriptor(): PluginManifest`.
