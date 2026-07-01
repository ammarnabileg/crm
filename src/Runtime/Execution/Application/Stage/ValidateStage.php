<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Stage;

use Nizam\Runtime\Execution\Application\Exception\ExecutionApplicationException;
use Nizam\Runtime\Execution\Application\ExecutionValidator;
use Nizam\Runtime\Execution\Application\PipelineContext;
use Nizam\Runtime\Execution\Domain\ExecutionState;

/**
 * The first pipeline stage: confirm the request is well-formed and the execution is freshly pending.
 *
 * Validation runs the {@see ExecutionValidator} over the originating request and, on failure, raises
 * an {@see ExecutionApplicationException} so the pipeline short-circuits the execution to Failed
 * before any work is planned. It also asserts the execution is in {@see ExecutionState::Pending} — a
 * freshly started execution — since re-validating an in-flight execution would be a programming error.
 * The stage mutates nothing on success; it is a gate.
 */
final class ValidateStage implements PipelineStage
{
    /**
     * @param ExecutionValidator $validator The request validator whose result gates the pipeline.
     */
    public function __construct(
        private readonly ExecutionValidator $validator,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'validate';
    }

    /**
     * {@inheritDoc}
     *
     * @throws ExecutionApplicationException When the request is invalid or the execution is not pending.
     */
    public function process(PipelineContext $context): void
    {
        $execution = $context->execution();
        if ($execution->state() !== ExecutionState::Pending) {
            throw ExecutionApplicationException::pipelineStalled(
                $execution->executionId()->toString(),
                $this->name(),
            );
        }

        $result = $this->validator->validate($context->request());
        if ($result->isErr()) {
            throw ExecutionApplicationException::pipelineStalled(
                $execution->executionId()->toString(),
                $this->name() . ': ' . (string) $result->errorMessage(),
            );
        }

        assert($result->isOk() === true, 'A non-error validation result must be OK.');
        $context->timeout()->assertWithinWallClock();
    }
}
