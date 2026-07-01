<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Infrastructure\Source;

use Nizam\Platform\Plugin\Port\DiscoveredPlugin;
use Nizam\Platform\Plugin\Port\PluginSource;
use Nizam\Platform\Plugin\PluginManifest;

/**
 * A {@see PluginSource} that exposes an explicit, in-memory set of manifests.
 *
 * Used by tests and by programmatic registration: callers hand it manifests (each with an optional
 * locator) and it reports them verbatim as {@see DiscoveredPlugin}s. When no locator is given for a
 * manifest, a stable `array:<name>@<version>` locator is synthesised so installs recorded from this
 * source still carry a meaningful origin. It performs no I/O.
 */
final class ArrayPluginSource implements PluginSource
{
    /**
     * @var list<DiscoveredPlugin> The discovered plugins this source exposes.
     */
    private array $discovered;

    /**
     * @param list<DiscoveredPlugin> $discovered The discovered plugins to expose.
     */
    public function __construct(array $discovered = [])
    {
        $this->discovered = array_values($discovered);
    }

    /**
     * Build a source from bare manifests, synthesising a stable locator for each.
     *
     * @param list<PluginManifest> $manifests The manifests to expose.
     */
    public static function fromManifests(array $manifests): self
    {
        $discovered = [];
        foreach ($manifests as $manifest) {
            $discovered[] = new DiscoveredPlugin(
                $manifest,
                sprintf('array:%s@%s', $manifest->name(), (string) $manifest->version()),
            );
        }

        return new self($discovered);
    }

    /**
     * Add a manifest to the source, addressed by an explicit or synthesised locator.
     */
    public function add(PluginManifest $manifest, ?string $locator = null): void
    {
        $this->discovered[] = new DiscoveredPlugin(
            $manifest,
            $locator ?? sprintf('array:%s@%s', $manifest->name(), (string) $manifest->version()),
        );
    }

    /**
     * Discover the plugins this source exposes.
     *
     * @return list<DiscoveredPlugin>
     */
    public function discover(): array
    {
        return $this->discovered;
    }
}
