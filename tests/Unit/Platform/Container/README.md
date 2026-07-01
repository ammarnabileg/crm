# tests/Unit/Platform/Container

**Purpose.** Unit tests for the PSR-11 DI container (`Nizam\Platform\Container`).

**Responsibilities.**
- `ContainerTest` — `bind`/`singleton`/`instance`, constructor autowiring, `make` with parameters, `call`, and the error paths: not-found, unresolvable scalar, and circular dependency.

**Dependencies.** `phpunit/phpunit` ^11; `Nizam\Platform\Container\*`.

**Public interfaces.** None — run with `vendor/bin/phpunit`.
