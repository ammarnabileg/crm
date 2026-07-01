<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Port;

use Nizam\Runtime\Execution\Application\ExecutionEngine;
use Nizam\Runtime\Execution\Application\Port\ExecutionPlanner;
use Nizam\Runtime\Execution\Application\Port\WorkerDispatcher;

/**
 * The port that builds an {@see ExecutionEngine} bound to a *request-scoped* planner and dispatcher.
 *
 * The engine plans and dispatches through the {@see ExecutionPlanner} and {@see WorkerDispatcher} ports
 * it is constructed with. For a real run those two must be scoped to the current request — the planner
 * must yield the steps the resolved Manager plugin decided on, and the dispatcher must invoke the
 * specific worker each step was assigned to through the {@see \Nizam\Runtime\Orchestration\WorkerCoordinator}.
 * Rather than have the {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} assemble the engine's
 * persistence/lock/event collaborators itself (which would couple it to infrastructure), it depends on
 * this factory: the infrastructure layer supplies an implementation that holds the shared collaborators
 * and stamps in the per-request planner and dispatcher. This keeps the orchestrator free of I/O wiring
 * while still letting each request drive its own manager and workers.
 */
interface ExecutionEngineFactory
{
    /**
     * Build an execution engine bound to the given request-scoped planner and dispatcher.
     *
     * @param ExecutionPlanner  $planner    The request-scoped planner yielding the manager's steps.
     * @param WorkerDispatcher  $dispatcher The request-scoped dispatcher invoking the assigned workers.
     *
     * @return ExecutionEngine An engine ready to execute the request.
     */
    public function build(ExecutionPlanner $planner, WorkerDispatcher $dispatcher): ExecutionEngine;
}
