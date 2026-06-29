<?php

declare(strict_types=1);

namespace HaHireAI\Core\Health\Probes;

use HaHireAI\Core\Contracts\HealthProbe;
use HaHireAI\Core\Health\HealthResult;

/** Verifies the runtime meets the minimum PHP version. */
final class PhpVersionProbe implements HealthProbe
{
    public function __construct(private readonly string $minimum = '8.3.0')
    {
    }

    public function name(): string
    {
        return 'php.version';
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function check(): HealthResult
    {
        $current = PHP_VERSION;

        return version_compare($current, $this->minimum, '>=')
            ? HealthResult::ok("PHP {$current}")
            : HealthResult::unhealthy("PHP {$current} < required {$this->minimum}");
    }
}
