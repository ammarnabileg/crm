<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

/**
 * The lifecycle state of a plugin within the registry.
 *
 * A plugin is first {@see self::Discovered} from a source, then {@see self::Installed} once validated
 * and persisted, and {@see self::Enabled} when it may actively participate. It can be {@see self::Disabled}
 * without losing its installation, {@see self::Failed} when a lifecycle hook or health check errors,
 * {@see self::Incompatible} when it cannot run against the current platform, and {@see self::Uninstalled}
 * once retired. String-backed for stable persistence in the plugin registry.
 *
 * The legal transitions between states are enforced by {@see RegisteredPlugin}, not by this enum; the
 * predicates here describe *capabilities of a state* that the entity and services consult.
 */
enum PluginState: string
{
    /** Found by a source but not yet installed. */
    case Discovered = 'discovered';

    /** Validated and persisted; not yet active. */
    case Installed = 'installed';

    /** Active and permitted to participate. */
    case Enabled = 'enabled';

    /** Installed but deliberately switched off. */
    case Disabled = 'disabled';

    /** A lifecycle hook or health check errored. */
    case Failed = 'failed';

    /** Cannot run against the current platform version. */
    case Incompatible = 'incompatible';

    /** Retired and removed from active use. */
    case Uninstalled = 'uninstalled';

    /**
     * Whether a plugin in this state is currently active and may be invoked.
     */
    public function isActive(): bool
    {
        return $this === self::Enabled;
    }

    /**
     * Whether a plugin in this state is installed (present in the registry as a usable install).
     *
     * Failed and disabled plugins remain installed; discovered, incompatible and uninstalled do not.
     */
    public function isInstalled(): bool
    {
        return match ($this) {
            self::Installed, self::Enabled, self::Disabled, self::Failed => true,
            self::Discovered, self::Incompatible, self::Uninstalled => false,
        };
    }

    /**
     * Whether this is a terminal state from which no further transition is allowed.
     */
    public function isTerminal(): bool
    {
        return $this === self::Uninstalled;
    }
}
