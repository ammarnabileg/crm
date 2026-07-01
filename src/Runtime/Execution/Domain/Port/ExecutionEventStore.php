<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Port;

use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Runtime\Execution\Domain\ExecutionId;

/**
 * The append-only event store for an execution's domain events.
 *
 * The domain declares this port; infrastructure adapters implement it. Every event an
 * {@see \Nizam\Runtime\Execution\Domain\Execution} records is appended, in order, keyed by the
 * execution id, and can later be streamed back in the exact order it was appended — the substrate
 * for {@see \Nizam\Runtime\Execution\Domain\Execution::replay()}, recovery, and audit. The store is
 * strictly append-only: events are never updated or deleted.
 */
interface ExecutionEventStore
{
    /**
     * Append a batch of events for an execution, preserving their order.
     *
     * @param list<DomainEvent> $events The events to append (may be empty, in which case this is a no-op).
     */
    public function append(ExecutionId $executionId, array $events): void;

    /**
     * Stream back every event recorded for an execution, in append order.
     *
     * @return list<DomainEvent>
     */
    public function stream(ExecutionId $executionId): array;
}
