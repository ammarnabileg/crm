<?php

declare(strict_types=1);

namespace App\Contracts\Plugin;

use App\Services\Plugin\PluginApi;

/**
 * A HalaOps plugin (docs/51 Plugin SDK). A plugin is self-contained and extends the
 * platform ONLY through the PluginApi capability gateway (the sandbox) — it never
 * touches the database, secrets, tenant data or AI keys directly. The platform
 * drives its lifecycle (install → enable → disable → uninstall) via the hooks.
 *
 * Most plugins extend App\Services\Plugin\AbstractPlugin (no-op lifecycle hooks) and
 * implement key(), manifest() and register().
 */
interface PluginInterface
{
    /** Stable, unique plugin key, e.g. 'personality_assessment'. */
    public function key(): string;

    /**
     * Plugin manifest: name, author, version, description, dependencies[],
     * permissions[], required_modules[], min_platform_version, license.
     *
     * @return array<string,mixed>
     */
    public function manifest(): array;

    /** Declare capabilities (permissions, menus, workflow nodes, actions, …) via the sandbox API. */
    public function register(PluginApi $api): void;

    public function onInstall(): void;

    public function onEnable(): void;

    public function onDisable(): void;

    public function onUninstall(): void;
}
