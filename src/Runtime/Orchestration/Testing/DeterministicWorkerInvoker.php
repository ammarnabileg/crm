<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Testing;

use DateTimeImmutable;
use Nizam\Platform\Plugin\Contract\WorkerPlugin;
use Nizam\Runtime\Execution\Domain\ValueObject\EvidenceItem;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;
use Nizam\Runtime\Orchestration\Port\WorkerInvoker;
use Nizam\Runtime\Orchestration\ValueObject\ExecutionContext;
use Nizam\Runtime\Orchestration\ValueObject\WorkTask;

/**
 * A deterministic, dependency-free {@see WorkerInvoker} for tests and safe defaults.
 *
 * It runs a resolved worker against a task with no I/O, no clock, and no randomness: it echoes the task
 * payload back as the {@see WorkerResult::taskResult()}, reports a fixed confidence, and attaches one
 * evidence item naming the task and worker — so a given task always yields the same result. A per-task
 * confidence override lets a test drive the {@see \Nizam\Runtime\Orchestration\ConfidenceEvaluator} and
 * the manager's decision down a chosen branch (low confidence, or an error that fails the step) without
 * any real worker. It never reaches another worker; it is only ever called by the coordinator.
 */
final class DeterministicWorkerInvoker implements WorkerInvoker
{
    /** @var array<string, float> */
    private array $confidenceByTaskId = [];

    /** @var array<string, list<string>> */
    private array $errorsByTaskId = [];

    /**
     * @param float             $defaultConfidence The confidence returned for tasks with no override, in [0, 1].
     * @param DateTimeImmutable $capturedAt        The fixed instant stamped on evidence, for determinism.
     */
    public function __construct(
        private readonly float $defaultConfidence = 1.0,
        private readonly DateTimeImmutable $capturedAt = new DateTimeImmutable('1970-01-01T00:00:00+00:00'),
    ) {
    }

    /**
     * Override the confidence a specific task's result will report.
     */
    public function withConfidence(string $taskId, float $confidence): self
    {
        $this->confidenceByTaskId[$taskId] = $confidence;

        return $this;
    }

    /**
     * Make a specific task's result report the given errors (which fails its step in the engine).
     *
     * @param list<string> $errors
     */
    public function withErrors(string $taskId, array $errors): self
    {
        $this->errorsByTaskId[$taskId] = $errors;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function invoke(WorkerPlugin $worker, WorkTask $task, ExecutionContext $context): WorkerResult
    {
        $evidence = new EvidenceItem(
            'task',
            $task->taskId(),
            sprintf('Worker "%s" fulfilled task "%s".', $worker->manifest()->name(), $task->intent()),
            $this->capturedAt,
        );

        $confidence = $this->confidenceByTaskId[$task->taskId()] ?? $this->defaultConfidence;
        $errors = $this->errorsByTaskId[$task->taskId()] ?? [];

        return new WorkerResult(
            taskResult: $task->payload(),
            evidence: [$evidence],
            reasoningSummary: sprintf('Deterministically fulfilled by "%s".', $worker->manifest()->name()),
            confidence: $confidence,
            executionTimeMs: 0,
            executionCostMicros: 0,
            resourcesUsed: ['tokens' => 0],
            automationSelected: null,
            toolsUsed: [],
            warnings: [],
            errors: $errors,
            recommendations: [],
            logs: [sprintf('Invoked worker %s for task %s.', $worker->manifest()->name(), $task->taskId())],
        );
    }
}
