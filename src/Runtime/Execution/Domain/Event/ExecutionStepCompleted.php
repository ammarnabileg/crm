<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ExecutionStepId;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;

/**
 * Recorded when a step of an execution completes and its worker result is captured.
 *
 * Emitted by {@see \Nizam\Runtime\Execution\Domain\Execution::completeStep()}. Carries the full
 * {@see WorkerResult} so a replay can reattach it and re-fold its cost and performance into the
 * execution's snapshots.
 */
final class ExecutionStepCompleted implements DomainEvent
{
    /**
     * @param ExecutionId       $executionId The owning execution.
     * @param ExecutionStepId   $stepId      The step that completed.
     * @param WorkerResult      $result      The worker's structured output.
     * @param DateTimeImmutable $occurredAt  When the step completed.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly ExecutionStepId $stepId,
        private readonly WorkerResult $result,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The owning execution.
     */
    public function executionId(): ExecutionId
    {
        return $this->executionId;
    }

    /**
     * The step that completed.
     */
    public function stepId(): ExecutionStepId
    {
        return $this->stepId;
    }

    /**
     * The worker's structured output.
     */
    public function result(): WorkerResult
    {
        return $this->result;
    }

    /**
     * {@inheritDoc}
     */
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * {@inheritDoc}
     */
    public function eventName(): string
    {
        return 'runtime.execution_step_completed';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->executionId->toString();
    }
}
