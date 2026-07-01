# tests/Unit/Platform/Support

**Purpose.** Unit tests for the pure support utilities (`Nizam\Platform\Support`).

**Responsibilities.**
- `ResultTest` — ok/err construction, `isOk`/`isErr`, `value` throwing on error, `map`/`mapErr`, and `unwrapOr`.
- `UuidTest` — UUID v7 version/variant bits, uniqueness, time-ordering, and `isValid`/`isV7` validation.

**Dependencies.** `phpunit/phpunit` ^11; `Nizam\Platform\Support\Result` and `Uuid`.

**Public interfaces.** None — run with `vendor/bin/phpunit`.
