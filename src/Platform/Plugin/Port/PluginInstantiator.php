<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Port;

use Nizam\Platform\Plugin\PluginInterface;
use Nizam\Platform\Plugin\PluginManifest;

/**
 * The port that resolves a plugin's entry-point class into a live {@see PluginInterface} instance.
 *
 * The platform never instantiates a plugin's entry-point class directly. It hands the manifest to an
 * instantiator, whose Infrastructure adapter resolves the `entryPointClass` (typically via the
 * container, autowiring its dependencies) and returns the constructed plugin — isolating any
 * construction failure behind the port so a broken plugin cannot crash the Core. This keeps the domain
 * free of container and reflection concerns.
 */
interface PluginInstantiator
{
    /**
     * Construct the plugin described by the manifest.
     *
     * @param PluginManifest $manifest The manifest whose `entryPointClass` is to be instantiated.
     *
     * @return PluginInterface The constructed plugin instance.
     */
    public function make(PluginManifest $manifest): PluginInterface;
}
