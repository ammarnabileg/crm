# tests/Unit/Runtime/Execution

## Purpose
Unit tests for the Runtime execution engine's domain and application layers — the state machine, the
event-sourced `Execution` aggregate, the worker result contract, and the retry/timeout/recovery/replay
services. These tests run entirely in-process against in-memory adapters and a fake clock; they touch no
database. Integration coverage (PDO repository, event store, concurrency, end-to-end) lives under
`tests/Integration/Runtime`.

## Responsibilities
- `ExecutionStateMachineTest` — every legal ADR-0018 transition is permitted, representative illegal ones
  throw `IllegalExecutionTransition`, and the two terminal states have no exits.
- `ExecutionTest` — the start→plan→assign→run→review→approve→complete happy path emits the correct ordered
  events; fail→recover; cancel; retry respects the `RetryPolicy` and throws `RetryExhausted` at the limit;
  `replay(events)` reconstitutes identical state.
- `RetryEngineTest` — fixed and exponential backoff math, budget enforcement, bounded jitter via an
  injected deterministic randomizer.
- `TimeoutManagerTest` — wall-clock and per-step budgets enforced against a fake, advanceable clock,
  raising `ExecutionTimedOut`.
- `WorkerResultTest` — every spec-mandated field, the `[0, 1]` confidence bound, non-negative time/cost,
  and structural equality/serialization.
- `RecoveryServiceTest` — rebuild a failed execution from the event store, mark it Recovered, persist and
  publish; reject non-failed and empty streams.
- `ReplayServiceTest` — read-only rebuild of state and event names from the stream, with no side effects.

## Dependencies
- `Nizam\Runtime\Execution\*` (domain + application under test).
- `Nizam\Runtime\Infrastructure\Persistence\InMemory\*` (event store + repository doubles that are real,
  single-process adapters).
- `Nizam\Kernel\Domain\*`, `Nizam\Platform\Support\*`, `Nizam\Platform\Exception\*`.
- PHPUnit 11 (attribute-based tests and data providers).

## Public interfaces
- `MutableTestClock` — a controllable `Nizam\Kernel\Domain\Clock` shared by these tests; advanceable by
  whole seconds (`advance`) or milliseconds (`advanceMs`) for deterministic timestamps and timeouts.
- `CollectingRecoveryPublisher` (in `RecoveryServiceTest`) — an `ExecutionEventPublisher` double that
  records the events recovery published.
