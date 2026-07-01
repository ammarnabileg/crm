<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Service;

use Nizam\Platform\Plugin\Exception\PluginPermissionDeniedException;
use Nizam\Platform\Plugin\PermissionSet;
use Nizam\Platform\Plugin\RegisteredPlugin;

/**
 * Enforces that a plugin acts only within the permissions its tenant granted it.
 *
 * The gate is the second half of the isolation model (the first being that a plugin only ever sees the
 * capabilities handed to it in its {@see \Nizam\Platform\Plugin\PluginContext}). Before the platform
 * lets a plugin perform a permission-guarded action, it asks the gate to authorize the action's
 * permission key against the set the tenant granted. If the key is not in the granted set, the gate
 * refuses with a {@see PluginPermissionDeniedException}; otherwise it returns silently. The gate makes
 * no reference to what the plugin's manifest *declared* it needs — a grant is authoritative, so a
 * plugin cannot act on a permission merely because it asked for it, only because it was granted it.
 */
final class PluginPermissionGate
{
    /**
     * Authorize a permission-guarded action, or refuse it.
     *
     * @param RegisteredPlugin $plugin        The plugin attempting the action.
     * @param string           $permissionKey The permission key the action requires.
     * @param PermissionSet    $granted       The set of permissions the tenant granted the plugin.
     *
     * @throws PluginPermissionDeniedException When the granted set does not contain the required key.
     */
    public function authorize(RegisteredPlugin $plugin, string $permissionKey, PermissionSet $granted): void
    {
        if (!$granted->has($permissionKey)) {
            throw PluginPermissionDeniedException::forPermission($plugin->name(), $permissionKey);
        }
    }

    /**
     * Whether the granted set would authorize the given permission, without throwing.
     *
     * A non-throwing companion to {@see self::authorize()} for callers that want to branch on the
     * decision rather than guard with a try/catch.
     *
     * @param string        $permissionKey The permission key the action requires.
     * @param PermissionSet $granted       The set of permissions the tenant granted the plugin.
     */
    public function allows(string $permissionKey, PermissionSet $granted): bool
    {
        return $granted->has($permissionKey);
    }
}
