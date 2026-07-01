<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Infrastructure\Event;

use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Platform\Event\EventDispatcher;
use Nizam\Platform\Plugin\Port\PluginEventPublisher;

/**
 * The Infrastructure adapter that bridges pulled plugin domain events onto the platform event pipeline.
 *
 * {@see \Nizam\Platform\Plugin\RegisteredPlugin} aggregates buffer {@see DomainEvent}s as they change;
 * the application layer pulls them after a unit of work and hands them to the
 * {@see PluginEventPublisher} port. This adapter implements that port by dispatching each event, in
 * the order it was recorded, through the PSR-14 {@see EventDispatcher} — keeping the domain and
 * application layers free of any dispatch machinery.
 */
final class DispatchingPluginEventPublisher implements PluginEventPublisher
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
