<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;

/**
 * Recorded when an execution is held for review, entering
 * {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Review}.
 *
 * Emitted by {@see \Nizam\Runtime\Execution\Domain\Execution::toReview()}.
 */
final class ExecutionSentToReview implements DomainEvent
{
    /**
     * @param ExecutionId       $executionId The execution sent to review.
     * @param DateTimeImmutable $occurredAt  When it entered review.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The execution sent to review.
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
        return 'runtime.execution_sent_to_review';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->executionId->toString();
    }
}
