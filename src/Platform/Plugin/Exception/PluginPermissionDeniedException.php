<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Exception;

/**
 * Raised when a plugin attempts an action for which it was not granted the required permission.
 *
 * The {@see \Nizam\Platform\Plugin\Service\PluginPermissionGate} enforces the isolation rule that a
 * plugin may only act within the permissions its tenant granted it: if a plugin needs a permission it
 * declared but was not granted, the gate denies the action with this exception. Carries error code
 * `PLUGIN.PERMISSION_DENIED`.
 */
final class PluginPermissionDeniedException extends PluginException
{
    /**
     * The stable error code for a denied permission.
     */
    public const string CODE = 'PLUGIN.PERMISSION_DENIED';

    /**
     * Build the exception naming the plugin and the permission it lacked.
     *
     * @param string $pluginName    The plugin that was denied.
     * @param string $permissionKey The permission key the plugin was not granted.
     */
    public static function forPermission(string $pluginName, string $permissionKey): self
    {
        return new self(
            self::CODE,
            sprintf('Plugin "%s" was not granted permission "%s".', $pluginName, $permissionKey),
        );
    }
}
