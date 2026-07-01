<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Stage;

use Nizam\Runtime\Execution\Application\PipelineContext;

/**
 * The Plan stage: move the execution into {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Planning}.
 *
 * Planning is the phase where the manager decides how the work decomposes; here the stage simply
 * transitions the aggregate into Planning (the aggregate's own guard enforces that this is legal only
 * from Pending or Recovered) and stamps the timeline. The actual decomposition into steps happens in
 * the following {@see AssignStage} via the {@see \Nizam\Runtime\Execution\Application\Port\ExecutionPlanner}.
 * The stage also re-checks the wall-clock budget so a run that has already overrun fails fast.
 */
final class PlanStage implements PipelineStage
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'plan';
    }

    /**
     * {@inheritDoc}
     */
    public function process(PipelineContext $context): void
    {
        $context->timeout()->assertWithinWallClock();
        $context->execution()->plan($context->clock());
    }
}
