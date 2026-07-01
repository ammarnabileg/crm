# Platform\Plugin\Exception

**Purpose.** The exception hierarchy for the Plugin Platform. Every exception extends the base
`PluginException` (itself an SPL `RuntimeException`, keeping the domain coupled only to PHP + Kernel)
and carries a stable, dotted error code under the `PLUGIN.*` namespace so callers, logs and API
responses can branch on the failure kind without matching message text. These model *unexpected /
guard* failures; expected business outcomes are modelled with `Nizam\Platform\Support\Result` in the
service layer.

**Responsibilities.** Provide typed failures with codes:
- `PluginException` — base (`PLUGIN` prefix; `errorCode()`).
- `PluginManifestException` — `PLUGIN.MANIFEST_INVALID` (missing/invalid field, bad name/kind/
  permission-key/dependency-name).
- `PluginValidationException` — `PLUGIN.VALIDATION_FAILED` (bad SemVer, bad constraint, entry-point
  does not implement the kind contract).
- `PluginDependencyException` — `PLUGIN.DEPENDENCY_UNRESOLVED` (cycle, missing/incompatible required
  dependency).
- `PluginNotFoundException` — `PLUGIN.NOT_FOUND` (by name or id).
- `PluginStateException` — `PLUGIN.INVALID_STATE_TRANSITION` (illegal lifecycle transition).
- `PluginPermissionDeniedException` — `PLUGIN.PERMISSION_DENIED` (ungranted permission).
- `IncompatiblePluginException` — `PLUGIN.INCOMPATIBLE` (platform constraint unsatisfied).

**Dependencies.** PHP SPL (`RuntimeException`), `Nizam\Platform\Plugin\PluginState` (for the state
exception's message). No I/O.

**Public interfaces.** The exception classes above and their named static factory constructors; each
concrete exception exposes a `CODE` constant and inherits `errorCode()`.
