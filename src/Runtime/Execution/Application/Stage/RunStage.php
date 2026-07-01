<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Stage;

use Nizam\Runtime\Execution\Application\Exception\ExecutionApplicationException;
use Nizam\Runtime\Execution\Application\PipelineContext;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionStep;
use Nizam\Runtime\Execution\Domain\ExecutionStepState;

/**
 * The Run stage: execute every assigned step to completion, folding each worker result in.
 *
 * The stage begins the execution — {@see Execution::run()} starts the first pending step and takes the
 * execution into {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Running} — then drives the
 * remaining steps in order: for each running step it guards the per-step timeout, dispatches the step
 * through the {@see \Nizam\Runtime\Execution\Application\Port\WorkerDispatcher}, completes it with the
 * returned {@see \Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult} (which the aggregate folds
 * into its cost and performance and, while steps remain, parks in Waiting), then begins the next
 * pending step. The loop ends with the execution back in Running and no pending steps, ready for
 * review. Each iteration is bounded by the step count, so the fan-out is deterministic and terminates.
 */
final class RunStage implements PipelineStage
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'run';
    }

    /**
     * {@inheritDoc}
     *
     * @throws ExecutionApplicationException When the execution has no step to run.
     * @throws \Nizam\Runtime\Execution\Domain\Exception\ExecutionTimedOut When a step overruns its budget.
     */
    public function process(PipelineContext $context): void
    {
        $execution = $context->execution();
        if ($execution->steps() === []) {
            throw ExecutionApplicationException::pipelineStalled(
                $execution->executionId()->toString(),
                $this->name() . ': there are no steps to run',
            );
        }

        $context->timeout()->assertWithinWallClock();
        $execution->run($context->clock());

        // Deterministic in-process fan-out: run each step exactly once, in assignment order.
        $total = count($execution->steps());
        for ($processed = 0; $processed < $total; ++$processed) {
            $running = $this->runningStep($execution);
            if ($running === null) {
                break;
            }

            $this->runStep($context, $running);

            $next = $this->firstPendingStep($execution);
            if ($next === null) {
                break;
            }

            $execution->beginStep($next->stepId(), $context->clock());
        }
    }

    /**
     * Dispatch a single running step, fold its result in, and raise when the worker reported errors.
     *
     * A worker that returns an error result leaves its step {@see ExecutionStepState::Failed}; the
     * stage treats that as an execution failure and raises so the pipeline short-circuits the whole
     * execution to Failed (from which the engine may recover-and-retry). A successful step leaves the
     * execution progressing normally.
     *
     * @throws ExecutionApplicationException When the completed step ended in a failed state.
     */
    private function runStep(PipelineContext $context, ExecutionStep $step): void
    {
        $startedAt = $step->startedAt() ?? $context->timeout()->startedAt();
        $context->timeout()->assertStepWithin($step->stepId()->toString(), $startedAt);

        $result = $context->dispatcher()->dispatch($step, $context->request());
        $execution = $context->execution();
        $execution->completeStep($step->stepId(), $result, $context->clock());

        $completed = $execution->step($step->stepId());
        if ($completed !== null && $completed->state() === ExecutionStepState::Failed) {
            throw ExecutionApplicationException::pipelineStalled(
                $execution->executionId()->toString(),
                sprintf('%s: worker for step "%s" reported errors', $this->name(), $step->name()),
            );
        }
    }

    /**
     * The step currently in {@see ExecutionStepState::Running}, or null when none is.
     */
    private function runningStep(Execution $execution): ?ExecutionStep
    {
        foreach ($execution->steps() as $step) {
            if ($step->state() === ExecutionStepState::Running) {
                return $step;
            }
        }

        return null;
    }

    /**
     * The first step still {@see ExecutionStepState::Pending}, in assignment order, or null when none.
     */
    private function firstPendingStep(Execution $execution): ?ExecutionStep
    {
        foreach ($execution->steps() as $step) {
            if ($step->state() === ExecutionStepState::Pending) {
                return $step;
            }
        }

        return null;
    }
}
