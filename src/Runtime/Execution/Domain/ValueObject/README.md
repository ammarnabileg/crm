# Runtime\Execution\Domain\ValueObject

**Purpose.** The immutable, compared-by-value building blocks of an execution. Each is self-validating
at construction, side-effect-free, and either returns new instances from "mutating" helpers or is
purely read-only.

**Responsibilities.**
- `WorkerResult` — the spec-mandated structured worker output: `taskResult`, `evidence`
  (`EvidenceItem[]`), `reasoningSummary`, `confidence` (validated to [0, 1]), `executionTimeMs`,
  `executionCostMicros`, `resourcesUsed`, `automationSelected`, `toolsUsed`, `warnings`, `errors`,
  `recommendations`, `logs`. The only channel by which a worker reaches the Runtime.
- `EvidenceItem` — one traceable artefact (kind, reference, summary, capturedAt) with a dedupe key.
- `ExecutionMetadata` — tenant/user/department/manager/intent context plus routing labels.
- `ExecutionTimeline` + `TimelineEntry` — the ordered, append-only, human-readable execution path.
- `CostSnapshot` / `PerformanceSnapshot` — running totals folded forward as steps complete.
- `RetryPolicy` (+ `BackoffStrategy`) — attempt cap, base delay, backoff, jitter; `shouldRetry()`,
  `nextDelayMs()`. `TimeoutPolicy` — wall-clock and per-step budgets. `ExecutionLock` — a held-lock
  handle (key, owner token, acquiredAt, ttl) with expiry/refresh helpers.

**Dependencies.** `Nizam\Kernel\Domain\{ValueObject, TenantId, UserId}`, `Nizam\Platform\Support\Assert`,
PHP `DateTimeImmutable`. No I/O.

**Public interfaces.** Each value object's constructor, its typed accessors, `equals()`, and (where
useful for persistence/read models) `toArray()`.
