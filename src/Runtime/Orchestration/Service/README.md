# Runtime\Orchestration\Service

**Purpose.** The request-scoped bridge adapters that connect the `MasterOrchestrator` to the Execution
engine's two application ports (`ExecutionPlanner`, `WorkerDispatcher`). They exist because coordination
(the three dispatch modes, permission gating, merging) is an orchestration concern that must happen once,
through the `WorkerCoordinator` — while the engine still needs to drive each step through its ports so the
aggregate folds cost, performance, and the timeline in the normal way. These adapters are constructed per
request and hold only that request's precomputed data.

**Responsibilities.**
- `ManagerDrivenPlanner` — an `ExecutionPlanner` that returns the exact `ExecutionStep`s the resolved
  Manager plugin already planned (the manager decides *what* the work is; the engine decides *how* it is
  driven). Returning the same steps on a recovered attempt keeps a resumed run deterministic.
- `CoordinatedWorkerDispatcher` — a `WorkerDispatcher` that replays, per step, the `WorkerResult` the
  `WorkerCoordinator` already produced (keyed by `ExecutionStepId`). Coordination therefore runs exactly
  once, through the coordinator, and the engine replays it deterministically; a step with no
  coordinator-produced result is a programming error and raises.

**Dependencies.** The Execution layer's `Port\{ExecutionPlanner,WorkerDispatcher}`, `ExecutionRequest`,
`ExecutionStep`, `ExecutionStepId`, and `WorkerResult`; the layer's own `OrchestrationException`; and PHP.
No I/O.

**Public interfaces.** `ManagerDrivenPlanner`, `CoordinatedWorkerDispatcher` — both are constructed by the
`MasterOrchestrator` and handed to the `ExecutionEngineFactory`.
