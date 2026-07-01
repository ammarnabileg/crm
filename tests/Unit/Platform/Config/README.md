# tests/Unit/Platform/Config

**Purpose.** Unit tests for configuration and environment access (`Nizam\Platform\Config`).

**Responsibilities.**
- `ConfigTest` — dot-access `get`/`has`/`set`/`all` with defaults on the repository.
- `EnvTest` — `get`/`bool`/`int`/`required` with "true"/"false"/"null" casting and the missing-required error.

**Dependencies.** `phpunit/phpunit` ^11; `Nizam\Platform\Config\Config` and `Env`.

**Public interfaces.** None — run with `vendor/bin/phpunit`.
