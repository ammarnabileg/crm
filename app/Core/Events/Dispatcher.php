<?php

declare(strict_types=1);

namespace HaHireAI\Core\Events;

use HaHireAI\Core\Contracts\EventDispatcher;

/**
 * Simple in-process event dispatcher. Listeners run synchronously in
 * registration order. Queue-backed async delivery is added in a later phase.
 * See docs/ARCHITECTURE.md §4, docs/EVENT_BUS.md.
 */
final class Dispatcher implements EventDispatcher
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function hasListeners(string $event): bool
    {
        return ! empty($this->listeners[$event]);
    }

    public function dispatch(string $event, mixed $payload = null): array
    {
        $results = [];

        foreach ($this->listeners[$event] ?? [] as $listener) {
            $results[] = $listener($payload, $event);
        }

        return $results;
    }
}
