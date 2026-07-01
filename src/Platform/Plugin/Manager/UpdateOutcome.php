<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Manager;

use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\RegisteredPlugin;

/**
 * The result of updating a plugin, retaining the prior version for rollback.
 *
 * When {@see PluginInstaller::update()} moves a plugin to a strictly newer version it captures the
 * manifest and source it replaced, so the update can be reversed. This value pairs the freshly-updated
 * {@see RegisteredPlugin} with that superseded {@see PluginManifest} and source locator; the installer's
 * {@see PluginInstaller::rollback()} consumes it to restore the previous version. It is a pure carrier of
 * the two ends of an update and performs no I/O.
 */
final class UpdateOutcome
{
    /**
     * @param RegisteredPlugin $plugin           The plugin as it now stands, at the newer version.
     * @param PluginManifest   $previousManifest The manifest that was in force before the update.
     * @param string           $previousSource   The source locator the previous version came from.
     */
    public function __construct(
        private readonly RegisteredPlugin $plugin,
        private readonly PluginManifest $previousManifest,
        private readonly string $previousSource,
    ) {
    }

    /**
     * The plugin as it now stands, at the newer version.
     */
    public function plugin(): RegisteredPlugin
    {
        return $this->plugin;
    }

    /**
     * The manifest that was in force before the update.
     */
    public function previousManifest(): PluginManifest
    {
        return $this->previousManifest;
    }

    /**
     * The version string of the manifest that was in force before the update.
     */
    public function previousVersion(): string
    {
        return (string) $this->previousManifest->version();
    }

    /**
     * The source locator the previous version came from.
     */
    public function previousSource(): string
    {
        return $this->previousSource;
    }
}
