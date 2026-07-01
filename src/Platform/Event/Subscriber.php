<?php

declare(strict_types=1);

namespace Nizam\Platform\Event;

/**
 * A class that declares, in one place, the events it listens to and the methods that handle them.
 *
 * Subscribers are a convenience over registering listeners one by one on the
 * {@see ListenerProvider}: the provider can read {@see self::subscribedEvents()} and wire every
 * declared handler at once.
 */
interface Subscriber
{
    /**
     * A map of event class name to handler specification.
     *
     * Each value is either:
     *   - a method name (string) on this subscriber, or
     *   - a `[methodName, priority]` pair where higher priority runs earlier.
     *
     * @return array<class-string, string|array{0: string, 1: int}>
     */
    public static function subscribedEvents(): array;
}
