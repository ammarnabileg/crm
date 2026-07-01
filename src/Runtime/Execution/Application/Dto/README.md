# Runtime\Execution\Application\Dto

**Purpose.** The read models the Execution application layer returns to the layers above (the
orchestrator, queries, the non-technical Execution Monitor, audit/replay tooling). Each is built of
scalars and plain arrays only — safe to serialize and transport — and never leaks a domain aggregate,
entity, or mutable value object.

**Responsibilities.**
- `ExecutionView` — a flat projection of an `Execution`: identity, metadata (tenant/user/department/
  manager/intent/labels), current state, its `ExecutionStepView`s, its `ExecutionTimelineView`, its
  `ExecutionMetricsView`, attempt counter, timestamps, and version.
- `ExecutionStepView` — a flat projection of an `ExecutionStep`: identity, name, state, worker
  reference, attempt, start/finish instants, duration, and its `WorkerResult` as a scalar map.
- `ExecutionTimelineView` — the ordered, human-readable timeline (state, instant, friendly note) as
  scalar maps; the stack-trace-free story surfaced to the Execution Monitor. Value-compared.
- `ExecutionMetricsView` — flattened cost (tokens, currency micros, per-provider breakdown) and
  performance (wall/CPU ms, step and retry counts) for dashboards and reporting.
- `ExecutionReplayView` — a read-only rebuild of an execution from its event stream: the full
  `ExecutionView` state plus the ordered names of the events replayed and how many were applied.

**Dependencies.** `Nizam\Runtime\Execution\Domain\*` (`Execution`, `ExecutionStep`, `ExecutionId`,
`ExecutionTimeline`, `CostSnapshot`, `PerformanceSnapshot`); `Nizam\Kernel\Domain\ValueObject` (for
the value-compared timeline view); and PHP itself.

**Public interfaces.** `ExecutionView`, `ExecutionStepView`, `ExecutionTimelineView`,
`ExecutionMetricsView`, `ExecutionReplayView`.

**Key rules honored.** Scalar-only and transport-safe; built via `fromDomain(...)`/`fromExecution(...)`
factories; strictly read-only — projecting never mutates, persists, or publishes.
