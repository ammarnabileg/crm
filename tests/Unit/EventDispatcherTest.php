<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Events\EventDispatcher;
use Tests\TestCase;

final class SampleEvent
{
    public function __construct(public int $value)
    {
    }
}

/**
 * Event dispatcher: registration, ordered invocation, return value, and
 * fault isolation (a throwing listener must not break the others) — docs/47 EAS-6.
 */
return new class extends TestCase {
    private function dispatcher(): EventDispatcher
    {
        return new EventDispatcher(app('log'));
    }

    public function test_listener_receives_event(): void
    {
        $d = $this->dispatcher();
        $seen = null;
        $d->listen(SampleEvent::class, function (SampleEvent $e) use (&$seen) {
            $seen = $e->value;
        });
        $d->dispatch(new SampleEvent(42));
        $this->assertSame(42, $seen);
    }

    public function test_multiple_listeners_run_in_order(): void
    {
        $d = $this->dispatcher();
        $log = [];
        $d->listen(SampleEvent::class, function () use (&$log) {
            $log[] = 'a';
        });
        $d->listen(SampleEvent::class, function () use (&$log) {
            $log[] = 'b';
        });
        $d->dispatch(new SampleEvent(1));
        $this->assertSame(['a', 'b'], $log);
    }

    public function test_dispatch_returns_the_event(): void
    {
        $d = $this->dispatcher();
        $event = new SampleEvent(7);
        $this->assertTrue($d->dispatch($event) === $event);
    }

    public function test_throwing_listener_is_isolated(): void
    {
        $d = $this->dispatcher();
        $ran = false;
        $d->listen(SampleEvent::class, function () {
            throw new \RuntimeException('boom');
        });
        $d->listen(SampleEvent::class, function () use (&$ran) {
            $ran = true;
        });
        // Should not throw; the second listener still runs.
        $d->dispatch(new SampleEvent(1));
        $this->assertTrue($ran);
    }

    public function test_no_listeners_is_a_noop(): void
    {
        $d = $this->dispatcher();
        $this->assertCount(0, $d->listenersFor(SampleEvent::class));
        $d->dispatch(new SampleEvent(1)); // no error
        $this->assertTrue(true);
    }
};
