# Platform\Config

**Purpose.** Configuration access for the platform: a dot-access repository over a nested array, and a typed environment-variable reader used to seed it at bootstrap.

**Responsibilities.**
- `Config` — `get`/`has`/`set`/`all` with dotted keys (e.g. `logging.channels.app.level`).
- `Env` — `get`/`bool`/`int`/`string`/`required`; casts `true`/`false`/`null`, strips quotes; reads `$_ENV`, `$_SERVER`, `getenv`.

**Dependencies.** `Nizam\Platform\Exception` (`ConfigException`).

**Public interfaces.** `Config`, `Env`.
