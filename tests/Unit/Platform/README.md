# tests/Unit/Platform

**Purpose.** Unit tests for the self-built platform infrastructure (`Nizam\Platform\*`), mirroring `src/Platform/`.

**Responsibilities.**
- `Support/` — `Result` and `Uuid` v7.
- `Container/` — bind/singleton/instance/autowire/call and error paths.
- `Config/` — dot-access config and environment casting.
- `Event/` — PSR-14 dispatch (priority, order, stoppable, subtype matching).
- `Logging/` — PSR-3 JSON-line output, interpolation, channels.
- `Bootstrap/` — the composition root (core singletons, boot idempotency, environment parsing).

**Dependencies.** `phpunit/phpunit` ^11; the code under test in `Nizam\Platform\*`.

**Public interfaces.** None — run with `vendor/bin/phpunit`.
