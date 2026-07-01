<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;

/**
 * Recorded when work steps are assigned to an execution, moving it into
 * {@see \Nizam\Runtime\Execution\Domain\ExecutionState::Assigned}.
 *
 * Carries the ids and worker references of the assigned steps so a replay can rebuild the step list.
 * Emitted by {@see \Nizam\Runtime\Execution\Domain\Execution::assign()}.
 */
final class WorkTaskAssigned implements DomainEvent
{
    /** @var list<array{stepId: string, name: string, workerRef: string}> */
    private readonly array $steps;

    /**
     * @param ExecutionId                                                     $executionId The execution.
     * @param list<array{stepId: string, name: string, workerRef: string}>    $steps       The assigned steps.
     * @param DateTimeImmutable                                               $occurredAt  When steps were assigned.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        array $steps,
        private readonly DateTimeImmutable $occurredAt,
    ) {
        $this->steps = array_values($steps);
    }

    /**
     * The execution the steps were assigned to.
     */
    public function executionId(): ExecutionId
    {
        return $this->executionId;
    }

    /**
     * The assigned steps as scalar descriptors.
     *
     * @return list<array{stepId: string, name: string, workerRef: string}>
     */
    public function steps(): array
    {
        return $this->steps;
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
        return 'runtime.work_task_assigned';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->executionId->toString();
    }
}
