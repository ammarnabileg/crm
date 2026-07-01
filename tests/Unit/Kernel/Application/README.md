# tests/Unit/Kernel/Application

**Purpose.** Unit tests for the application-layer CQRS buses (`Nizam\Kernel\Application`).

**Responsibilities.**
- `SimpleCommandBusTest` — registration, one-to-one message→handler dispatch, and the errors on duplicate registration and unregistered dispatch.

**Dependencies.** `phpunit/phpunit` ^11; `Nizam\Kernel\Application\SimpleCommandBus`.

**Public interfaces.** None — run with `vendor/bin/phpunit`.
