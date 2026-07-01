<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;

/**
 * Recorded when an execution finishes successfully, entering the terminal
 * {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Completed} state.
 *
 * Emitted by {@see \Nizam\Runtime\Execution\Domain\Execution::complete()}.
 */
final class ExecutionCompleted implements DomainEvent
{
    /**
     * @param ExecutionId       $executionId The completed execution.
     * @param DateTimeImmutable $occurredAt  When it completed.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The completed execution.
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
        return 'runtime.execution_completed';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->executionId->toString();
    }
}
