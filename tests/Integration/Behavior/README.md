# tests/Integration/Behavior

## Purpose
Integration tests for the **Behavior** module's PDO persistence adapters, run against an in-memory
SQLite database built by `Nizam\Behavior\Infrastructure\Migration\SqliteSchema`.

## Responsibilities
- `PdoBehaviorRepositoryTest`:
  - Applies the SQLite schema to a fresh `PDO('sqlite::memory:')` connection.
  - Round-trips a `BehaviorProfile` (including its append-only revision history) through
    `PdoBehaviorProfileRepository` and `BehaviorMapper` with full fidelity.
  - Verifies in-place upsert (no row duplication) on re-save.
  - Proves tenant isolation for both profiles and proposals — a second tenant cannot read the
    first's aggregate even with the same id.
  - Round-trips a `BehaviorChangeProposal`, then `approve()`s it and asserts the decided status is
    persisted and it drops out of the pending listing.
  - Drives the full approval-gated flow (propose → approve → apply) and asserts the applied new
    profile version is persisted.

## Dependencies
- `phpunit/phpunit` ^11.
- `ext-pdo` / `pdo_sqlite`.
- `Nizam\Behavior\Infrastructure\Persistence\Pdo\*`, `BehaviorMapper`, `SqliteSchema`, and the
  Behavior domain aggregates.

## Public interfaces
Test class only; no production code.
