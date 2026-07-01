# Runtime — Infrastructure

## Purpose
The I/O edge of the AI Runtime. Everything here implements a Domain or Orchestration **port** with a
concrete adapter (in-memory or PDO), plus the wiring (`RuntimeServiceProvider`, `RuntimeModule`) that
installs the Runtime into a platform container. No business rules live here — only persistence,
event dispatch, and composition.

## Responsibilities
- Persist executions and their append-only event history (`Persistence/InMemory`, `Persistence/Pdo`).
- Serialize execution domain events to/from JSON so aggregates replay bit-for-bit (`Persistence/Pdo/ExecutionEventSerializer`).
- Serialize the advisory lock row with a TTL (`Persistence/Pdo/PdoExecutionLockManager`).
- Publish recorded events onto the platform PSR-14 dispatcher (`Event/DispatchingExecutionEventPublisher`).
- Provide real, seedable in-memory adapters for every Orchestration provider port (`Provider/InMemory`) as safe defaults.
- Ship the Postgres 16 migration and a runnable SQLite equivalent (`Migration`).
- Assemble the request-scoped `ExecutionEngine` (`RuntimeExecutionEngineFactory`) and bind everything (`RuntimeServiceProvider`).
- Provide fake Manager/Worker plugins for integration tests (`Testing`).

## Dependencies
- `Nizam\Kernel\*`, `Nizam\Platform\Support\*`, `Nizam\Platform\Container\*`, `Nizam\Platform\Event\*`,
  `Nizam\Platform\Exception\*`, `Nizam\Platform\Plugin\*`, PDO, PSR interfaces.
- The Runtime `Execution` and `Orchestration` layers (implements their ports).

## Public interfaces
- `RuntimeServiceProvider` — binds ports→adapters, the engine factory, and the `MasterOrchestrator`.
- `PersistenceDriver` — `InMemory` | `Pdo`, chosen by the host.
- `RuntimeExecutionEngineFactory` — the production `ExecutionEngineFactory`.
- The `..\RuntimeModule` facade is the stable install seam; the `MasterOrchestrator` is the sole runtime entry point.
