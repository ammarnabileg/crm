<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

/**
 * The health level a plugin reports from a health check.
 *
 * A plugin is {@see self::Healthy} when fully operational, {@see self::Degraded} when working but
 * impaired (e.g. a slow or partially-available dependency), and {@see self::Unhealthy} when it cannot
 * perform its function. The platform uses the level to decide whether to keep routing work to a plugin
 * and whether to surface a warning in the marketplace. String-backed for stable persistence in the
 * health table.
 */
enum HealthLevel: string
{
    /** Fully operational. */
    case Healthy = 'healthy';

    /** Working but impaired. */
    case Degraded = 'degraded';

    /** Not operational. */
    case Unhealthy = 'unhealthy';

    /**
     * Whether a plugin at this level may still be routed work.
     *
     * Healthy and degraded plugins remain usable; an unhealthy plugin does not.
     */
    public function isUsable(): bool
    {
        return $this !== self::Unhealthy;
    }
}
