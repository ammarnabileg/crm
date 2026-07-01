<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Port;

use Nizam\Platform\Plugin\PluginId;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\RegisteredPlugin;

/**
 * The port through which {@see RegisteredPlugin} aggregates are persisted and loaded.
 *
 * A hexagonal port: the registry and manager depend on this contract, and Infrastructure adapters
 * (in-memory, PDO) implement it. All reads and writes concern installed plugins; uninstalled plugins
 * are soft-deleted by adapters and excluded from lookups. Lookups by name return the single live
 * install of that plugin name.
 */
interface PluginRepository
{
    /**
     * Persist a plugin aggregate, inserting or updating as needed.
     */
    public function save(RegisteredPlugin $plugin): void;

    /**
     * Load a plugin by its identity, or null when none exists.
     */
    public function ofId(PluginId $id): ?RegisteredPlugin;

    /**
     * Load the live plugin with the given manifest name, or null when none exists.
     */
    public function ofName(string $name): ?RegisteredPlugin;

    /**
     * All live registered plugins.
     *
     * @return list<RegisteredPlugin>
     */
    public function all(): array;

    /**
     * All live registered plugins of a given kind.
     *
     * @return list<RegisteredPlugin>
     */
    public function byKind(PluginKind $kind): array;

    /**
     * All currently-enabled registered plugins.
     *
     * @return list<RegisteredPlugin>
     */
    public function enabled(): array;
}
