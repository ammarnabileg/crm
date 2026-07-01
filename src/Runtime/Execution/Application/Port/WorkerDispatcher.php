<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Port;

use Nizam\Runtime\Execution\Application\ValueObject\ExecutionRequest;
use Nizam\Runtime\Execution\Domain\ExecutionStep;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;

/**
 * The port through which the Run stage obtains a {@see WorkerResult} for a single step.
 *
 * Dispatching a step to a worker plugin — and, for parallel or collaborative work, coordinating
 * several — is an orchestration concern that lives outside the Execution application layer. The
 * pipeline depends only on this port: given a planned {@see ExecutionStep} and the originating
 * {@see ExecutionRequest}, it returns the structured {@see WorkerResult} the worker produced, which
 * the pipeline then folds into the execution. Workers are never invoked directly by the pipeline;
 * every invocation passes through an implementation of this port (the orchestration layer's worker
 * coordinator in production, a deterministic stub in isolation tests).
 */
interface WorkerDispatcher
{
    /**
     * Dispatch a single step to its worker and return the structured result.
     *
     * @param ExecutionStep    $step    The step to run (carries the worker reference).
     * @param ExecutionRequest $request The originating request (carries the payload and context).
     *
     * @return WorkerResult The worker's self-validating structured output.
     */
    public function dispatch(ExecutionStep $step, ExecutionRequest $request): WorkerResult;
}
