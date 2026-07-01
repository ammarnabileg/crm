# tests/Unit/Platform/Bootstrap

**Purpose.** Unit tests for the composition root (`Nizam\Platform\Bootstrap`).

**Responsibilities.**
- `ApplicationTest` — building the container, installing `CoreServiceProvider`, resolving the core singletons, `boot()` idempotency, and `Environment` parsing.

**Dependencies.** `phpunit/phpunit` ^11; `Nizam\Platform\Bootstrap\*`.

**Public interfaces.** None — run with `vendor/bin/phpunit`.
