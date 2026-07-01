<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Port;

/**
 * The port through which the platform discovers available plugins.
 *
 * A hexagonal port: the discovery service depends on this contract, and Infrastructure adapters
 * implement it — a directory scanner reading `plugin.json` files, an in-memory array of manifests for
 * tests and programmatic registration, and so on. A source is read-only and side-effect free from the
 * caller's perspective: it reports what plugins it can see, and the platform decides what to do with
 * them.
 */
interface PluginSource
{
    /**
     * Discover the plugins this source currently exposes.
     *
     * @return list<DiscoveredPlugin>
     */
    public function discover(): array;
}
