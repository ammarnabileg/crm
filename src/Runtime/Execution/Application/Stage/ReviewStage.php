<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Stage;

use Nizam\Runtime\Execution\Application\PipelineContext;

/**
 * The Review stage: hold the executed work for review, then approve it.
 *
 * With all steps complete the execution is in {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Running};
 * this stage sends it to {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Review} and then, in the
 * default runtime path, records an automatic approval by the Runtime itself so the following
 * {@see FinalizeStage} may complete it. Rejection and human review are modelled by the aggregate
 * ({@see \Nizam\Runtime\Execution\Domain\Execution::reject()}) and driven by the orchestration layer;
 * the default pipeline approves so an unattended execution can finish. The approver identity is stable
 * for auditability.
 */
final class ReviewStage implements PipelineStage
{
    /**
     * The identity recorded as the approver when the Runtime auto-approves an unattended execution.
     */
    public const string RUNTIME_APPROVER = 'runtime';

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'review';
    }

    /**
     * {@inheritDoc}
     */
    public function process(PipelineContext $context): void
    {
        $context->timeout()->assertWithinWallClock();

        $execution = $context->execution();
        $execution->toReview($context->clock());
        $execution->approve(self::RUNTIME_APPROVER, $context->clock());
    }
}
