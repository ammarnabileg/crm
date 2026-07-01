<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin;

use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Platform\Plugin\Port\PluginEventPublisher;

/**
 * A {@see PluginEventPublisher} test double that records every published event in order.
 *
 * Services and the lifecycle manager publish pulled domain events through this port; tests substitute
 * this collector so they can assert precisely which events were emitted, in which order, without any
 * dispatch machinery. The recorded events are exposed both as the flat published list and filtered by
 * concrete event class for targeted assertions.
 */
final class CollectingEventPublisher implements PluginEventPublisher
{
    /**
     * @var list<DomainEvent> Every event published, in publication order.
     */
    private array $events = [];

    /**
     * Record a batch of published events, in order.
     *
     * @param list<DomainEvent> $events The events to record.
     */
    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->events[] = $event;
        }
    }

    /**
     * Every event recorded, in publication order.
     *
     * @return list<DomainEvent>
     */
    public function all(): array
    {
        return $this->events;
    }

    /**
     * The recorded events that are instances of the given concrete event class.
     *
     * @template T of DomainEvent
     *
     * @param class-string<T> $eventClass The event class to filter by.
     *
     * @return list<T>
     */
    public function ofType(string $eventClass): array
    {
        $matches = [];
        foreach ($this->events as $event) {
            if ($event instanceof $eventClass) {
                $matches[] = $event;
            }
        }

        return $matches;
    }

    /**
     * The stable event names recorded, in publication order.
     *
     * @return list<string>
     */
    public function eventNames(): array
    {
        return array_map(static fn (DomainEvent $event): string => $event->eventName(), $this->events);
    }

    /**
     * Whether any recorded event is an instance of the given class.
     *
     * @param class-string<DomainEvent> $eventClass The event class to look for.
     */
    public function has(string $eventClass): bool
    {
        return $this->ofType($eventClass) !== [];
    }

    /**
     * Forget every recorded event.
     */
    public function reset(): void
    {
        $this->events = [];
    }
}
