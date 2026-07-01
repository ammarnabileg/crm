<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;

/**
 * Recorded when an execution is terminated before completion, entering the terminal
 * {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Cancelled} state.
 *
 * Carries who cancelled it. Emitted by {@see \Nizam\Runtime\Execution\Domain\Execution::cancel()}.
 */
final class ExecutionCancelled implements DomainEvent
{
    /**
     * @param ExecutionId       $executionId The cancelled execution.
     * @param string            $cancelledBy Identity that cancelled it.
     * @param DateTimeImmutable $occurredAt  When it was cancelled.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly string $cancelledBy,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The cancelled execution.
     */
    public function executionId(): ExecutionId
    {
        return $this->executionId;
    }

    /**
     * The identity that cancelled it.
     */
    public function cancelledBy(): string
    {
        return $this->cancelledBy;
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
        return 'runtime.execution_cancelled';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->executionId->toString();
    }
}
