<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application;

use Nizam\Kernel\Domain\Clock;
use Nizam\Runtime\Execution\Application\Port\ExecutionPlanner;
use Nizam\Runtime\Execution\Application\Port\WorkerDispatcher;
use Nizam\Runtime\Execution\Application\ValueObject\ExecutionRequest;
use Nizam\Runtime\Execution\Domain\Execution;

/**
 * The request-scoped carrier threaded through the {@see ExecutionPipeline}'s stages.
 *
 * A context bundles everything a {@see Stage\PipelineStage} needs to advance one execution: the live
 * {@see Execution} aggregate, the originating {@see ExecutionRequest}, the {@see Clock} every mutation
 * takes its time from, the {@see TimeoutManager} guarding the run's budgets, the {@see ExecutionPlanner}
 * that supplies steps, and the {@see WorkerDispatcher} that runs them. It is a mutable, single-run
 * carrier — not a value object — created once per pipeline invocation and passed by reference so each
 * stage mutates the same aggregate. It performs no I/O of its own; it merely holds collaborators.
 */
final class PipelineContext
{
    /**
     * @param Execution        $execution The live aggregate the stages advance.
     * @param ExecutionRequest $request   The originating request.
     * @param Clock            $clock     The time source every mutation uses.
     * @param TimeoutManager   $timeout   The budget guard for this run.
     * @param ExecutionPlanner $planner   The port supplying the steps to assign.
     * @param WorkerDispatcher $dispatcher The port running each step to a result.
     */
    public function __construct(
        private readonly Execution $execution,
        private readonly ExecutionRequest $request,
        private readonly Clock $clock,
        private readonly TimeoutManager $timeout,
        private readonly ExecutionPlanner $planner,
        private readonly WorkerDispatcher $dispatcher,
    ) {
    }

    /**
     * The live aggregate the stages advance.
     */
    public function execution(): Execution
    {
        return $this->execution;
    }

    /**
     * The originating request.
     */
    public function request(): ExecutionRequest
    {
        return $this->request;
    }

    /**
     * The time source every mutation uses.
     */
    public function clock(): Clock
    {
        return $this->clock;
    }

    /**
     * The budget guard for this run.
     */
    public function timeout(): TimeoutManager
    {
        return $this->timeout;
    }

    /**
     * The port supplying the steps to assign.
     */
    public function planner(): ExecutionPlanner
    {
        return $this->planner;
    }

    /**
     * The port running each step to a result.
     */
    public function dispatcher(): WorkerDispatcher
    {
        return $this->dispatcher;
    }
}
