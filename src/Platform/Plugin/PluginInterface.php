<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

/**
 * The base SDK contract every plugin implements.
 *
 * A plugin is any class the platform can discover, validate, install and invoke without the Core
 * knowing its concrete type. The single obligation of the base contract is to expose the plugin's
 * {@see PluginManifest}: the platform reads the manifest to learn the plugin's identity, version,
 * kind, dependencies and required permissions, and then treats the plugin only through the more
 * specific kind contract its manifest declares (see the `Contract/` namespace). Concrete plugins
 * should not perform I/O in their constructor; construction is isolated by the platform's
 * instantiator so a broken plugin cannot take down the Core.
 */
interface PluginInterface
{
    /**
     * The plugin's published descriptor.
     *
     * Must return a stable manifest whose `name`, `version` and `kind` do not change across calls for
     * a given plugin build.
     */
    public function manifest(): PluginManifest;
}
