<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Persistence\InMemory;

use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventStore;

/**
 * A real, single-process, append-only {@see ExecutionEventStore} for tests and safe defaults.
 *
 * It appends each execution's recorded {@see DomainEvent}s, in order, to an in-memory list keyed by
 * {@see ExecutionId}, and streams them back in the exact order they were appended — the substrate for
 * {@see \Nizam\Runtime\Execution\Domain\Execution::replay()}, recovery, and audit. The store is strictly
 * append-only: no operation updates or deletes a recorded event. It is a working store, not a stub — the
 * durable equivalent is {@see \Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionEventStore}.
 */
final class InMemoryExecutionEventStore implements ExecutionEventStore
{
    /** @var array<string, list<DomainEvent>> */
    private array $events = [];

    /**
     * {@inheritDoc}
     */
    public function append(ExecutionId $executionId, array $events): void
    {
        if ($events === []) {
            return;
        }

        $key = $executionId->toString();
        $this->events[$key] = [...($this->events[$key] ?? []), ...array_values($events)];
    }

    /**
     * {@inheritDoc}
     */
    public function stream(ExecutionId $executionId): array
    {
        return $this->events[$executionId->toString()] ?? [];
    }
}
