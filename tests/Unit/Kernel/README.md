# tests/Unit/Kernel

**Purpose.** Unit tests for the shared DDD kernel (`Nizam\Kernel\*`), mirroring `src/Kernel/`.

**Responsibilities.**
- `Domain/` — identity, equality, validation, and aggregate domain-event recording.
- `Application/` — the in-memory command/query buses.
- `Tenancy/` — the tenant context carrier.

**Dependencies.** `phpunit/phpunit` ^11; the code under test in `Nizam\Kernel\*`.

**Public interfaces.** None — run with `vendor/bin/phpunit`.
