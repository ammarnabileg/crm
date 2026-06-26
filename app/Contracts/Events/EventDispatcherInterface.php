<?php

declare(strict_types=1);

namespace App\Contracts\Events;

/**
 * Event dispatcher contract. Important actions emit domain events; side effects
 * (audit, notifications, search indexing) are listeners, not inline code
 * (docs/47 EAS-6). Application code depends on this interface.
 */
interface EventDispatcherInterface
{
    /**
     * Register a listener for an event class.
     *
     * @param class-string $event
     * @param callable(object):void $listener
     */
    public function listen(string $event, callable $listener): void;

    /**
     * Dispatch an event to all registered listeners and return it.
     */
    public function dispatch(object $event): object;

    /**
     * @param class-string $event
     * @return array<int,callable> Listeners registered for the event.
     */
    public function listenersFor(string $event): array;
}
