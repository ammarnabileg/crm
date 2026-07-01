<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application;

use Nizam\Runtime\Execution\Application\Port\ExecutionPlanner;
use Nizam\Runtime\Execution\Application\ValueObject\ExecutionRequest;
use Nizam\Runtime\Execution\Domain\ExecutionStep;
use Nizam\Runtime\Execution\Domain\ExecutionStepId;

/**
 * A deterministic, dependency-free planner that maps a request into a single worker step.
 *
 * This is the Execution application layer's safe default {@see ExecutionPlanner}: it lets the pipeline
 * and engine run end-to-end without an orchestration/manager plugin present, so the layer is testable
 * in isolation. It derives exactly one step whose worker reference is the request's intent (or a named
 * default when the intent is unusable as a reference) and whose name describes the intent. Production
 * planning — real manager-driven decomposition into many steps and workers — is supplied by the
 * orchestration layer through the same port.
 */
final class DefaultExecutionPlanner implements ExecutionPlanner
{
    /**
     * The worker reference used when the request's intent is not a usable reference.
     */
    public const string DEFAULT_WORKER_REF = 'runtime.default_worker';

    /**
     * {@inheritDoc}
     */
    public function plan(ExecutionRequest $request): array
    {
        $intent = trim($request->intentRef());
        $workerRef = $intent !== '' ? $intent : self::DEFAULT_WORKER_REF;

        return [
            ExecutionStep::assign(
                ExecutionStepId::generate(),
                sprintf('Fulfil intent "%s"', $workerRef),
                $workerRef,
            ),
        ];
    }
}
