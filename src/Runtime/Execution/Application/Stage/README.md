# Runtime\Execution\Application\Stage

**Purpose.** The ordered stages the `ExecutionPipeline` runs to drive one `Execution` through its
lifecycle: Validate → Plan → Assign → Run → Review → Finalize. Each stage performs exactly one
transition-worth of work on the aggregate carried by the `PipelineContext`, honoring ADR-0018's state
machine (the aggregate's own guards enforce legality). A stage never persists or publishes — the
`ExecutionEngine` owns the transaction and lock — and a stage that cannot legally act must raise so
the pipeline short-circuits the execution to `Failed`.

**Responsibilities.**
- `PipelineStage` — the interface every stage implements: `name(): string`, `process(PipelineContext):
  void`.
- `ValidateStage` — gate: runs `ExecutionValidator` over the request and asserts the execution is
  freshly `Pending`; raises on invalid input so nothing is planned.
- `PlanStage` — transitions the execution into `Planning`.
- `AssignStage` — asks the `ExecutionPlanner` for the ordered steps and `assign()`s them (→ `Assigned`);
  raises when no steps are produced.
- `RunStage` — `run()`s the first step (→ `Running`), then deterministically fans out the remaining
  steps in order: guard the per-step timeout, dispatch via `WorkerDispatcher`, `completeStep()`
  (cost/perf folded in; `Waiting` derived while steps remain), begin the next; raises when a worker
  returns errors so the execution fails.
- `ReviewStage` — `toReview()` then auto-`approve()`s (the unattended default path) so `FinalizeStage`
  may complete; human review/rejection is driven by the orchestration layer.
- `FinalizeStage` — `complete()`s the approved execution (→ terminal `Completed`).

**Dependencies.** `Nizam\Runtime\Execution\Application\{PipelineContext,ExecutionValidator}`; the
application ports `Port\{ExecutionPlanner,WorkerDispatcher}`; `Nizam\Runtime\Execution\Domain\*`
(aggregate, `ExecutionState`, `ExecutionStepState`, steps, exceptions);
`Application\Exception\ExecutionApplicationException`; and PHP itself.

**Public interfaces.** `PipelineStage` and the six concrete stages, consumed by `ExecutionPipeline`.

**Key rules honored.** One legal transition per stage; no I/O; fail-fast to `Failed` on any error
(including timeouts and worker-reported errors); deterministic, ordered in-process step fan-out.
