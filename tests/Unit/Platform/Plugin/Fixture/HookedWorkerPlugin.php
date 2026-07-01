<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin\Fixture;

use Nizam\Platform\Plugin\Contract\WorkerPlugin;
use Nizam\Platform\Plugin\PluginContext;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\PluginLifecycleHooks;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\SemanticVersion;
use Nizam\Platform\Plugin\VersionConstraint;
use RuntimeException;

/**
 * A worker plugin fixture that implements {@see PluginLifecycleHooks} and records hook invocations.
 *
 * The lifecycle-manager tests use this to prove that a plugin's `onEnable`/`onDisable`/`onUninstall`
 * hooks are actually invoked inside the sandbox during a transition, and that a hook that throws is
 * contained as a failure rather than crashing the transition. Which hook throws is configurable so a
 * single fixture can drive both the happy path and the fault path. Instances are created directly by
 * the test's fake instantiator; nothing here performs I/O.
 */
final class HookedWorkerPlugin implements WorkerPlugin, PluginLifecycleHooks
{
    /**
     * @var list<string> The names of the hooks invoked, in order.
     */
    public array $invoked = [];

    /**
     * @param string      $name        The manifest name this instance publishes.
     * @param string|null $throwOnHook The hook that should throw, or null for a clean plugin.
     */
    public function __construct(
        private readonly string $name = 'pkg.hooked',
        private readonly ?string $throwOnHook = null,
    ) {
    }

    /**
     * The plugin's published descriptor.
     */
    public function manifest(): PluginManifest
    {
        return PluginManifest::create(
            name: $this->name,
            displayName: 'Hooked Worker',
            version: SemanticVersion::of(1, 0, 0),
            kind: PluginKind::Worker,
            description: 'A worker with lifecycle hooks.',
            author: 'Test',
            license: 'MIT',
            entryPointClass: self::class,
            platformConstraint: VersionConstraint::parse('*'),
        );
    }

    /**
     * A machine-readable description of the work this worker performs.
     *
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        return ['taskTypes' => ['noop']];
    }

    /**
     * {@inheritDoc}
     */
    public function onInstall(PluginContext $context): void
    {
        $this->record('onInstall');
    }

    /**
     * {@inheritDoc}
     */
    public function onEnable(PluginContext $context): void
    {
        $this->record('onEnable');
    }

    /**
     * {@inheritDoc}
     */
    public function onDisable(PluginContext $context): void
    {
        $this->record('onDisable');
    }

    /**
     * {@inheritDoc}
     */
    public function onUninstall(PluginContext $context): void
    {
        $this->record('onUninstall');
    }

    /**
     * {@inheritDoc}
     */
    public function onUpdate(PluginContext $context): void
    {
        $this->record('onUpdate');
    }

    /**
     * Record a hook invocation, throwing when this instance is configured to fail on that hook.
     */
    private function record(string $hook): void
    {
        $this->invoked[] = $hook;
        if ($this->throwOnHook === $hook) {
            throw new RuntimeException(sprintf('%s hook failed deliberately', $hook));
        }
    }
}
