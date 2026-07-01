<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Manager;

use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\Exception\IncompatiblePluginException;
use Nizam\Platform\Plugin\Exception\PluginDependencyException;
use Nizam\Platform\Plugin\Exception\PluginNotFoundException;
use Nizam\Platform\Plugin\Exception\PluginStateException;
use Nizam\Platform\Plugin\Exception\PluginValidationException;
use Nizam\Platform\Plugin\Port\DiscoveredPlugin;
use Nizam\Platform\Plugin\Port\PluginEventPublisher;
use Nizam\Platform\Plugin\PluginId;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\PluginState;
use Nizam\Platform\Plugin\RegisteredPlugin;
use Nizam\Platform\Plugin\SemanticVersion;
use Nizam\Platform\Plugin\Service\PluginDependencyResolver;
use Nizam\Platform\Plugin\Service\PluginValidator;

/**
 * Installs and updates plugins: validate, resolve dependencies, persist and announce.
 *
 * The installer is the write path from a {@see DiscoveredPlugin} to a persisted {@see RegisteredPlugin}.
 * On {@see self::install()} it validates the manifest against the running platform via the
 * {@see PluginValidator} (rejecting a manifest whose entry point does not fulfil its kind contract or
 * whose platform constraint is unmet), resolves the plugin's required dependencies against what is
 * already installed with the {@see PluginDependencyResolver} (rejecting a missing or incompatible
 * required dependency, or a cycle), records the plugin in {@see \Nizam\Platform\Plugin\PluginState::Installed},
 * persists it, and publishes the pulled {@see \Nizam\Platform\Plugin\Event\PluginInstalled} event.
 *
 * On {@see self::update()} it moves an existing install to a strictly newer version — the aggregate
 * refuses a same-or-older version — validates the new manifest, publishes the pulled
 * {@see \Nizam\Platform\Plugin\Event\PluginUpdated} event, and returns an {@see UpdateOutcome} that
 * retains the superseded manifest so the update can be reversed with {@see self::rollback()}.
 */
final class PluginInstaller
{
    /**
     * @param PluginRegistry           $registry  The registry the installer reads installs from and writes to.
     * @param PluginValidator          $validator Validates a manifest against the platform before install.
     * @param PluginDependencyResolver $resolver  Resolves and checks the plugin's dependency graph.
     * @param PluginEventPublisher     $publisher The egress for pulled lifecycle domain events.
     * @param Clock                    $clock     The time source handed to the aggregate.
     */
    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly PluginValidator $validator,
        private readonly PluginDependencyResolver $resolver,
        private readonly PluginEventPublisher $publisher,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Install a discovered plugin: validate, resolve dependencies, persist and announce.
     *
     * @param DiscoveredPlugin $discovered      The plugin to install.
     * @param string           $platformVersion The running platform version, as a SemVer string.
     * @param TenantId|null    $tenantId        The owning tenant, or null for a global install.
     *
     * @throws PluginValidationException    When the manifest fails validation.
     * @throws IncompatiblePluginException  When the manifest's platform constraint is unmet.
     * @throws PluginStateException         When a live install of the plugin already exists.
     * @throws PluginDependencyException    When a required dependency is missing or incompatible, or the
     *                                       dependency graph cycles.
     *
     * @return RegisteredPlugin The newly-installed plugin.
     */
    public function install(
        DiscoveredPlugin $discovered,
        string $platformVersion,
        ?TenantId $tenantId = null,
    ): RegisteredPlugin {
        $manifest = $discovered->manifest();

        if ($this->registry->has($manifest->name())) {
            throw PluginStateException::forOperation(
                sprintf('install an already-installed plugin "%s" —', $manifest->name()),
                PluginState::Installed,
            );
        }

        $this->validate($manifest, $platformVersion);
        $this->resolveDependencies($manifest);

        $plugin = RegisteredPlugin::install(
            PluginId::generate(),
            $manifest,
            $discovered->locator(),
            $tenantId,
            $this->clock,
        );

        $this->registry->register($plugin);
        $this->publisher->publish($plugin->pullDomainEvents());

        return $plugin;
    }

