<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;

/**
 * Recorded when an execution under review is rejected, entering
 * {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Rejected}.
 *
 * Carries who rejected it and why. Emitted by {@see \Nizam\Runtime\Execution\Domain\Execution::reject()}.
 */
final class ExecutionRejected implements DomainEvent
{
    /**
     * @param ExecutionId       $executionId The rejected execution.
     * @param string            $rejectedBy  Identity that rejected it.
     * @param string            $reason      The human-readable reason for rejection.
     * @param DateTimeImmutable $occurredAt  When it was rejected.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly string $rejectedBy,
        private readonly string $reason,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The rejected execution.
     */
    public function executionId(): ExecutionId
    {
        return $this->executionId;
    }

    /**
     * The identity that rejected it.
     */
    public function rejectedBy(): string
    {
        return $this->rejectedBy;
    }

    /**
     * The human-readable reason for rejection.
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
        return 'runtime.execution_rejected';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->executionId->toString();
    }
}
