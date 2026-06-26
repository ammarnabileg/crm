<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Contracts\Events\EventDispatcherInterface;
use App\Core\Logger;

/**
 * Synchronous in-process event dispatcher. Listeners run in registration order;
 * a listener that throws is logged and skipped so one failing side effect cannot
 * abort the others or the caller (docs/47 EAS-6 edge cases). Heavy listeners
 * should enqueue work rather than block (see the Queue system).
 */
final class EventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, array<int, callable>> */
    private array $listeners = [];

    public function __construct(private readonly Logger $logger)
    {
    }

    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function dispatch(object $event): object
    {
        foreach ($this->listenersFor($event::class) as $listener) {
            try {
                $listener($event);
            } catch (\Throwable $e) {
                $this->logger->error('Event listener failed', [
                    'event'     => $event::class,
                    'exception' => $e::class,
                    'message'   => $e->getMessage(),
                ]);
            }
        }

        return $event;
    }

    public function listenersFor(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }
}