    /**
     * Update an installed plugin to a strictly newer version, retaining the prior version for rollback.
     *
     * @param string           $name            The manifest name of the plugin to update.
     * @param DiscoveredPlugin $discovered      The newer version to move to.
     * @param string           $platformVersion The running platform version, as a SemVer string.
     *
     * @throws PluginNotFoundException      When no live plugin has that name.
     * @throws PluginValidationException    When the newer manifest fails validation.
     * @throws IncompatiblePluginException  When the newer manifest's platform constraint is unmet.
     * @throws PluginStateException         When the version is not strictly newer, names differ, or the
     *                                      plugin is terminal.
     *
     * @return UpdateOutcome The updated plugin paired with the superseded version for rollback.
     */
    public function update(string $name, DiscoveredPlugin $discovered, string $platformVersion): UpdateOutcome
    {
        $plugin = $this->registry->get($name);
        $newManifest = $discovered->manifest();

        $this->validate($newManifest, $platformVersion);

        $previousManifest = $plugin->manifest();
        $previousSource = $plugin->source();

        $plugin->update($newManifest, $discovered->locator(), $this->clock);

        $this->registry->register($plugin);
        $this->publisher->publish($plugin->pullDomainEvents());

        return new UpdateOutcome($plugin, $previousManifest, $previousSource);
    }

    /**
     * Reverse an update, restoring the plugin to the version it held before.
     *
     * Applies the retained prior manifest as a fresh "update" back to the earlier version. Because the
     * aggregate only permits strictly-newer updates, the current (newer) record is uninstalled and the
     * prior version is re-installed in its place, preserving a clean audit trail of both transitions.
     *
     * @param UpdateOutcome $outcome The outcome captured by {@see self::update()}.
     *
     * @throws PluginStateException When the plugin is no longer in an updatable state.
     *
     * @return RegisteredPlugin The plugin restored to its previous version.
     */
    public function rollback(UpdateOutcome $outcome): RegisteredPlugin
    {
        $current = $outcome->plugin();
        $wasEnabled = $current->isEnabled();

        $current->uninstall($this->clock);
        $this->registry->register($current);
        $this->publisher->publish($current->pullDomainEvents());

        $restored = RegisteredPlugin::install(
            PluginId::generate(),
            $outcome->previousManifest(),
            $outcome->previousSource(),
            $current->tenantId(),
            $this->clock,
        );
        if ($wasEnabled) {
            $restored->enable($this->clock);
        }

        $this->registry->register($restored);
        $this->publisher->publish($restored->pullDomainEvents());

        return $restored;
    }

    /**
     * Validate a manifest, raising the appropriate exception on failure.
     *
     * @throws PluginValidationException   When validation fails for a non-platform reason.
     * @throws IncompatiblePluginException When the failure is an unmet platform constraint.
     */
    private function validate(PluginManifest $manifest, string $platformVersion): void
    {
        $result = $this->validator->validate($manifest, $platformVersion);
        if ($result->isOk()) {
            return;
        }

        if (!$manifest->platformConstraint()->satisfies($this->parsePlatformVersion($platformVersion, $manifest))) {
            throw IncompatiblePluginException::forPlatform(
                $manifest->name(),
                $manifest->platformConstraint()->raw(),
                $platformVersion,
            );
        }

        throw PluginValidationException::forReason((string) $result->errorMessage());
    }

    /**
     * Parse the platform version for the incompatibility check, defaulting to the plugin's own version.
     *
     * The plugin's own version is a guaranteed-valid SemVer fallback used only so the constraint check
     * can run to decide *which* exception to raise; when the platform string itself is unparseable the
     * fallback ensures the constraint is treated as unmet, yielding the incompatibility exception.
     */
    private function parsePlatformVersion(
        string $platformVersion,
        PluginManifest $manifest,
    ): SemanticVersion {
        try {
            return SemanticVersion::parse($platformVersion);
        } catch (PluginValidationException) {
            return $manifest->version();
        }
    }

    /**
     * Resolve the plugin's required dependencies against the current installed set.
     *
     * Runs the topological resolver over the already-installed manifests plus the incoming one, which
     * surfaces a missing or incompatible required dependency, or a dependency cycle, as an exception.
     *
     * @throws PluginDependencyException When resolution fails.
     */
    private function resolveDependencies(PluginManifest $manifest): void
    {
        $available = [$manifest];
        foreach ($this->registry->all() as $installed) {
            if ($installed->name() !== $manifest->name()) {
                $available[] = $installed->manifest();
            }
        }

        $this->resolver->resolveOrder($available);
    }
}
