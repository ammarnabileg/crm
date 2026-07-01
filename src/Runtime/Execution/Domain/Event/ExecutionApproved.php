<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;

/**
 * Recorded when an execution under review is approved, entering
 * {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Approved}.
 *
 * Carries who approved it. Emitted by {@see \Nizam\Runtime\Execution\Domain\Execution::approve()}.
 */
final class ExecutionApproved implements DomainEvent
{
    /**
     * @param ExecutionId       $executionId The approved execution.
     * @param string            $approvedBy  Identity that approved it.
     * @param DateTimeImmutable $occurredAt  When it was approved.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly string $approvedBy,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The approved execution.
     */
    public function executionId(): ExecutionId
    {
        return $this->executionId;
    }

    /**
     * The identity that approved it.
     */
    public function approvedBy(): string
    {
        return $this->approvedBy;
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
        return 'runtime.execution_approved';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->executionId->toString();
    }
}
