<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;

/**
 * Recorded when an execution enters {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Retrying}.
 *
 * Carries the attempt number now being prepared and the human reason for the retry. Emitted by
 * {@see \Nizam\Runtime\Execution\Domain\Execution::retry()}.
 */
final class ExecutionRetried implements DomainEvent
{
    /**
     * @param ExecutionId       $executionId The execution being retried.
     * @param int               $attempt     The attempt number now being prepared (>= 2).
     * @param string            $reason      The human-readable reason for the retry.
     * @param DateTimeImmutable $occurredAt  When the retry was scheduled.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly int $attempt,
        private readonly string $reason,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The execution being retried.
     */
    public function executionId(): ExecutionId
    {
        return $this->executionId;
    }

    /**
     * The attempt number now being prepared.
     */
    public function attempt(): int
    {
        return $this->attempt;
    }

    /**
     * The human-readable reason for the retry.
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
        return 'runtime.execution_retried';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->executionId->toString();
    }
}
