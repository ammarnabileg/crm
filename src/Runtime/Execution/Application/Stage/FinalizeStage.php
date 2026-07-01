<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Stage;

use Nizam\Runtime\Execution\Application\PipelineContext;

/**
 * The Finalize stage: complete an approved execution, reaching the terminal Completed state.
 *
 * The stage transitions the aggregate from {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Approved}
 * to terminal {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Completed}, the successful end of
 * the lifecycle. It is the last stage the {@see \Nizam\Runtime\Execution\Application\ExecutionPipeline}
 * runs; the aggregate's own guard enforces that completion is only legal from Approved.
 */
final class FinalizeStage implements PipelineStage
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'finalize';
    }

    /**
     * {@inheritDoc}
     */
    public function process(PipelineContext $context): void
    {
        $context->execution()->complete($context->clock());
    }
}
