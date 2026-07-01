# Runtime\Execution\Application\ValueObject

**Purpose.** The immutable value objects of the Execution application layer — the typed input and
output of `ExecutionEngine.execute()` and the verdict of the `RetryEngine`. They carry no I/O and no
business rules beyond self-validation and projection helpers, and are compared by value.

**Responsibilities.**
- `ExecutionRequest` — the input one execution runs from: `ExecutionId` (idempotency key), `TenantId`,
  optional `UserId`, `intentRef`, opaque `payload`, optional department/manager references, a
  `RetryPolicy` and `TimeoutPolicy`, and labels. `create(...)` builds one from primitives (fresh id +
  default policies); `toMetadata()` projects the `ExecutionMetadata` the aggregate is started with.
- `ExecutionResult` — the outcome: `ExecutionId`, final `ExecutionState`, an optional scalar-map
  `managerDecision` (the orchestrator projects its own decision VO into this shape so this layer stays
  free of the Orchestration package), the accrued `CostSnapshot` and `PerformanceSnapshot`, and the
  `ExecutionTimelineView`. `fromExecution()` projects a run; `withManagerDecision()` attaches a
  decision; `toArray()` serializes.
- `RetryDecision` — the `RetryEngine`'s verdict: whether to retry, the 1-based attempt number, and the
  jittered backoff delay in milliseconds.

**Dependencies.** `Nizam\Runtime\Execution\Domain\*` (`ExecutionId`, `ExecutionState`,
`ExecutionMetadata`, `RetryPolicy`, `TimeoutPolicy`, `CostSnapshot`, `PerformanceSnapshot`);
`Application\Dto\ExecutionTimelineView`; `Nizam\Kernel\Domain\{TenantId,UserId,ValueObject}`;
`Nizam\Platform\Support\Assert`; and PHP itself.

**Public interfaces.** `ExecutionRequest`, `ExecutionResult`, `RetryDecision`.

**Key rules honored.** Immutable and value-compared; self-validating at construction; no dependency on
the Orchestration package (the manager decision is a scalar map, not an Orchestration type).
