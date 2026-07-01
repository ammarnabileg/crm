# tests/Unit/Platform/Logging

**Purpose.** Unit tests for PSR-3 logging (`Nizam\Platform\Logging`).

**Responsibilities.**
- `LoggerTest` — JSON-line handler output, message placeholder interpolation, named channels via `LogManager`, and rejection of an invalid log level.

**Dependencies.** `phpunit/phpunit` ^11; `Nizam\Platform\Logging\*`.

**Public interfaces.** None — run with `vendor/bin/phpunit`.
