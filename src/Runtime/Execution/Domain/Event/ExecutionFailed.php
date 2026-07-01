<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;

/**
 * Recorded when an execution ends in failure, entering
 * {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Failed}.
 *
 * Carries the human reason for the failure. Emitted by
 * {@see \Nizam\Runtime\Execution\Domain\Execution::fail()}.
 */
final class ExecutionFailed implements DomainEvent
{
    /**
     * @param ExecutionId       $executionId The failed execution.
     * @param string            $reason      The human-readable reason for the failure.
     * @param DateTimeImmutable $occurredAt  When it failed.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly string $reason,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The failed execution.
     */
    public function executionId(): ExecutionId
    {
        return $this->executionId;
    }

    /**
     * The human-readable reason for the failure.
     */
    public function reason(): string
    {
        return $this->reason;
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
        return 'runtime.execution_failed';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->executionId->toString();
    }
}
