<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Exception;

/**
 * Raised when a plugin cannot be found in the registry.
 *
 * Thrown by the manager and lifecycle services when an operation references a plugin by name or id
 * that has no matching registered install. Carries error code `PLUGIN.NOT_FOUND`.
 */
final class PluginNotFoundException extends PluginException
{
    /**
     * The stable error code for a missing plugin.
     */
    public const string CODE = 'PLUGIN.NOT_FOUND';

    /**
     * No registered plugin has the given manifest name.
     */
    public static function withName(string $name): self
    {
        return new self(self::CODE, sprintf('No registered plugin named "%s".', $name));
    }

    /**
     * No registered plugin has the given identity.
     */
    public static function withId(string $id): self
    {
        return new self(self::CODE, sprintf('No registered plugin with id "%s".', $id));
    }
}
