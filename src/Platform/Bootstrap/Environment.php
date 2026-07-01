<?php

declare(strict_types=1);

namespace Nizam\Platform\Bootstrap;

/**
 * The runtime environment the application is executing in.
 *
 * Drives environment-sensitive behaviour: whether to show detailed errors, enable debug tooling,
 * cache aggressively, and so on. Parsed from configuration/env at bootstrap via
 * {@see self::fromString()}, which is tolerant of common aliases ("prod", "dev", "test").
 */
enum Environment: string
{
    case Production = 'production';
    case Staging = 'staging';
    case Development = 'development';
    case Testing = 'testing';

    /**
     * Parse an environment from a (possibly abbreviated) string, defaulting to Production.
     *
     * Unknown values resolve to {@see self::Production} — the safest default, since it disables
     * debug output and developer conveniences.
     */
    public static function fromString(string $value): self
    {
        return match (strtolower(trim($value))) {
            'production', 'prod', 'live' => self::Production,
            'staging', 'stage' => self::Staging,
            'development', 'dev', 'local' => self::Development,
            'testing', 'test' => self::Testing,
            default => self::Production,
        };
    }

    /**
     * Whether this is the production environment.
     */
    public function isProduction(): bool
    {
        return $this === self::Production;
    }

    /**
     * Whether developer conveniences (verbose errors, no caching) should be enabled.
     */
    public function isDebugFriendly(): bool
    {
        return $this === self::Development || $this === self::Testing;
    }
}
