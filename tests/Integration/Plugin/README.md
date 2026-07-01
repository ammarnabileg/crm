# tests/Integration/Plugin

## Purpose
Integration tests for the Plugin Platform's Infrastructure adapters, exercised end-to-end against real
I/O (an in-memory SQLite database and temporary files on disk).

## Responsibilities
- `PdoPluginRepositoryTest` — the PDO repository against `new PDO('sqlite::memory:')` with
  `SqliteSchema::apply()`: save/reload round-trip, lookup by id and name, `byKind`/`enabled` filtering,
  tenant-vs-global scoping, health and failure-reason columns, optimistic version, and the soft-delete
  of an uninstalled plugin (with the partial unique-name index enforced).
- `DirectoryPluginSourceTest` — the directory source scanning real `plugin.json` files written into a
  temporary base directory: discovery of valid manifests, ordering, ignoring manifest-less directories,
  empty/missing base directories, and malformed-manifest failure.

## Dependencies
- PHPUnit 11 (attribute-based tests) and the PDO SQLite driver.
- `Nizam\Platform\Plugin\Infrastructure\*` adapters, `Testing\ReferencePlugin`, and (for a non-worker
  entry point) the unit-test `Fixture\ToolOnlyPlugin`.

## Public interfaces
Test classes only; no production code lives here.
