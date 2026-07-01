<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

/**
 * Optional lifecycle callbacks a plugin may implement to react to its own lifecycle transitions.
 *
 * A plugin that needs to run setup or teardown work — provisioning storage on install, opening a
 * connection on enable, releasing resources on disable, cleaning up on uninstall, or migrating state
 * on update — implements this interface. The platform invokes each hook, passing the
 * {@see PluginContext} for the transition, *inside a fault-catching sandbox*: a hook that throws marks
 * the plugin failed but never crashes the Core. Implementing this interface is optional; a plugin that
 * has nothing to do at a transition simply does not implement it (or leaves the method empty).
 */
interface PluginLifecycleHooks
{
    /**
     * Run once when the plugin is installed, before it is enabled.
     *
     * @param PluginContext $context The context for the install operation.
     */
    public function onInstall(PluginContext $context): void;

    /**
     * Run each time the plugin is enabled.
     *
     * @param PluginContext $context The context for the enable operation.
     */
    public function onEnable(PluginContext $context): void;

    /**
     * Run each time the plugin is disabled.
     *
     * @param PluginContext $context The context for the disable operation.
     */
    public function onDisable(PluginContext $context): void;

    /**
     * Run once when the plugin is uninstalled.
     *
     * @param PluginContext $context The context for the uninstall operation.
     */
    public function onUninstall(PluginContext $context): void;

    /**
     * Run when the plugin is updated from a previous version to this one.
     *
     * @param PluginContext $context The context for the update operation.
     */
    public function onUpdate(PluginContext $context): void;
}
