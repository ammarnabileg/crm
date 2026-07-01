# tests/Unit/Kernel/Domain

**Purpose.** Unit tests for the DDD domain building blocks (`Nizam\Kernel\Domain`).

**Responsibilities.**
- `IdentifierTest` — UUID v7 identifier generation, `fromString` validation, equality, and aggregate domain-event recording/pulling.

**Dependencies.** `phpunit/phpunit` ^11; `Nizam\Kernel\Domain\*` (and `Nizam\Platform\Support\Uuid`).

**Public interfaces.** None — run with `vendor/bin/phpunit`.
