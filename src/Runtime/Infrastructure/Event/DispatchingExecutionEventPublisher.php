<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Event;

use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Platform\Event\EventDispatcher;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventPublisher;

/**
 * The Infrastructure adapter that bridges an execution's recorded events onto the platform event pipeline.
 *
 * The {@see \Nizam\Runtime\Execution\Application\ExecutionEngine} pulls the events an
 * {@see \Nizam\Runtime\Execution\Domain\Execution} recorded after each attempt commits and hands them to
 * the {@see ExecutionEventPublisher} port. This adapter implements that port by dispatching each event,
 * in the order it was recorded, through the PSR-14 {@see EventDispatcher} — so listeners (automation,
 * notifications, projections) react only after the work is durable, and the domain and application
 * layers stay free of any dispatch machinery.
 */
final class DispatchingExecutionEventPublisher implements ExecutionEventPublisher
{
    /**
     * @param EventDispatcher $dispatcher The platform PSR-14 dispatcher events are handed to.
     */
    public function __construct(private readonly EventDispatcher $dispatcher)
    {
    }

    /**
     * Publish a batch of recorded domain events, in order, through the platform dispatcher.
     *
     * @param list<DomainEvent> $events The events to publish (may be empty, in which case this is a no-op).
     */
    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
