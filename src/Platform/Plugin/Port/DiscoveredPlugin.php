<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Port;

use Nizam\Platform\Plugin\PluginManifest;

/**
 * A plugin found by a {@see PluginSource} but not yet installed.
 *
 * Pairs a plugin's {@see PluginManifest} with the opaque source locator it was discovered at (a
 * directory path, a package coordinate, an array key — whatever the concrete source uses to address
 * it). The installer consumes discovered plugins: it validates the manifest, resolves dependencies,
 * and records the locator as the installed plugin's `source`. This is a pure value object with no I/O.
 */
final class DiscoveredPlugin
{
    /**
     * @param PluginManifest $manifest The discovered plugin's descriptor.
     * @param string         $locator  The opaque source locator addressing the plugin.
     */
    public function __construct(
        private readonly PluginManifest $manifest,
        private readonly string $locator,
    ) {
    }

    /**
     * The discovered plugin's descriptor.
     */
    public function manifest(): PluginManifest
    {
        return $this->manifest;
    }

    /**
     * The opaque source locator addressing the plugin.
     */
    public function locator(): string
    {
        return $this->locator;
    }

    /**
     * The discovered plugin's manifest name.
     */
    public function name(): string
    {
        return $this->manifest->name();
    }

    /**
     * A stable key uniquely identifying this name+version pair, for de-duplication.
     */
    public function identityKey(): string
    {
        return $this->manifest->name() . '@' . (string) $this->manifest->version();
    }
}
