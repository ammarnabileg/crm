<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * In-process event dispatcher contract. The async/external bus (Phase 13)
 * subscribes through the same mechanism. See docs/ARCHITECTURE.md §4.
 */
interface EventDispatcher
{
    /** Register a listener for a named event. */
    public function listen(string $event, callable $listener): void;

    /** True if any listener is registered for the event. */
    public function hasListeners(string $event): bool;

    /**
     * Dispatch a named event with an optional payload.
     *
     * @return list<mixed> the listeners' return values, in order
     */
    public function dispatch(string $event, mixed $payload = null): array;
}
