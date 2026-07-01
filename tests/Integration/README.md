# tests/Integration

## Purpose
Integration tests that exercise Nizam adapters against real I/O boundaries — chiefly PDO
repositories running on an in-memory SQLite connection — to prove that persistence, hydration, and
tenant scoping behave correctly end to end.

## Responsibilities
- Host the `Integration` PHPUnit test suite (declared in `phpunit.xml.dist`).
- Stand up real infrastructure per test (e.g. `new PDO('sqlite::memory:')` + `SqliteSchema::apply`)
  and assert round-trip fidelity and cross-tenant isolation.

## Dependencies
- `phpunit/phpunit` ^11.
- `ext-pdo` / `pdo_sqlite`.
- The Infrastructure adapters and migrations under test.

## Public interfaces
Test classes only. Subdirectories mirror the bounded context under test (e.g. `Behavior/`).
