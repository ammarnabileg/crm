<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application;

use Nizam\Runtime\Execution\Application\Port\WorkerDispatcher;
use Nizam\Runtime\Execution\Application\ValueObject\ExecutionRequest;
use Nizam\Runtime\Execution\Domain\ExecutionStep;
use Nizam\Runtime\Execution\Domain\ValueObject\EvidenceItem;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;

/**
 * A deterministic, dependency-free dispatcher that produces a successful result for any step.
 *
 * This is the Execution application layer's safe default {@see WorkerDispatcher}: it lets the pipeline
 * and engine run end-to-end without a real worker plugin present, so the layer is testable in
 * isolation. It echoes the request payload back as the task result, reports full confidence, and
 * records a single evidence item naming the step and worker — a completely deterministic output with
 * no time or cost, no I/O, and no randomness. Production dispatch — real worker plugins invoked through
 * the orchestration layer's coordinator — is supplied through the same port.
 */
final class DefaultWorkerDispatcher implements WorkerDispatcher
{
    /**
     * @param \DateTimeImmutable $capturedAt The fixed instant stamped on the evidence, for determinism.
     */
    public function __construct(
        private readonly \DateTimeImmutable $capturedAt = new \DateTimeImmutable('1970-01-01T00:00:00+00:00'),
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(ExecutionStep $step, ExecutionRequest $request): WorkerResult
    {
        $evidence = new EvidenceItem(
            'step',
            $step->stepId()->toString(),
            sprintf('Worker "%s" fulfilled step "%s".', $step->workerRef(), $step->name()),
            $this->capturedAt,
        );

        return new WorkerResult(
            taskResult: $request->payload(),
            evidence: [$evidence],
            reasoningSummary: sprintf('Deterministically fulfilled by "%s".', $step->workerRef()),
            confidence: 1.0,
            executionTimeMs: 0,
            executionCostMicros: 0,
            resourcesUsed: ['tokens' => 0],
            automationSelected: null,
            toolsUsed: [],
            warnings: [],
            errors: [],
            recommendations: [],
            logs: [sprintf('Dispatched step %s to %s.', $step->stepId()->toString(), $step->workerRef())],
        );
    }
}
