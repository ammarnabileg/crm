<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ExecutionStepId;

/**
 * Recorded when a specific step of an execution begins running.
 *
 * Emitted by {@see \Nizam\Runtime\Execution\Domain\Execution::beginStep()}. Carries the step id so a
 * replay can re-stamp the step's start.
 */
final class ExecutionStepStarted implements DomainEvent
{
    /**
     * @param ExecutionId       $executionId The owning execution.
     * @param ExecutionStepId   $stepId      The step that started.
     * @param DateTimeImmutable $occurredAt  When the step started.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly ExecutionStepId $stepId,
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
     * The step that started.
     */
    public function stepId(): ExecutionStepId
    {
        return $this->stepId;
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
        return 'runtime.execution_step_started';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->executionId->toString();
    }
}
