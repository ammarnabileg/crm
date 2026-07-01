# tests — Nizam test root

**Purpose.** The PSR-4 test root (`Nizam\Tests\` → `tests/`) for the platform. Holds the automated tests that enforce the Constitution's "every test passes" Definition of Done (§10).

**Responsibilities.**
- `Unit/` — fast, isolated unit tests for the Wave-1 foundation spine, mirroring the `src/` tree under `Nizam\Tests\Unit\...`; runs as the PHPUnit "Unit" test suite.

Higher-level suites (integration, functional) will be added as later phases deliver the infrastructure they exercise.

**Dependencies.** `phpunit/phpunit` ^11 (attribute-based); the `Nizam\` autoloader; configuration in `phpunit.xml.dist`.

**Public interfaces.** None — run with `vendor/bin/phpunit`. Current status: green, 76 tests / 151 assertions on PHP 8.4.19 + PHPUnit 11.5.55.
