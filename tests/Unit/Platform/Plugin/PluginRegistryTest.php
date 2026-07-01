<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin;

use Nizam\Platform\Plugin\Exception\PluginNotFoundException;
use Nizam\Platform\Plugin\Infrastructure\Persistence\InMemory\InMemoryPluginRepository;
use Nizam\Platform\Plugin\Manager\PluginRegistry;
use Nizam\Platform\Plugin\PluginId;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\RegisteredPlugin;
use Nizam\Platform\Plugin\SemanticVersion;
use Nizam\Platform\Plugin\VersionConstraint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PluginRegistry}: lookups by name/kind/enabled over the repository port.
 */
#[CoversClass(PluginRegistry::class)]
#[CoversClass(InMemoryPluginRepository::class)]
final class PluginRegistryTest extends TestCase
{
    private InMemoryPluginRepository $repository;
    private PluginRegistry $registry;
    private FixedTestClock $clock;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPluginRepository();
        $this->registry = new PluginRegistry($this->repository);
        $this->clock = new FixedTestClock();
    }

    private function worker(string $name): RegisteredPlugin
    {
        return RegisteredPlugin::install(
            PluginId::generate(),
            PluginTestFactory::manifest($name, '1.0.0'),
            'array:' . $name,
            null,
            $this->clock,
        );
    }

    private function tool(string $name): RegisteredPlugin
    {
        $manifest = PluginManifest::create(
            name: $name,
            displayName: 'Tool ' . $name,
            version: SemanticVersion::of(1, 0, 0),
            kind: PluginKind::Tool,
            description: '',
            author: 'Test',
            license: 'MIT',
            entryPointClass: \Nizam\Tests\Unit\Platform\Plugin\Fixture\ToolOnlyPlugin::class,
            platformConstraint: VersionConstraint::parse('*'),
        );

        return RegisteredPlugin::install(PluginId::generate(), $manifest, 'array:' . $name, null, $this->clock);
    }

    #[Test]
    public function registerAndFind(): void
    {
        $plugin = $this->worker('pkg.a');
        $this->registry->register($plugin);

        self::assertTrue($this->registry->has('pkg.a'));
        self::assertNotNull($this->registry->find('pkg.a'));
        self::assertSame($plugin->pluginId()->toString(), $this->registry->get('pkg.a')->pluginId()->toString());
    }

    #[Test]
    public function findReturnsNullForUnknown(): void
    {
        self::assertNull($this->registry->find('pkg.absent'));
        self::assertFalse($this->registry->has('pkg.absent'));
    }

    #[Test]
    public function getThrowsForUnknown(): void
    {
        $this->expectException(PluginNotFoundException::class);
        $this->expectExceptionMessage('No registered plugin named "pkg.absent"');

        $this->registry->get('pkg.absent');
    }

    #[Test]
    public function byKindFiltersByCapability(): void
    {
        $this->registry->register($this->worker('pkg.worker-a'));
        $this->registry->register($this->worker('pkg.worker-b'));
        $this->registry->register($this->tool('pkg.tool-a'));

        self::assertCount(2, $this->registry->byKind(PluginKind::Worker));
        self::assertCount(1, $this->registry->byKind(PluginKind::Tool));
        self::assertCount(0, $this->registry->byKind(PluginKind::Manager));
    }

    #[Test]
    public function enabledReturnsOnlyEnabledPlugins(): void
    {
        $enabled = $this->worker('pkg.on');
        $enabled->enable($this->clock);
        $this->registry->register($enabled);
        $this->registry->register($this->worker('pkg.off'));

        $result = $this->registry->enabled();

        self::assertCount(1, $result);
        self::assertSame('pkg.on', $result[0]->name());
    }

    #[Test]
    public function allExcludesUninstalledPlugins(): void
    {
        $this->registry->register($this->worker('pkg.live'));
        $gone = $this->worker('pkg.gone');
        $gone->uninstall($this->clock);
        $this->registry->register($gone);

        $all = $this->registry->all();

        self::assertCount(1, $all);
        self::assertSame('pkg.live', $all[0]->name());
        self::assertFalse($this->registry->has('pkg.gone'));
    }

    #[Test]
    public function uninstalledPluginStaysAddressableById(): void
    {
        $gone = $this->worker('pkg.gone');
        $gone->uninstall($this->clock);
        $this->registry->register($gone);

        self::assertNotNull($this->repository->ofId($gone->pluginId()));
    }
}
