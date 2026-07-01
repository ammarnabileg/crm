# Runtime\Execution\Application\Port

**Purpose.** The application-layer ports the `ExecutionPipeline` depends on for the two concerns that
belong outside the Execution layer: deciding *what steps* an execution runs, and *running* each step
to a result. The Execution application layer declares these interfaces and ships deterministic default
adapters; the Orchestration layer provides the manager-driven, worker-backed implementations. This
keeps the pipeline testable in isolation and enforces that workers are only ever invoked behind a
port — never directly by execution code.

**Responsibilities.**
- `ExecutionPlanner` — `plan(ExecutionRequest): list<ExecutionStep>`: the ordered, non-empty steps an
  execution should run. In production the orchestration layer resolves the manager plugin and asks it
  to plan; `DefaultExecutionPlanner` supplies a single-step default.
- `WorkerDispatcher` — `dispatch(ExecutionStep, ExecutionRequest): WorkerResult`: run one step to its
  structured result. In production the orchestration layer's `WorkerCoordinator` invokes the worker
  plugin(s); `DefaultWorkerDispatcher` supplies a deterministic success. Workers are never called
  directly by the pipeline — every invocation passes through this port.

**Dependencies.** `Nizam\Runtime\Execution\Application\ValueObject\ExecutionRequest`;
`Nizam\Runtime\Execution\Domain\{ExecutionStep, ValueObject\WorkerResult}`; and PHP itself.

**Public interfaces.** `ExecutionPlanner`, `WorkerDispatcher`.

**Key rules honored.** The Execution layer decides *nothing* about manager/worker plugins itself; both
concerns are abstracted behind these ports, satisfied by safe deterministic defaults here and by the
orchestration layer in production.
