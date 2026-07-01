<?php

declare(strict_types=1);

namespace Nizam\Behavior\Infrastructure\Event;

use Nizam\Behavior\Domain\Port\BehaviorEventPublisher;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Platform\Event\EventDispatcher;

/**
 * The Infrastructure adapter that bridges pulled domain events onto the platform event pipeline.
 *
 * Aggregates buffer {@see DomainEvent}s as they change; the application layer pulls them after a unit
 * of work and hands them to the {@see BehaviorEventPublisher} port. This adapter implements that port
 * by dispatching each event, in the order it was recorded, through the PSR-14
 * {@see EventDispatcher} — keeping the domain and application layers free of any dispatch machinery.
 */
final class DispatchingBehaviorEventPublisher implements BehaviorEventPublisher
{
    /**
     * @param EventDispatcher $dispatcher The platform PSR-14 dispatcher events are handed to.
     */
    public function __construct(private readonly EventDispatcher $dispatcher)
    {
    }

    /**
     * Publish a batch of domain events, in order, through the platform dispatcher.
     *
     * @param list<DomainEvent> $events The events to publish.
     */
    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
