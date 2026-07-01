<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Manager;

use Nizam\Kernel\Domain\Clock;
use Nizam\Platform\Plugin\Exception\PluginDependencyException;
use Nizam\Platform\Plugin\Port\PluginEventPublisher;
use Nizam\Platform\Plugin\Port\PluginInstantiator;
use Nizam\Platform\Plugin\PluginContext;
use Nizam\Platform\Plugin\PluginDependency;
use Nizam\Platform\Plugin\PluginInterface;
use Nizam\Platform\Plugin\PluginLifecycleHooks;
use Nizam\Platform\Plugin\RegisteredPlugin;
use Nizam\Platform\Plugin\Service\PluginSandbox;
use Nizam\Platform\Support\Result;

/**
 * Drives a plugin through its lifecycle transitions, running its hooks under fault isolation.
 *
 * The lifecycle manager owns enable / disable / uninstall. Each transition follows the same discipline:
 * it guards the move (notably, {@see self::enable()} refuses unless every *required* dependency is itself
 * installed, enabled and satisfies the declared version constraint — optional dependencies never block),
 * mutates the {@see RegisteredPlugin} aggregate, runs the plugin's optional {@see PluginLifecycleHooks}
 * callback for that transition *inside the {@see PluginSandbox}* so a throwing hook is contained rather
 * than crashing the Core, and — only when the hook succeeds — persists the plugin and publishes its
 * pulled domain events. When a hook faults the sandbox has already published a
 * {@see \Nizam\Platform\Plugin\Event\PluginFailed} event; the manager then marks the plugin failed,
 * persists that, and returns a failure {@see Result} so the caller learns the transition did not take.
 * A plugin that declares no hooks transitions cleanly with no plugin code run at all.
 */
final class PluginLifecycleManager
{
    /**
     * @param PluginRegistry       $registry     The registry the manager reads and writes plugins through.
     * @param PluginInstantiator   $instantiator Constructs a plugin instance to invoke its hooks.
     * @param PluginSandbox        $sandbox      The fault-isolation boundary each hook runs inside.
     * @param PluginEventPublisher $publisher    The egress for pulled lifecycle domain events.
     * @param Clock                $clock        The time source handed to the aggregate.
     */
    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly PluginInstantiator $instantiator,
        private readonly PluginSandbox $sandbox,
        private readonly PluginEventPublisher $publisher,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Enable a plugin, requiring every required dependency to be enabled and compatible.
     *
     * @param string             $name    The manifest name of the plugin to enable.
     * @param PluginContext|null $context The context handed to the plugin's `onEnable` hook, if any.
     *
     * @throws \Nizam\Platform\Plugin\Exception\PluginNotFoundException When no live plugin has that name.
     * @throws PluginDependencyException When a required dependency is missing, disabled or incompatible.
     * @throws \Nizam\Platform\Plugin\Exception\PluginStateException    When the plugin cannot be enabled.
     *
     * @return Result A success carrying the enabled plugin, or a failure describing a faulted hook.
     */
    public function enable(string $name, ?PluginContext $context = null): Result
    {
        $plugin = $this->registry->get($name);
        $this->assertDependenciesEnabled($plugin);

        $plugin->enable($this->clock);

        return $this->applyHook($plugin, 'onEnable', $context);
    }

    /**
     * Disable a plugin, stopping it from participating without uninstalling it.
     *
     * @param string             $name    The manifest name of the plugin to disable.
     * @param PluginContext|null $context The context handed to the plugin's `onDisable` hook, if any.
     *
     * @throws \Nizam\Platform\Plugin\Exception\PluginNotFoundException When no live plugin has that name.
     * @throws \Nizam\Platform\Plugin\Exception\PluginStateException    When the plugin is not enabled.
     *
     * @return Result A success carrying the disabled plugin, or a failure describing a faulted hook.
     */
    public function disable(string $name, ?PluginContext $context = null): Result
    {
        $plugin = $this->registry->get($name);
        $plugin->disable($this->clock);

        return $this->applyHook($plugin, 'onDisable', $context);
    }

