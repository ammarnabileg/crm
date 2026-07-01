<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Port;

use Nizam\Kernel\Domain\DomainEvent;

/**
 * The port through which recorded execution events are announced to the rest of the platform.
 *
 * The domain declares this port; an infrastructure adapter dispatches to the platform's
 * {@see \Nizam\Platform\Event\EventDispatcher}. After a unit of work commits, the application layer
 * pulls the events an {@see \Nizam\Runtime\Execution\Domain\Execution} recorded and hands them here
 * for publication so listeners (automation, notifications, projections) can react — always after the
 * fact, never inside the aggregate.
 */
interface ExecutionEventPublisher
{
    /**
     * Publish a batch of recorded domain events, in order.
     *
     * @param list<DomainEvent> $events The events to publish (may be empty, in which case this is a no-op).
     */
    public function publish(array $events): void;
}
