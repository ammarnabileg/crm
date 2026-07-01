<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;

/**
 * Recorded when an execution moves into {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Planning}.
 *
 * Marks the point at which the manager begins deciding which steps and workers the work requires.
 * Emitted by {@see \Nizam\Runtime\Execution\Domain\Execution::plan()}.
 */
final class ExecutionPlanned implements DomainEvent
{
    /**
     * @param ExecutionId       $executionId The execution being planned.
     * @param DateTimeImmutable $occurredAt  When planning began.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The execution being planned.
     */
    public function executionId(): ExecutionId
    {
        return $this->executionId;
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
        return 'runtime.execution_planned';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->executionId->toString();
    }
}
