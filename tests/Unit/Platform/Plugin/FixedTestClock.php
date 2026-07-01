<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin;

use DateTimeImmutable;
use Nizam\Kernel\Domain\Clock;

/**
 * A controllable {@see Clock} for deterministic Plugin Platform tests.
 *
 * Plugin aggregate mutators stamp their domain events with `$clock->now()`. A real clock would make
 * those timestamps non-deterministic and could collapse two rapid transitions onto the same instant.
 * This test double returns a fixed instant that tests may advance explicitly, so timestamp and
 * ordering assertions stay stable and reproducible.
 */
final class FixedTestClock implements Clock
{
    /**
     * @param DateTimeImmutable $now The current instant the clock reports.
     */
    public function __construct(
        private DateTimeImmutable $now = new DateTimeImmutable('2026-07-01T12:00:00+00:00'),
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
     * Advance the clock by a number of seconds and return the new instant.
     */
    public function advance(int $seconds): DateTimeImmutable
    {
        $this->now = $this->now->modify(sprintf('+%d seconds', $seconds));

        return $this->now;
    }
}
