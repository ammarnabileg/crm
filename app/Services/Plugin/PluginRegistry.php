<?php

declare(strict_types=1);

namespace App\Services\Plugin;

use App\Contracts\Plugin\PluginInterface;
use App\Models\Plugin;
use RuntimeException;

/**
 * Plugin Registry + lifecycle (docs/51 Plugin SDK).
 *
 * Tracks registered plugin packages and drives their lifecycle — install, enable,
 * disable, update, uninstall — with dependency validation and platform-version
 * compatibility checks. Records each package in the `plugins` table. `capabilities()`
 * aggregates the declarations of all ENABLED plugins (via the PluginApi sandbox) so
 * the host can apply them. Plugins extend the system only through that API; the
 * registry never hands a plugin the database, secrets or AI keys.
 */
final class PluginRegistry
{
    /** @var array<string, PluginInterface> */
    private array $plugins = [];

    public function register(PluginInterface $plugin): self
    {
        $this->plugins[$plugin->key()] = $plugin;

        return $this;
    }

    /** @return string[] registered (code-present) plugin keys */
    public function registered(): array
    {
        return array_keys($this->plugins);
    }

    public function platformVersion(): string
    {
        return (string) config('app.version', '1.0.0');
    }

    /** Install a registered plugin: validate compatibility + dependencies, then record it. */
    public function install(string $key): Plugin
    {
        $plugin = $this->mustGet($key);
        $manifest = PluginManifest::fromArray($key, $plugin->manifest());

        if (! $this->isCompatible($manifest)) {
            throw new RuntimeException(
                "Plugin '{$key}' requires platform >= {$manifest->minPlatformVersion} (have {$this->platformVersion()})."
            );
        }
        foreach ($manifest->dependencies as $dep) {
            if (! $this->isInstalled($dep)) {
                throw new RuntimeException("Plugin '{$key}' depends on '{$dep}', which is not installed.");
            }
        }

        $plugin->onInstall();

        return $this->writeRow($key, $manifest, 'installed', ['installed_at' => now()]);
    }

    /** Enable an installed plugin (its dependencies must be enabled). */
    public function enable(string $key): Plugin
    {
        $plugin = $this->mustGet($key);
        $row = Plugin::findBy('key', $key);
        if ($row === null || (string) $row->status === 'uninstalled') {
            throw new RuntimeException("Plugin '{$key}' must be installed before it can be enabled.");
        }
        $manifest = PluginManifest::fromArray($key, $plugin->manifest());
        foreach ($manifest->dependencies as $dep) {
            if (! $this->isEnabled($dep)) {
                throw new RuntimeException("Plugin '{$key}' needs dependency '{$dep}' enabled first.");
            }
        }

        $plugin->onEnable();
        $row->update(['status' => 'enabled', 'enabled_at' => now()]);

        return $row;
    }

    public function disable(string $key): Plugin
    {
        $plugin = $this->mustGet($key);
        $row = Plugin::findBy('key', $key);
        if ($row === null) {
            throw new RuntimeException("Plugin '{$key}' is not installed.");
        }
        $plugin->onDisable();
        $row->update(['status' => 'disabled']);

        return $row;
    }

    public function uninstall(string $key): void
    {
        $plugin = $this->mustGet($key);
        $row = Plugin::findBy('key', $key);
        if ($row === null) {
            return;
        }
        // Block uninstall if another installed plugin depends on this one.
        foreach ($this->plugins as $otherKey => $other) {
            if ($otherKey === $key || ! $this->isInstalled($otherKey)) {
                continue;
            }
            $deps = PluginManifest::fromArray($otherKey, $other->manifest())->dependencies;
            if (in_array($key, $deps, true)) {
                throw new RuntimeException("Cannot uninstall '{$key}': '{$otherKey}' depends on it.");
            }
        }
        $plugin->onUninstall();
        $row->update(['status' => 'uninstalled']);
    }

    /** Update a plugin's recorded version (manifest must stay compatible). */
    public function update(string $key, string $newVersion): Plugin
    {
        $plugin = $this->mustGet($key);
        $row = Plugin::findBy('key', $key);
        if ($row === null) {
            throw new RuntimeException("Plugin '{$key}' is not installed.");
        }
        $manifest = PluginManifest::fromArray($key, $plugin->manifest());
        if (! $this->isCompatible($manifest)) {
            throw new RuntimeException("Plugin '{$key}' v{$newVersion} is not compatible with this platform.");
        }
        $row->update(['version' => $newVersion]);

        return $row;
    }

    public function isInstalled(string $key): bool
    {
        $row = Plugin::findBy('key', $key);

        return $row !== null && (string) $row->status !== 'uninstalled';
    }

    public function isEnabled(string $key): bool
    {
        $row = Plugin::findBy('key', $key);

        return $row !== null && (string) $row->status === 'enabled';
    }

    public function statusOf(string $key): ?string
    {
        $row = Plugin::findBy('key', $key);

        return $row !== null ? (string) $row->status : null;
    }

    public function isCompatible(PluginManifest $manifest): bool
    {
        return version_compare($this->platformVersion(), $manifest->minPlatformVersion, '>=');
    }

    /**
     * Aggregate the capability declarations of all ENABLED plugins through the
     * sandbox API. The host platform applies these (permissions, menus, workflow
     * nodes, automation actions, …).
     */
    public function capabilities(): PluginApi
    {
        $api = new PluginApi('*');
        foreach ($this->plugins as $key => $plugin) {
            if ($this->isEnabled($key)) {
                $plugin->register($api);
            }
        }

        return $api;
    }

    private function mustGet(string $key): PluginInterface
    {
        if (! isset($this->plugins[$key])) {
            throw new RuntimeException("Unknown plugin '{$key}' (not registered).");
        }

        return $this->plugins[$key];
    }

    private function writeRow(string $key, PluginManifest $manifest, string $status, array $extra): Plugin
    {
        $existing = Plugin::findBy('key', $key);
        $attributes = array_merge([
            'key'                  => $key,
            'name'                 => $manifest->name,
            'author'               => $manifest->author,
            'version'              => $manifest->version,
            'description'          => $manifest->description,
            'status'               => $status,
            'manifest'             => json_encode($manifest->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'dependencies'         => json_encode($manifest->dependencies, JSON_UNESCAPED_SLASHES),
            'permissions'          => json_encode($manifest->permissions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'license'              => $manifest->license,
            'min_platform_version' => $manifest->minPlatformVersion,
        ], $extra);

        if ($existing !== null) {
            $existing->update($attributes);

            return $existing;
        }

        return Plugin::create($attributes);
    }
}
