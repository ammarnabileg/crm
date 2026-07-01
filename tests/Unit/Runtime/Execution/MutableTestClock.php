<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Runtime\Execution;

use DateTimeImmutable;
use Nizam\Kernel\Domain\Clock;

/**
 * A controllable {@see Clock} for deterministic Runtime execution tests.
 *
 * The execution aggregate and its application services stamp every event, timeline entry, and elapsed
 * measurement with {@see Clock::now()}. A real clock would make timestamps non-deterministic and could
 * collapse two rapid mutations onto the same instant. This double returns a fixed instant that a test
 * advances explicitly (by seconds or milliseconds), so ordering, duration, and timeout assertions are
 * stable and reproducible.
 */
final class MutableTestClock implements Clock
{
    /**
     * @param DateTimeImmutable $now The current instant the clock reports.
     */
    public function __construct(
        private DateTimeImmutable $now = new DateTimeImmutable('2026-06-01T12:00:00.000000+00:00'),
    ) {
    }

    /**
     * The current instant.
     */
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    /**
     * Advance the clock by whole seconds and return the new instant.
     */
    public function advance(int $seconds): DateTimeImmutable
    {
        $this->now = $this->now->modify(sprintf('+%d seconds', $seconds));

        return $this->now;
    }

    /**
     * Advance the clock by a number of milliseconds and return the new instant.
     */
    public function advanceMs(int $milliseconds): DateTimeImmutable
    {
        $this->now = $this->now->modify(sprintf('+%d microseconds', $milliseconds * 1000));

        return $this->now;
    }
}
