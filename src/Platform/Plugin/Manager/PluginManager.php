<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Manager;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\PluginContext;
use Nizam\Platform\Plugin\PluginHealthStatus;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\Port\DiscoveredPlugin;
use Nizam\Platform\Plugin\Port\PluginSource;
use Nizam\Platform\Plugin\RegisteredPlugin;
use Nizam\Platform\Plugin\Service\PluginHealthChecker;
use Nizam\Platform\Support\Result;

/**
 * The public surface of the Plugin Platform, composing discovery, install, lifecycle and health.
 *
 * The manager is the single façade the rest of the platform talks to. It delegates each concern to the
 * specialist it wraps — the {@see PluginDiscoveryService} to find plugins across sources, the
 * {@see PluginInstaller} to validate/resolve/persist installs and updates, the
 * {@see PluginLifecycleManager} to enable/disable/uninstall under fault isolation, the
 * {@see PluginHealthChecker} to run a plugin's self-check, and the {@see PluginRegistry} for lookups —
 * and presents them as one coherent API. It is tenant-aware: install and update take an optional owning
 * {@see TenantId} (null installs the plugin globally), and health checks run within a supplied or
 * derived {@see PluginContext} carrying the tenant scope. It owns no state of its own beyond its
 * collaborators, so it is safe to resolve once and reuse.
 */
final class PluginManager
{
    /**
     * @param PluginRegistry          $registry     Lookups over installed plugins.
     * @param PluginDiscoveryService  $discovery    Discovers plugins across sources.
     * @param PluginInstaller         $installer    Installs and updates plugins.
     * @param PluginLifecycleManager  $lifecycle    Drives enable/disable/uninstall transitions.
     * @param PluginHealthChecker     $healthChecker Runs a plugin's declared health check.
     */
    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly PluginDiscoveryService $discovery,
        private readonly PluginInstaller $installer,
        private readonly PluginLifecycleManager $lifecycle,
        private readonly PluginHealthChecker $healthChecker,
    ) {
    }

    /**
     * Discover the plugins visible across the given sources, de-duplicated and announced.
     *
     * @param list<PluginSource> $sources The sources to scan, in priority order.
     *
     * @return list<DiscoveredPlugin> The distinct discovered plugins.
     */
    public function discover(array $sources): array
    {
        return $this->discovery->discover($sources);
    }

    /**
     * Install a discovered plugin for a tenant, or globally when no tenant is given.
     *
     * @param DiscoveredPlugin $discovered      The plugin to install.
     * @param string           $platformVersion The running platform version, as a SemVer string.
     * @param TenantId|null    $tenantId        The owning tenant, or null for a global install.
     *
     * @return RegisteredPlugin The newly-installed plugin.
     */
    public function install(
        DiscoveredPlugin $discovered,
        string $platformVersion,
        ?TenantId $tenantId = null,
    ): RegisteredPlugin {
        return $this->installer->install($discovered, $platformVersion, $tenantId);
    }

    /**
     * Update an installed plugin to a strictly newer version, retaining the prior version for rollback.
     *
     * @param string           $name            The manifest name of the plugin to update.
     * @param DiscoveredPlugin $discovered      The newer version to move to.
     * @param string           $platformVersion The running platform version, as a SemVer string.
     *
     * @return UpdateOutcome The updated plugin paired with the superseded version.
     */
    public function update(string $name, DiscoveredPlugin $discovered, string $platformVersion): UpdateOutcome
    {
        return $this->installer->update($name, $discovered, $platformVersion);
    }

    /**
     * Reverse an update, restoring the plugin to its previous version.
     *
     * @param UpdateOutcome $outcome The outcome returned by {@see self::update()}.
     *
     * @return RegisteredPlugin The plugin restored to its previous version.
     */
    public function rollback(UpdateOutcome $outcome): RegisteredPlugin
    {
        return $this->installer->rollback($outcome);
    }

    /**
     * Enable a plugin, running its `onEnable` hook under fault isolation.
     *
     * @param string             $name    The manifest name of the plugin to enable.
     * @param PluginContext|null $context The context handed to the plugin's hook, if any.
     *
     * @return Result A success carrying the enabled plugin, or a failure describing a faulted hook.
     */
    public function enable(string $name, ?PluginContext $context = null): Result
    {
        return $this->lifecycle->enable($name, $context);
    }

    /**
     * Disable a plugin, running its `onDisable` hook under fault isolation.
     *
     * @param string             $name    The manifest name of the plugin to disable.
     * @param PluginContext|null $context The context handed to the plugin's hook, if any.
     *
     * @return Result A success carrying the disabled plugin, or a failure describing a faulted hook.
     */
    public function disable(string $name, ?PluginContext $context = null): Result
    {
        return $this->lifecycle->disable($name, $context);
    }

    /**
     * Uninstall a plugin, running its `onUninstall` hook under fault isolation.
     *
     * @param string             $name    The manifest name of the plugin to uninstall.
     * @param PluginContext|null $context The context handed to the plugin's hook, if any.
     *
     * @return Result A success carrying the uninstalled plugin, or a failure describing a faulted hook.
     */
    public function uninstall(string $name, ?PluginContext $context = null): Result
    {
        return $this->lifecycle->uninstall($name, $context);
    }

    /**
     * Run a plugin's health check and record the outcome as its last-known health.
     *
     * @param string             $name    The manifest name of the plugin to check.
     * @param PluginContext|null $context The context the check runs within; a tenant-scoped or global
     *                                    default is derived from the plugin when null.
     *
     * @throws \Nizam\Platform\Plugin\Exception\PluginNotFoundException When no live plugin has that name.
     *
     * @return PluginHealthStatus The health status produced (also recorded on the plugin).
     */
    public function health(string $name, ?PluginContext $context = null): PluginHealthStatus
    {
        $plugin = $this->registry->get($name);
        $status = $this->healthChecker->run($plugin, $context ?? $this->contextFor($plugin));
        $this->registry->register($plugin);

        return $status;
    }

    /**
     * Fetch the live plugin with the given manifest name, failing when absent.
     *
     * @param string $name The plugin's manifest name.
     *
     * @throws \Nizam\Platform\Plugin\Exception\PluginNotFoundException When no live plugin has that name.
     */
    public function get(string $name): RegisteredPlugin
    {
        return $this->registry->get($name);
    }

    /**
     * Fetch the live plugin with the given manifest name, or null when absent.
     *
     * @param string $name The plugin's manifest name.
     */
    public function find(string $name): ?RegisteredPlugin
    {
        return $this->registry->find($name);
    }

    /**
     * All live registered plugins.
     *
     * @return list<RegisteredPlugin>
     */
    public function all(): array
    {
        return $this->registry->all();
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
        return $this->registry->byKind($kind);
    }

    /**
     * All currently-enabled registered plugins.
     *
     * @return list<RegisteredPlugin>
     */
    public function enabled(): array
    {
        return $this->registry->enabled();
    }

    /**
     * Derive a default context for a plugin, tenant-scoped when the plugin has an owning tenant.
     *
     * The context carries the plugin's config-schema defaults; a tenant-scoped plugin is granted its own
     * required permissions so its health check can exercise the capabilities it declared.
     */
    private function contextFor(RegisteredPlugin $plugin): PluginContext
    {
        $tenant = $plugin->tenantId();
        if ($tenant instanceof TenantId) {
            return PluginContext::forTenant(
                $tenant,
                $plugin->manifest()->requiredPermissionSet(),
                $plugin->manifest()->configSchema(),
            );
        }

        return PluginContext::global($plugin->manifest()->configSchema());
    }
}
