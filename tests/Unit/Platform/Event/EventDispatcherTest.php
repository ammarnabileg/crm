<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Event;

use Nizam\Platform\Event\EventDispatcher;
use Nizam\Platform\Event\ListenerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\StoppableEventInterface;

#[CoversClass(EventDispatcher::class)]
#[CoversClass(ListenerProvider::class)]
final class EventDispatcherTest extends TestCase
{
    public function testDispatchReturnsSameEventObject(): void
    {
        $provider = new ListenerProvider();
        $dispatcher = new EventDispatcher($provider);
        $event = new SampleEvent();

        self::assertSame($event, $dispatcher->dispatch($event));
    }

    public function testListenersRunInDescendingPriorityOrder(): void
    {
        $provider = new ListenerProvider();
        $provider->listen(SampleEvent::class, static fn (SampleEvent $e) => $e->log[] = 'low', -10);
        $provider->listen(SampleEvent::class, static fn (SampleEvent $e) => $e->log[] = 'high', 100);
        $provider->listen(SampleEvent::class, static fn (SampleEvent $e) => $e->log[] = 'mid', 0);

        $event = new SampleEvent();
        (new EventDispatcher($provider))->dispatch($event);

        self::assertSame(['high', 'mid', 'low'], $event->log);
    }

    public function testEqualPrioritiesPreserveRegistrationOrder(): void
    {
        $provider = new ListenerProvider();
        $provider->listen(SampleEvent::class, static fn (SampleEvent $e) => $e->log[] = 'first');
        $provider->listen(SampleEvent::class, static fn (SampleEvent $e) => $e->log[] = 'second');

        $event = new SampleEvent();
        (new EventDispatcher($provider))->dispatch($event);

        self::assertSame(['first', 'second'], $event->log);
    }

    public function testStoppableEventHaltsPropagation(): void
    {
        $provider = new ListenerProvider();
        $provider->listen(StoppableSampleEvent::class, static function (StoppableSampleEvent $e): void {
            $e->log[] = 'a';
            $e->stop();
        }, 10);
        $provider->listen(StoppableSampleEvent::class, static function (StoppableSampleEvent $e): void {
            $e->log[] = 'b';
        }, 0);

        $event = new StoppableSampleEvent();
        (new EventDispatcher($provider))->dispatch($event);

        self::assertSame(['a'], $event->log);
    }

    public function testListenerBoundToParentTypeReceivesSubtype(): void
    {
        $provider = new ListenerProvider();
        $provider->listen(SampleEvent::class, static fn (SampleEvent $e) => $e->log[] = 'parent');

        $event = new ChildEvent();
        (new EventDispatcher($provider))->dispatch($event);

        self::assertSame(['parent'], $event->log);
    }
}

class SampleEvent
{
    /** @var array<int, string> */
    public array $log = [];
}

final class ChildEvent extends SampleEvent
{
}

final class StoppableSampleEvent implements StoppableEventInterface
{
    /** @var array<int, string> */
    public array $log = [];

    private bool $stopped = false;

    public function stop(): void
    {
        $this->stopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}