    /**
     * Uninstall a plugin, retiring it from the registry.
     *
     * @param string             $name    The manifest name of the plugin to uninstall.
     * @param PluginContext|null $context The context handed to the plugin's `onUninstall` hook, if any.
     *
     * @throws \Nizam\Platform\Plugin\Exception\PluginNotFoundException When no live plugin has that name.
     * @throws \Nizam\Platform\Plugin\Exception\PluginStateException    When the plugin is already terminal.
     *
     * @return Result A success carrying the uninstalled plugin, or a failure describing a faulted hook.
     */
    public function uninstall(string $name, ?PluginContext $context = null): Result
    {
        $plugin = $this->registry->get($name);
        $plugin->uninstall($this->clock);

        return $this->applyHook($plugin, 'onUninstall', $context);
    }

    /**
     * Verify every required dependency is installed, enabled and version-compatible.
     *
     * Optional dependencies never block: a missing or unsatisfiable optional dependency is skipped.
     *
     * @throws PluginDependencyException When a required dependency is absent, not enabled, or incompatible.
     */
    private function assertDependenciesEnabled(RegisteredPlugin $plugin): void
    {
        foreach ($plugin->manifest()->dependencies() as $dependency) {
            $this->assertDependencyEnabled($plugin->name(), $dependency);
        }
    }

    /**
     * Verify a single dependency is satisfied by an enabled, compatible install.
     *
     * @throws PluginDependencyException When a required dependency is absent, not enabled, or incompatible.
     */
    private function assertDependencyEnabled(string $dependant, PluginDependency $dependency): void
    {
        $target = $this->registry->find($dependency->pluginName());

        if ($target === null || !$dependency->isSatisfiedBy($target->manifest()->version())) {
            if ($dependency->isOptional()) {
                return;
            }
            if ($target === null) {
                throw PluginDependencyException::missingRequired($dependant, $dependency->pluginName());
            }

            throw PluginDependencyException::incompatibleRequired(
                $dependant,
                $dependency->pluginName(),
                $dependency->constraint()->raw(),
            );
        }

        if (!$target->isEnabled() && !$dependency->isOptional()) {
            throw PluginDependencyException::incompatibleRequired(
                $dependant,
                $dependency->pluginName(),
                sprintf('%s (must be enabled)', $dependency->constraint()->raw()),
            );
        }
    }

    /**
     * Run the plugin's lifecycle hook for a completed transition inside the sandbox, then persist.
     *
     * When the plugin declares no hooks — or the specific hook is a no-op — the transition is simply
     * persisted and its events published. When the hook faults, the sandbox has already published a
     * failure event; the plugin is marked failed, that is persisted, and a failure {@see Result} is
     * returned so the transition does not silently appear to have succeeded.
     *
     * @param RegisteredPlugin   $plugin  The plugin whose transition has been applied on the aggregate.
     * @param string             $hook    The hook method name to invoke ("onEnable"/"onDisable"/"onUninstall").
     * @param PluginContext|null $context The context to hand the hook, or null for a global default.
     *
     * @return Result A success carrying the plugin, or a failure carrying the fault detail.
     */
    private function applyHook(RegisteredPlugin $plugin, string $hook, ?PluginContext $context): Result
    {
        $hooks = $this->resolveHooks($plugin);
        if ($hooks === null) {
            $this->commit($plugin);

            return Result::ok($plugin);
        }

        $runContext = $context ?? PluginContext::global($plugin->manifest()->configSchema());
        $result = $this->sandbox->run(
            $plugin,
            static function () use ($hooks, $hook, $runContext): mixed {
                $hooks->{$hook}($runContext);

                return null;
            },
        );

        if ($result->isErr()) {
            $plugin->markFailed((string) $result->errorMessage(), $this->clock);
            $this->commit($plugin);

            return $result;
        }

        $this->commit($plugin);

        return Result::ok($plugin);
    }

    /**
     * Resolve the plugin's lifecycle hooks, or null when it declares none.
     *
     * Instantiation runs through the {@see PluginSandbox} so a plugin with a throwing constructor cannot
     * crash the transition; a construction fault yields null (no hooks to run) after the sandbox has
     * recorded the failure.
     */
    private function resolveHooks(RegisteredPlugin $plugin): ?PluginLifecycleHooks
    {
        $result = $this->sandbox->run(
            $plugin,
            fn (): PluginInterface => $this->instantiator->make($plugin->manifest()),
        );

        if ($result->isErr()) {
            return null;
        }

        $instance = $result->value();

        return $instance instanceof PluginLifecycleHooks ? $instance : null;
    }

    /**
     * Persist the plugin and publish its pulled domain events.
     */
    private function commit(RegisteredPlugin $plugin): void
    {
        $this->registry->register($plugin);
        $this->publisher->publish($plugin->pullDomainEvents());
    }
}
