<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;

/**
 * Recorded when a failed execution is reconstructed and made resumable, entering
 * {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Recovered}.
 *
 * Emitted by {@see \Nizam\Runtime\Execution\Domain\Execution::recover()} after the recovery service
 * rebuilds the aggregate from the event store following a crash.
 */
final class ExecutionRecovered implements DomainEvent
{
    /**
     * @param ExecutionId       $executionId The recovered execution.
     * @param DateTimeImmutable $occurredAt  When it was recovered.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The recovered execution.
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
        return 'runtime.execution_recovered';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->executionId->toString();
    }
}
