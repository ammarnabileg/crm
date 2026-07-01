<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Infrastructure\Persistence\InMemory;

use Nizam\Platform\Plugin\Port\PluginRepository;
use Nizam\Platform\Plugin\PluginId;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\PluginState;
use Nizam\Platform\Plugin\RegisteredPlugin;

/**
 * An in-memory, seedable {@see PluginRepository} for tests and local wiring.
 *
 * Plugins are held in a process-local map keyed by {@see PluginId}. The adapter faithfully models the
 * live-vs-uninstalled distinction the durable adapter enforces at the database: an uninstalled plugin
 * is soft-deleted, so it is never returned by {@see self::ofName()}, {@see self::all()},
 * {@see self::byKind()} or {@see self::enabled()}, but is still addressable by its id for history and
 * re-install checks. Lookups by name return the single live install of that name. It is not persistent
 * and not concurrency safe; it exists so the module can run and be tested without a database.
 */
final class InMemoryPluginRepository implements PluginRepository
{
    /**
     * @var array<string, RegisteredPlugin> Plugins keyed by id string, in insertion order.
     */
    private array $plugins = [];

    /**
     * Seed the repository with plugins.
     *
     * @param list<RegisteredPlugin> $plugins The plugins to store.
     */
    public function seed(array $plugins): void
    {
        foreach ($plugins as $plugin) {
            $this->save($plugin);
        }
    }

    /**
     * Persist a plugin aggregate, inserting or replacing the stored copy.
     */
    public function save(RegisteredPlugin $plugin): void
    {
        $this->plugins[$plugin->pluginId()->toString()] = $plugin;
    }

    /**
     * Load a plugin by its identity, or null when none exists.
     */
    public function ofId(PluginId $id): ?RegisteredPlugin
    {
        return $this->plugins[$id->toString()] ?? null;
    }

    /**
     * Load the live plugin with the given manifest name, or null when none exists.
     */
    public function ofName(string $name): ?RegisteredPlugin
    {
        foreach ($this->plugins as $plugin) {
            if ($this->isLive($plugin) && $plugin->name() === $name) {
                return $plugin;
            }
        }

        return null;
    }

    /**
     * All live registered plugins, in insertion order.
     *
     * @return list<RegisteredPlugin>
     */
    public function all(): array
    {
        $live = [];
        foreach ($this->plugins as $plugin) {
            if ($this->isLive($plugin)) {
                $live[] = $plugin;
            }
        }

        return $live;
    }

    /**
     * All live registered plugins of a given kind.
     *
     * @return list<RegisteredPlugin>
     */
    public function byKind(PluginKind $kind): array
    {
        $matches = [];
        foreach ($this->all() as $plugin) {
            if ($plugin->kind() === $kind) {
                $matches[] = $plugin;
            }
        }

        return $matches;
    }

    /**
     * All currently-enabled registered plugins.
     *
     * @return list<RegisteredPlugin>
     */
    public function enabled(): array
    {
        $matches = [];
        foreach ($this->all() as $plugin) {
            if ($plugin->isEnabled()) {
                $matches[] = $plugin;
            }
        }

        return $matches;
    }

    /**
     * Whether a plugin is still live (not soft-deleted / uninstalled).
     */
    private function isLive(RegisteredPlugin $plugin): bool
    {
        return $plugin->state() !== PluginState::Uninstalled;
    }
}
