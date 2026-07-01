<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Stage;

use Nizam\Runtime\Execution\Application\Exception\ExecutionApplicationException;
use Nizam\Runtime\Execution\Application\PipelineContext;

/**
 * The Assign stage: plan the execution's steps and assign them, entering Assigned.
 *
 * The stage asks the {@see \Nizam\Runtime\Execution\Application\Port\ExecutionPlanner} on the context
 * for the ordered steps this execution should run, then hands them to the aggregate's
 * {@see \Nizam\Runtime\Execution\Domain\Execution::assign()}, which moves the execution into
 * {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Assigned}. A planner that returns no steps is
 * a failure — an execution must have at least one unit of work — so the stage raises an
 * {@see ExecutionApplicationException} to short-circuit the pipeline.
 */
final class AssignStage implements PipelineStage
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'assign';
    }

    /**
     * {@inheritDoc}
     *
     * @throws ExecutionApplicationException When the planner produces no steps.
     */
    public function process(PipelineContext $context): void
    {
        $context->timeout()->assertWithinWallClock();

        $steps = $context->planner()->plan($context->request());
        if ($steps === []) {
            throw ExecutionApplicationException::pipelineStalled(
                $context->execution()->executionId()->toString(),
                $this->name() . ': the planner produced no steps',
            );
        }

        $context->execution()->assign($steps, $context->clock());
    }
}
