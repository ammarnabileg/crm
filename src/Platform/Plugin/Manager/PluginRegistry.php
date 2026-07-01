<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Manager;

use Nizam\Platform\Plugin\Exception\PluginNotFoundException;
use Nizam\Platform\Plugin\Port\PluginRepository;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\RegisteredPlugin;

/**
 * The read-oriented index over the {@see PluginRepository} of installed plugins.
 *
 * The registry is the platform's lookup surface for what is installed: it answers "which plugins do we
 * have?", "which are enabled?", "which are of this kind?" and "is this one present?" by delegating to
 * the repository port, so callers query plugins without touching persistence directly. It also owns the
 * single write the read side needs — {@see self::register()} persists (inserts or updates) a
 * {@see RegisteredPlugin} — because installation, lifecycle changes and health updates all funnel their
 * saves through the same place. Every lookup concerns live installs only; uninstalled plugins are
 * soft-deleted by the repository and never surface here.
 */
final class PluginRegistry
{
    /**
     * @param PluginRepository $repository The persistence port the registry indexes over.
     */
    public function __construct(
        private readonly PluginRepository $repository,
    ) {
    }

    /**
     * Persist a plugin into the registry, inserting or updating as needed.
     *
     * @param RegisteredPlugin $plugin The plugin aggregate to store.
     */
    public function register(RegisteredPlugin $plugin): void
    {
        $this->repository->save($plugin);
    }

    /**
     * Fetch the live plugin with the given manifest name, or null when none exists.
     *
     * @param string $name The plugin's manifest name.
     */
    public function find(string $name): ?RegisteredPlugin
    {
        return $this->repository->ofName($name);
    }

    /**
     * Fetch the live plugin with the given manifest name, failing when absent.
     *
     * @param string $name The plugin's manifest name.
     *
     * @throws PluginNotFoundException When no live plugin has that name.
     */
    public function get(string $name): RegisteredPlugin
    {
        $plugin = $this->repository->ofName($name);
        if ($plugin === null) {
            throw PluginNotFoundException::withName($name);
        }

        return $plugin;
    }

    /**
     * All live registered plugins.
     *
     * @return list<RegisteredPlugin>
     */
    public function all(): array
    {
        return $this->repository->all();
    }

    /**
     * All live registered plugins of a given kind.
     *
     * @param PluginKind $kind The capability kind to filter by.
     *
     * @return list<RegisteredPlugin>
     */
    public function byKind(PluginKind $kind): array
    {
        return $this->repository->byKind($kind);
    }

    /**
     * All currently-enabled registered plugins.
     *
     * @return list<RegisteredPlugin>
     */
    public function enabled(): array
    {
        return $this->repository->enabled();
    }

    /**
     * Whether a live plugin with the given manifest name exists.
     *
     * @param string $name The plugin's manifest name.
     */
    public function has(string $name): bool
    {
        return $this->repository->ofName($name) !== null;
    }
}
