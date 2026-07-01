<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Exception;

/**
 * Raised when a plugin cannot run against the current platform version.
 *
 * A manifest declares a `platformConstraint` — the range of platform versions it supports. When the
 * running platform version falls outside that range, installation or enablement is refused and the
 * plugin is marked {@see \Nizam\Platform\Plugin\PluginState::Incompatible}. Carries error code
 * `PLUGIN.INCOMPATIBLE`.
 */
final class IncompatiblePluginException extends PluginException
{
    /**
     * The stable error code for a platform-incompatible plugin.
     */
    public const string CODE = 'PLUGIN.INCOMPATIBLE';

    /**
     * Build the exception describing the plugin, its constraint and the running platform version.
     *
     * @param string $pluginName      The incompatible plugin.
     * @param string $constraint      The platform constraint the manifest declared.
     * @param string $platformVersion The running platform version that fell outside the constraint.
     */
    public static function forPlatform(string $pluginName, string $constraint, string $platformVersion): self
    {
        return new self(
            self::CODE,
            sprintf(
                'Plugin "%s" requires platform %s but the platform is %s.',
                $pluginName,
                $constraint,
                $platformVersion,
            ),
        );
    }
}
