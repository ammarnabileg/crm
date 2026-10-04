<?php

declare(strict_types=1);

namespace HaHireAI\Core\Health;

/** Overall and per-probe health status. See docs/HEALTH_CHECK_SYSTEM.md. */
enum HealthStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unhealthy = 'unhealthy';

    public function rank(): int
    {
        return match ($this) {
            self::Healthy => 0,
            self::Degraded => 1,
            self::Unhealthy => 2,
        };
    }
}
