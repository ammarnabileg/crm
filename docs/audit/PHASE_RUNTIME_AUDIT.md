# AI Runtime + Master Orchestrator — Implementation Audit

> **Status: Delivered & test-green | Version: 1.0.0 | Date: 2026-07-01 | Owner: Architecture (Nizam Core)**
>
> Realizes **ADR-0017** (single Master Orchestrator) and **ADR-0018** (AI Runtime execution
> state machine); persistence & event-sourcing realized by **ADR-0021**. This audit records the
> `Nizam\Runtime\*` increment (`src/Runtime/`) as a real, compiling, test-green, documented slice
> per Constitution §10, with HONEST numbers taken from an actual run on 2026-07-01.

---

## 1. Scope

The AI Runtime is the **only path that executes work**. A request enters the Master Orchestrator,
which validates it, resolves tenant/user/permissions/department, loads the Manager plugin,
dispatches Worker plugins through the runtime (never worker-to-worker), coordinates them, merges
and scores their results, and returns the Manager's decision. Every execution is
state-machine-driven, event-sourced, auditable, cost/perf-tracked, retryable, recoverable, and
replayable.

---

## 2. Completed work (inventory)

### 2.1 Execution Domain — `src/Runtime/Execution/Domain/`
- **Identifiers** (extend Kernel `Identifier`, UUID v7): `ExecutionId`, `ExecutionStepId`, `SessionId`.
- **`ExecutionState`** — backed enum with EXACTLY the 13 spec states: `Pending`, `Planning`,
  `Assigned`, `Waiting`, `Running`, `Retrying`, `Review`, `Approved`, `Rejected`, `Cancelled`,
  `Completed`, `Failed`, `Recovered`; plus `isTerminal()` (`Completed`, `Cancelled`).
- **`ExecutionStateMachine`** — `allowedTransitions()`, `canTransition(from,to)`, `assert(from,to)`
  throwing `IllegalExecutionTransition`. The production-sane legal graph from the spec.
- **`ExecutionStep`** entity (`ExecutionStepState`), **`ExecutionTimeline`** / **`TimelineEntry`** VOs,
  **`ExecutionMetadata`** VO, **`CostSnapshot`** / **`PerformanceSnapshot`** VOs,
  **`RetryPolicy`** (`BackoffStrategy` Fixed/Exponential + jitter; `nextDelayMs`, `shouldRetry`),
  **`TimeoutPolicy`**, **`ExecutionLock`** VO.
- **`WorkerResult`** VO with ALL mandated fields (taskResult, evidence[], reasoningSummary,
  confidence 0..1 validated, executionTimeMs, executionCostMicros, resourcesUsed, automationSelected,
  toolsUsed, warnings, errors, recommendations, logs) + **`EvidenceItem`** VO.
- **`Execution`** aggregate (event-sourced): `start / plan / assign / beginStep / completeStep /
  run / retry / toReview / approve / reject / complete / fail / recover / cancel` — every mutator
  guarded by `ExecutionStateMachine::assert()` and emits the matching event; `replay(events): self`
  reconstitutes identical state; `RetryPolicy` enforced with `RetryExhausted` at the limit.
