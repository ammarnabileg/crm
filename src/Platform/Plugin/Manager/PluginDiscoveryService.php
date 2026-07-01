<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Manager;

use Nizam\Kernel\Domain\Clock;
use Nizam\Platform\Plugin\Event\PluginDiscovered;
use Nizam\Platform\Plugin\Port\DiscoveredPlugin;
use Nizam\Platform\Plugin\Port\PluginEventPublisher;
use Nizam\Platform\Plugin\Port\PluginSource;

/**
 * Discovers the plugins visible across a set of sources, de-duplicated and announced.
 *
 * Given any number of {@see PluginSource} adapters — a directory scanner, an in-memory array, a package
 * index — the service asks each what plugins it can see and merges the results into a single de-duplicated
 * list. De-duplication is by plugin *name and version* (a plugin found at the same version from two
 * sources is reported once; the first source to expose it wins, so ordering is stable), which lets the
 * same plugin legitimately appear at different versions across sources. For every distinct discovery it
 * records a {@see PluginDiscovered} domain event and publishes the batch through the
 * {@see PluginEventPublisher} port, so where plugins originate is observable and auditable. Discovery is
 * read-only: it never installs, validates or mutates anything.
 */
final class PluginDiscoveryService
{
    /**
     * @param PluginEventPublisher $publisher The egress for {@see PluginDiscovered} events.
     * @param Clock                $clock     The time source stamping each discovery event.
     */
    public function __construct(
        private readonly PluginEventPublisher $publisher,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Discover the plugins exposed by the given sources, de-duplicated by name and version.
     *
     * @param list<PluginSource> $sources The sources to scan, in priority order.
     *
     * @return list<DiscoveredPlugin> The distinct discovered plugins, first-source-wins.
     */
    public function discover(array $sources): array
    {
        /** @var array<string, DiscoveredPlugin> $unique Discovered plugins keyed by name@version. */
        $unique = [];

        foreach ($sources as $source) {
            foreach ($source->discover() as $discovered) {
                $key = $discovered->identityKey();
                if (!isset($unique[$key])) {
                    $unique[$key] = $discovered;
                }
            }
        }

        $discovered = array_values($unique);
        $this->announce($discovered);

        return $discovered;
    }

    /**
     * Record and publish a {@see PluginDiscovered} event for each distinct discovery.
     *
     * @param list<DiscoveredPlugin> $discovered The distinct discovered plugins.
     */
    private function announce(array $discovered): void
    {
        if ($discovered === []) {
            return;
        }

        $now = $this->clock->now();
        $events = [];
        foreach ($discovered as $plugin) {
            $manifest = $plugin->manifest();
            $events[] = new PluginDiscovered(
                $manifest->name(),
                (string) $manifest->version(),
                $manifest->kind(),
                $plugin->locator(),
                $now,
            );
        }

        $this->publisher->publish($events);
    }
}
