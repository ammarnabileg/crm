<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionMetadata;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;

/**
 * Recorded when a new execution is started and enters {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Pending}.
 *
 * Marks the birth of an execution. Emitted by {@see \Nizam\Runtime\Execution\Domain\Execution::start()}.
 * Carries the metadata and policies the execution was created with so a replay can reconstruct it
 * faithfully — the retry and timeout policies are configuration, not derived from later events, so
 * they must travel on the birth event to survive event-sourced reconstitution.
 */
final class ExecutionStarted implements DomainEvent
{
    /**
     * @param ExecutionId       $executionId   The started execution.
     * @param ExecutionMetadata $metadata      The metadata the execution was created with.
     * @param RetryPolicy       $retryPolicy   The retry rules the execution was created with.
     * @param TimeoutPolicy     $timeoutPolicy The time budget the execution was created with.
     * @param DateTimeImmutable $occurredAt    When the execution started.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly ExecutionMetadata $metadata,
        private readonly RetryPolicy $retryPolicy,
        private readonly TimeoutPolicy $timeoutPolicy,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The retry rules the execution was created with.
     */
    public function retryPolicy(): RetryPolicy
    {
        return $this->retryPolicy;
    }

    /**
     * The time budget the execution was created with.
     */
    public function timeoutPolicy(): TimeoutPolicy
    {
        return $this->timeoutPolicy;
    }

    /**
     * The started execution.
     */
    public function executionId(): ExecutionId
    {
        return $this->executionId;
    }

    /**
     * The metadata the execution was created with.
     */
    public function metadata(): ExecutionMetadata
    {
        return $this->metadata;
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
        return 'runtime.execution_started';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->executionId->toString();
    }
}