- **Event/** — 13 domain events implementing Kernel `DomainEvent` (ExecutionStarted, ExecutionPlanned,
  WorkTaskAssigned, ExecutionStepStarted, ExecutionStepCompleted, ExecutionRetried,
  ExecutionSentToReview, ExecutionApproved, ExecutionRejected, ExecutionCompleted, ExecutionFailed,
  ExecutionRecovered, ExecutionCancelled).
- **Exception/** — `EXEC.*` hierarchy: `ExecutionException` (base), `IllegalExecutionTransition`,
  `RetryExhausted`, `ExecutionTimedOut`, `ExecutionLockException`, `UnknownStepException`.
- **Port/** — `ExecutionRepository`, `ExecutionEventStore` (append/stream), `ExecutionLockManager`,
  `ExecutionEventPublisher`.

### 2.2 Execution Application — `src/Runtime/Execution/Application/`
- **`ExecutionPipeline`** + `Stage/` (`PipelineStage` interface; `Validate`, `Plan`, `Assign`, `Run`,
  `Review`, `Finalize` stages) honoring the state machine, short-circuiting to `Failed`/`Recovered`.
- **`ExecutionEngine`** facade — `execute(ExecutionRequest): ExecutionResult` within an
  `ExecutionLockManager` lock, applying `RetryEngine` + `TimeoutManager`, persisting via repository +
  event store, publishing events; **idempotent by `ExecutionId`** (terminal-state short-circuit,
  re-checked inside the lock).
- **`RetryEngine`** (backoff/`shouldRetry`), **`TimeoutManager`** (monotonic via injected `Clock`,
  throws `ExecutionTimedOut`), **`RecoveryService`** (rebuild from event store → `Recovered`),
  **`ReplayService`** (read-only rebuild → `ExecutionReplayView`), **`ExecutionValidator`** (→ `Result`),
  **`CostTracker`** / **`PerformanceTracker`**.
- **VO/** `ExecutionRequest`, `ExecutionResult`, `RetryDecision`; **Dto/** `ExecutionView`,
  `ExecutionStepView`, `ExecutionTimelineView`, `ExecutionReplayView`, `ExecutionMetricsView`.
- **Port/** application seams `ExecutionPlanner`, `WorkerDispatcher` (defaults provided).

### 2.3 Orchestration — `src/Runtime/Orchestration/`
- **`MasterOrchestrator`** — the SOLE entry point; `handle(OrchestrationRequest): OrchestrationResponse`
  runs validate → resolve tenant/user/permissions → resolve department + Manager plugin → build
  `ExecutionRequest` → run `ExecutionEngine` → coordinate Workers via `WorkerCoordinator` → collect
  `WorkerResult[]` → `ResultMerger` → `ConfidenceEvaluator` → `ManagerDecision`.
- **`WorkerCoordinator`** — `Sequential`, `Parallel` (deterministic in-process fan-out; no real
  threads), and `Collaborative` (bounded re-entry — a worker requests more workers by RE-ENTERING the
  coordinator, never invoking another worker directly).
- **`ResultMerger`** (dedupe evidence, combine recommendations, aggregate cost/perf),
  **`ConfidenceEvaluator`** (score + rationale + needsRetry/needsMoreWorkers).
- **VO/** `OrchestrationRequest`, `OrchestrationResponse`, `ManagerDecision` (`DecisionOutcome`),
  `ExecutionContext`, `MergedResult`, `ConfidenceAssessment`, `WorkTask`, `WorkerAssignment`,
  `DispatchMode`, `ResolvedTenant`, `ResolvedUser`, `DepartmentAssignment`.
- **Port/** `TenantProvider`, `UserProvider`, `PermissionProvider`, `DepartmentResolver`,
  `ManagerPluginResolver`, `WorkerPluginResolver`, `AutomationSelector`, `ManagerAgent`,
  `WorkerInvoker`, `ExecutionEngineFactory`.

### 2.4 Infrastructure — `src/Runtime/Infrastructure/`
- **Persistence/InMemory/** — `InMemoryExecutionRepository`, `InMemoryExecutionEventStore`,
  `InMemoryExecutionLockManager` (real, single-process).
- **Persistence/Pdo/** — `PdoExecutionRepository`, `PdoExecutionEventStore` (append-only),
  `PdoExecutionLockManager` (advisory-row lock with TTL + steal-on-expiry), plus
  `ExecutionRowMapper`, `ExecutionEventSerializer`, `ExecutionProjectionWriter`. **Tenant-scoped**
  (every query filters `tenant_id` and `deleted_at IS NULL`); **soft-delete** for executions;
  sqlite + pgsql.
- **Event/`DispatchingExecutionEventPublisher`** → `Nizam\Platform\Event\EventDispatcher`.
- **Provider/InMemory/** — REAL seedable adapters for all Orchestration ports (Tenant/User/Permission
  providers, Department/Manager/Worker resolvers, `AutomationSelector`) usable as safe defaults.
- **Migration/** — `001_create_runtime_tables.sql` (Postgres 16: executions, execution_history,
  execution_timeline, execution_logs, manager_decisions, worker_results, execution_metrics, evidence;
  UUIDv7 PK, tenant_id, audit cols, deleted_at, version, JSONB, indexes) + `SqliteSchema::apply(PDO)`.
- **`RuntimeServiceProvider`** (binds ports→adapters, registers `ExecutionEngine` +
  `MasterOrchestrator` + services), **`RuntimeModule`** facade, `RuntimeExecutionEngineFactory`,
  `PersistenceDriver`.
- **Testing/** — `FakeManagerPlugin`, `FakeWorkerPlugin` (REAL implementations of the
  `Nizam\Platform\Plugin\Contract\*` kind contracts, used only by tests).

### 2.5 Tests — `tests/Unit/Runtime/`, `tests/Integration/Runtime/`
- **Unit** — `ExecutionStateMachineTest`, `ExecutionTest`, `RetryEngineTest`, `TimeoutManagerTest`,
  `WorkerResultTest`, `ResultMergerTest`, `ConfidenceEvaluatorTest`, `MasterOrchestratorTest`,
  `WorkerCoordinatorTest`, `RecoveryServiceTest`, `ReplayServiceTest`,
  `InMemoryExecutionPersistenceTest` (+ `MutableTestClock` helper).
- **Integration** — `PdoExecutionRepositoryTest`, `PdoExecutionEventStoreTest`, `ConcurrencyTest`,
  `RuntimeEndToEndTest` (all on sqlite in-memory via `SqliteSchema`).

### 2.6 Docs
- `docs/26-AI-Runtime.md` (module doc: state machine + lifecycle + ER Mermaid diagrams, WorkerResult
  contract, retry/timeout/lock/recovery/replay, cost/perf, non-technical Execution Monitor spec).
- `docs/16-ADR.md` — **ADR-0021** added (Accepted).
- This audit; `PROJECT_STATE.md` + `docs/15-Project-Roadmap.md` updated.

**File census:** 140 PHP source files under `src/Runtime/`, 28 folder `README.md` files. All
`php -l` clean; `composer dump-autoload -o` clean.

---

## 3. Architecture validation

| Invariant | Status | Evidence |
|-----------|--------|----------|
| **Single entry point** | PASS | `MasterOrchestrator::handle()` is the only public execution entry; workers are reachable only through `WorkerCoordinator`, which the orchestrator owns. |
| **State-machine-guarded transitions** | PASS | Every `Execution` mutator calls `ExecutionStateMachine::assert(from,to)` (15 references in the aggregate); illegal transitions throw `IllegalExecutionTransition`; terminal states (`Completed`, `Cancelled`) have no outgoing edges. |
| **Event-sourced + replayable** | PASS | Each mutator records a `DomainEvent`; `Execution::replay(events)` rebuilds identical state; `ReplayService` performs a read-only rebuild → `ExecutionReplayView`. |
| **Append-only event store** | PASS | `PdoExecutionEventStore` performs only `INSERT`/`SELECT` — no `UPDATE`/`DELETE` on history rows. |
| **Workers isolated through orchestrator** | PASS | Workers cannot call each other; a "more workers" request re-enters `WorkerCoordinator::dispatchCollaborative()`. `WorkerCoordinatorTest` and `MasterOrchestratorTest` assert worker-to-worker is impossible via the API. |
| **Tenant-scoped persistence** | PASS | Every PDO repository query filters `tenant_id` and `deleted_at IS NULL`; `PdoExecutionRepositoryTest` asserts cross-tenant isolation. |
| **Idempotent by ExecutionId** | PASS | `ExecutionEngine::execute()` short-circuits on an existing terminal execution and re-checks inside the lock; `ConcurrencyTest` asserts the lock serializes / idempotency prevents double-run. |
| **Retry honors RetryPolicy** | PASS | `RetryEngine` + `Execution::retry()` respect `maxAttempts`/backoff and throw `RetryExhausted` at the limit. |
| **Timeout enforced** | PASS | `TimeoutManager` measures wall/step time via an injected `Clock` and throws `ExecutionTimedOut`. |
| **Recovery from crash** | PASS | `RecoveryService` reconstructs from the event store and marks `Recovered`. |

---

## 4. Test results (REAL numbers)

Command: `cd /home/user/crm && vendor/bin/phpunit 2>&1 | tail -3`

```
Time: 00:00.169, Memory: 18.00 MB

OK (488 tests, 1378 assertions)
```

- **Full suite: 488 tests / 1378 assertions — GREEN**, under `failOnWarning="true"` and
  `failOnRisky="true"`, on **PHP 8.4.19** with **PHPUnit 11.5.55**.
- **Runtime increment: 141 tests / 411 assertions** (128 unit + 13 integration) added on top of the
  prior 347-test baseline (169 foundation/Behavior + 178 Plugin).
- `composer dump-autoload -o` clean (1959 classes); every `src/Runtime/` and `tests/*/Runtime/` file
  `php -l` clean.

---

## 5. Remaining risks

1. **Single-process distributed guarantees.** The in-process fan-out and the `InMemory*` adapters are
   correct within one PHP process; genuine multi-node behavior depends on the PDO adapters + a shared
   database, exercised here only against sqlite (see §6).
2. **Advisory-row lock liveness.** The PDO lock steals a key once the holder's TTL elapses. A holder
   that stalls longer than its TTL (e.g., a GC/IO pause) can be pre-empted; TTLs must be sized above
   the worst-case step latency until a lease-renewal daemon exists.
3. **Postgres parity unproven in CI.** The Postgres 16 migration is authored but not executed in this
   environment (no Postgres service); only the sqlite schema path is test-covered.
4. **Plugin execution is contract-level.** Manager/Worker plugins are invoked through the
   `Nizam\Platform\Plugin\Contract\*` seams via `Testing/` fakes; no real AI provider or long-running
   worker runtime is wired yet (that is a later phase).

---

## 6. Technical debt (honest)

- **DB advisory-row lock, not a true distributed lock.** `PdoExecutionLockManager` uses a single
  advisory lock row with a TTL and steal-on-expiry. This serializes executions across processes sharing
  one database but is **not** a Redis/ZooKeeper-grade distributed lock with fencing tokens and lease
  renewal — **that is future work.**
- **Parallel coordinator is in-process fan-out, not multi-node.** `DispatchMode::Parallel` is a
  deterministic in-process fan-out (independent invocations collected in order), as the Constitution
  forbids real threads/process-spawn here. Multi-node worker dispatch (queue/broker-backed) is future.
- **PDO validated on sqlite, Postgres pending an environment.** Repositories, event store, and lock
  manager are integration-tested on sqlite in-memory; Postgres 16 validation is deferred until a
  Postgres env is available in CI.
- **Cost/perf aggregation is structural, not metered.** `CostTracker`/`PerformanceTracker` aggregate
  the snapshots workers report; there is no independent metering/billing meter yet.
- **No HTTP/Console interface.** The runtime is invoked in-process; the "Execution Monitor" screen is
  specified in `docs/26-AI-Runtime.md` but not built (awaits the HTTP platform).

---

## 7. Recommendations before the next phase

1. **Stand up a Postgres 16 CI service** and run the existing integration suite against it to prove
   parity with sqlite (JSONB, UUIDv7 PK, indexes, advisory-row semantics).
2. **Introduce fencing tokens + lease renewal** on the lock (and design the Redis/true-distributed
   adapter behind the existing `ExecutionLockManager` port) before any multi-node deployment.
3. **Add a queue/broker-backed `WorkerDispatcher`** adapter behind the current port so `Parallel`
   dispatch can become genuinely multi-node without touching the domain or orchestrator.
4. **Wire a real Manager/Worker execution provider** (replacing the `Testing/` fakes) when the AI
   Providers phase lands, keeping the plugin-contract seam unchanged.
5. **Build the Execution Monitor UI** against `ReplayService`/`ExecutionMetricsView` once the HTTP
   platform exists, honoring the non-technical screen spec.

---

## Related Documents

- [docs/26-AI-Runtime.md](../26-AI-Runtime.md) — AI Runtime module documentation.
- [docs/16-ADR.md](../16-ADR.md) — ADR-0017, ADR-0018, **ADR-0021**.
- [PROJECT_STATE.md](../../PROJECT_STATE.md) — top-level project state.
- [docs/15-Project-Roadmap.md](../15-Project-Roadmap.md) — 12-phase program.
- [docs/audit/PHASE_PLUGIN_AUDIT.md](./PHASE_PLUGIN_AUDIT.md) — the preceding Plugin Platform increment.
