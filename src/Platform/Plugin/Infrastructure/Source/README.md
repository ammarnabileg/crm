# Plugin\Infrastructure\Source

**Purpose.** `PluginSource` adapters — the ways the platform discovers available plugins.

**Responsibilities.**
- `DirectoryPluginSource` — scans the immediate sub-directories of a base directory for a `plugin.json`
  manifest file, decodes and validates each into a `PluginManifest`, and yields a `DiscoveredPlugin`
  whose locator is the containing directory path. Read-only: it never loads or executes plugin code,
  only the declarative manifest. A missing base directory yields nothing; a malformed manifest is
  surfaced as a manifest exception.
- `ArrayPluginSource` — exposes an explicit, in-memory set of manifests as discovered plugins, with a
  synthesised `array:<name>@<version>` locator when none is given. Used by tests and programmatic
  registration. Performs no I/O.

**Dependencies.** `Nizam\Platform\Plugin\{Port\PluginSource,Port\DiscoveredPlugin,PluginManifest}`,
`Nizam\Platform\Support\Json`, `Nizam\Platform\Exception`, `Nizam\Platform\Plugin\Exception\PluginManifestException`, PHP.

**Public interfaces.** `DirectoryPluginSource implements PluginSource`;
`ArrayPluginSource implements PluginSource` (+ `fromManifests()`, `add()`).
