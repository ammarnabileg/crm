<?php

declare(strict_types=1);

namespace Nizam\Platform\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * The PSR-14 event dispatcher.
 *
 * Pulls listeners for a dispatched event from a {@see ListenerProviderInterface} and calls each in
 * turn, passing the same event object so listeners can inspect and mutate it. If the event
 * implements {@see StoppableEventInterface}, propagation halts as soon as the event reports it has
 * been stopped. The (possibly mutated) event is returned to the caller.
 */
final class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(private readonly ListenerProviderInterface $listeners)
    {
    }

    /**
     * Dispatch an event to all applicable listeners and return it.
     */
    public function dispatch(object $event): object
    {
        $stoppable = $event instanceof StoppableEventInterface;

        if ($stoppable && $event->isPropagationStopped()) {
            return $event;
        }

        foreach ($this->listeners->getListenersForEvent($event) as $listener) {
            $listener($event);

            if ($stoppable && $event->isPropagationStopped()) {
                break;
            }
        }

        return $event;
    }
}
