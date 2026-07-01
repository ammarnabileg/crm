<?php

declare(strict_types=1);

namespace Nizam\Platform\Support;

use DateTimeImmutable;
use DateTimeZone;
use Nizam\Kernel\Domain\Clock;

/**
 * The production {@see Clock}: reads the real wall-clock time.
 *
 * All domain and application code depends on the {@see Clock} port rather than calling
 * {@see \time()} or `new DateTimeImmutable()` directly, so time can be frozen or faked in tests.
 * This adapter is the only place that touches the actual system clock.
 */
final class SystemClock implements Clock
{
    private readonly DateTimeZone $timezone;

    /**
     * @param string $timezone IANA timezone name the returned instants are expressed in (UTC by default).
     */
    public function __construct(string $timezone = 'UTC')
    {
        $this->timezone = new DateTimeZone($timezone);
    }

    /**
     * The current instant, to microsecond precision, in the configured timezone.
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
