<?php

declare(strict_types=1);

namespace Nizam\Platform\Event;

use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * The PSR-14 listener registry.
 *
 * Listeners are registered against an event class and matched to a dispatched event by that
 * class and its ancestors (parent classes and implemented interfaces), so a listener bound to a
 * base type also receives subtypes. Within a single event type, listeners are returned in
 * descending priority order; ties preserve registration order (stable).
 */
final class ListenerProvider implements ListenerProviderInterface
{
    /**
     * Registered listeners keyed by event class name.
     *
     * @var array<string, array<int, array{listener: callable, priority: int, seq: int}>>
     */
    private array $listeners = [];

    /**
     * Monotonic sequence used to keep sorting stable across equal priorities.
     */
    private int $sequence = 0;

    /**
     * Register a listener for an event class (and its subtypes).
     *
     * @param string   $eventClass The fully-qualified event class or interface name.
     * @param callable $listener    Receives the event object; may mutate it.
     * @param int      $priority    Higher runs earlier; defaults to 0.
     */
    public function listen(string $eventClass, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventClass][] = [
            'listener' => $listener,
            'priority' => $priority,
            'seq' => $this->sequence++,
        ];
    }

    /**
     * Register every handler declared by a {@see Subscriber}.
     */
    public function subscribe(Subscriber $subscriber): void
    {
        foreach ($subscriber::subscribedEvents() as $eventClass => $handler) {
            if (is_array($handler)) {
                [$method, $priority] = $handler;
            } else {
                $method = $handler;
                $priority = 0;
            }

            $this->listen($eventClass, [$subscriber, $method], $priority);
        }
    }

    /**
     * Yield the listeners applicable to the given event, highest priority first (PSR-14).
     *
     * @return iterable<int, callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $matched = [];

        foreach ($this->listeners as $eventClass => $entries) {
            if ($event instanceof $eventClass) {
                foreach ($entries as $entry) {
                    $matched[] = $entry;
                }
            }
        }

        usort($matched, static function (array $a, array $b): int {
            return $b['priority'] <=> $a['priority']
                ?: $a['seq'] <=> $b['seq'];
        });

        foreach ($matched as $entry) {
            yield $entry['listener'];
        }
    }
}
