<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin;

use Nizam\Platform\Plugin\Event\PluginDisabled;
use Nizam\Platform\Plugin\Event\PluginEnabled;
use Nizam\Platform\Plugin\Event\PluginFailed;
use Nizam\Platform\Plugin\Event\PluginUninstalled;
use Nizam\Platform\Plugin\Exception\PluginDependencyException;
use Nizam\Platform\Plugin\Infrastructure\Persistence\InMemory\InMemoryPluginRepository;
use Nizam\Platform\Plugin\Infrastructure\Testing\ReferencePlugin;
use Nizam\Platform\Plugin\Manager\PluginLifecycleManager;
use Nizam\Platform\Plugin\Manager\PluginRegistry;
use Nizam\Platform\Plugin\PluginId;
use Nizam\Platform\Plugin\PluginState;
use Nizam\Platform\Plugin\RegisteredPlugin;
use Nizam\Platform\Plugin\Service\PluginSandbox;
use Nizam\Tests\Unit\Platform\Plugin\Fixture\HookedWorkerPlugin;
use Nizam\Tests\Unit\Platform\Plugin\Fixture\MapPluginInstantiator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PluginLifecycleManager}: enable/disable/uninstall events, dependency gating, hooks.
 */
#[CoversClass(PluginLifecycleManager::class)]
#[CoversClass(PluginRegistry::class)]
#[CoversClass(PluginSandbox::class)]
final class PluginLifecycleManagerTest extends TestCase
{
    private InMemoryPluginRepository $repository;
    private PluginRegistry $registry;
    private CollectingEventPublisher $publisher;
    private FixedTestClock $clock;
    private MapPluginInstantiator $instantiator;
    private PluginLifecycleManager $manager;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPluginRepository();
        $this->registry = new PluginRegistry($this->repository);
        $this->publisher = new CollectingEventPublisher();
        $this->clock = new FixedTestClock();
        $this->instantiator = new MapPluginInstantiator();
        $sandbox = new PluginSandbox($this->publisher, $this->clock);
        $this->manager = new PluginLifecycleManager(
            $this->registry,
            $this->instantiator,
            $sandbox,
            $this->publisher,
            $this->clock,
        );
    }

    /**
     * Install a plugin (state Installed) into the registry and forget its install event.
     *
     * The instantiator is given the hookless {@see ReferencePlugin} for this name so hook resolution
     * succeeds cleanly (the reference plugin declares no lifecycle hooks) and no construction-fault
     * event is published — leaving the test to assert only the transition events it drives.
     *
     * @param list<\Nizam\Platform\Plugin\PluginDependency> $dependencies
     */
    private function seed(string $name, array $dependencies = []): RegisteredPlugin
    {
        $plugin = RegisteredPlugin::install(
            PluginId::generate(),
            PluginTestFactory::manifest($name, '1.0.0', $dependencies),
            'array:' . $name,
            null,
            $this->clock,
        );
        $plugin->pullDomainEvents();
        $this->registry->register($plugin);
        $this->instantiator->register($name, new ReferencePlugin());

        return $plugin;
    }

    #[Test]
    public function fullLifecycleEmitsEventsInOrder(): void
    {
        $this->seed('pkg.a');

        self::assertTrue($this->manager->enable('pkg.a')->isOk());
        self::assertTrue($this->manager->disable('pkg.a')->isOk());
        self::assertTrue($this->manager->uninstall('pkg.a')->isOk());

        self::assertSame(
            ['plugin.enabled', 'plugin.disabled', 'plugin.uninstalled'],
            $this->publisher->eventNames(),
        );
    }

    #[Test]
    public function enablePersistsTheEnabledState(): void
    {
        $this->seed('pkg.a');

        $this->manager->enable('pkg.a');

        self::assertSame(PluginState::Enabled, $this->registry->get('pkg.a')->state());
        self::assertTrue($this->publisher->has(PluginEnabled::class));
    }

    #[Test]
    public function disablePersistsTheDisabledState(): void
    {
        $this->seed('pkg.a');
        $this->manager->enable('pkg.a');
        $this->publisher->reset();

        $this->manager->disable('pkg.a');

        self::assertSame(PluginState::Disabled, $this->registry->get('pkg.a')->state());
        self::assertTrue($this->publisher->has(PluginDisabled::class));
    }

    #[Test]
    public function uninstallRetiresThePlugin(): void
    {
        $this->seed('pkg.a');
        $this->manager->enable('pkg.a');
        $this->publisher->reset();

        $this->manager->uninstall('pkg.a');

        self::assertNull($this->registry->find('pkg.a'));
        self::assertTrue($this->publisher->has(PluginUninstalled::class));
    }

    #[Test]
    public function enableIsBlockedWhenARequiredDependencyIsDisabled(): void
    {
        // pkg.dep is installed but never enabled; pkg.app requires it.
        $this->seed('pkg.dep');
        $this->seed('pkg.app', [PluginTestFactory::requires('pkg.dep', '^1.0')]);

        $this->expectException(PluginDependencyException::class);
        $this->expectExceptionMessage('must be enabled');

        $this->manager->enable('pkg.app');
    }

    #[Test]
    public function enableIsBlockedWhenARequiredDependencyIsMissing(): void
    {
        $this->seed('pkg.app', [PluginTestFactory::requires('pkg.absent', '^1.0')]);

        $this->expectException(PluginDependencyException::class);
        $this->expectExceptionMessage('not available');

        $this->manager->enable('pkg.app');
    }

    #[Test]
    public function enableSucceedsWhenTheRequiredDependencyIsEnabled(): void
    {
        $this->seed('pkg.dep');
        $this->manager->enable('pkg.dep');
        $this->seed('pkg.app', [PluginTestFactory::requires('pkg.dep', '^1.0')]);

        $result = $this->manager->enable('pkg.app');

        self::assertTrue($result->isOk());
        self::assertSame(PluginState::Enabled, $this->registry->get('pkg.app')->state());
    }

    #[Test]
    public function aMissingOptionalDependencyDoesNotBlockEnable(): void
    {
        $this->seed('pkg.app', [PluginTestFactory::optionally('pkg.absent', '^1.0')]);

        self::assertTrue($this->manager->enable('pkg.app')->isOk());
    }

    #[Test]
    public function enableRunsTheOnEnableHook(): void
    {
        $hooked = new HookedWorkerPlugin('pkg.hooked');
        $this->instantiator->register('pkg.hooked', $hooked);
        $this->seedHooked($hooked);

        $this->manager->enable('pkg.hooked');

        self::assertSame(['onEnable'], $hooked->invoked);
    }

    #[Test]
    public function aThrowingEnableHookMarksThePluginFailed(): void
    {
        $hooked = new HookedWorkerPlugin('pkg.hooked', 'onEnable');
        $this->instantiator->register('pkg.hooked', $hooked);
        $this->seedHooked($hooked);

        $result = $this->manager->enable('pkg.hooked');

        self::assertTrue($result->isErr());
        self::assertSame(PluginState::Failed, $this->registry->get('pkg.hooked')->state());
        self::assertTrue($this->publisher->has(PluginFailed::class));
    }

    #[Test]
    public function aCleanPluginWithNoHooksTransitionsWithoutAFailureEvent(): void
    {
        // pkg.a resolves to the hookless ReferencePlugin (registered by seed()); the manager runs no
        // plugin code, so the only event is the transition itself — no PluginFailed leaks in.
        $this->seed('pkg.a');

        $result = $this->manager->enable('pkg.a');

        self::assertTrue($result->isOk());
        self::assertSame(['plugin.enabled'], $this->publisher->eventNames());
        self::assertFalse($this->publisher->has(PluginFailed::class));
    }

    #[Test]
    public function disableRunsTheOnDisableHook(): void
    {
        $hooked = new HookedWorkerPlugin('pkg.hooked');
        $this->instantiator->register('pkg.hooked', $hooked);
        $this->seedHooked($hooked);
        $this->manager->enable('pkg.hooked');

        $this->manager->disable('pkg.hooked');

        self::assertSame(['onEnable', 'onDisable'], $hooked->invoked);
    }

    /**
     * Seed a hooked plugin's registry record (state Installed) from its own manifest.
     */
    private function seedHooked(HookedWorkerPlugin $plugin): void
    {
        $record = RegisteredPlugin::install(
            PluginId::generate(),
            $plugin->manifest(),
            'array:' . $plugin->manifest()->name(),
            null,
            $this->clock,
        );
        $record->pullDomainEvents();
        $this->registry->register($record);
    }
}
