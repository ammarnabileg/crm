<?php

declare(strict_types=1);

namespace Nizam\Tests\Integration\Plugin;

use DateTimeImmutable;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\HealthLevel;
use Nizam\Platform\Plugin\Infrastructure\Migration\SqliteSchema;
use Nizam\Platform\Plugin\Infrastructure\Persistence\Pdo\PdoPluginRepository;
use Nizam\Platform\Plugin\Infrastructure\Persistence\Pdo\PluginHydrator;
use Nizam\Platform\Plugin\Infrastructure\Testing\ReferencePlugin;
use Nizam\Platform\Plugin\PluginHealthStatus;
use Nizam\Platform\Plugin\PluginId;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\PluginState;
use Nizam\Platform\Plugin\RegisteredPlugin;
use Nizam\Platform\Plugin\SemanticVersion;
use Nizam\Platform\Plugin\VersionConstraint;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for {@see PdoPluginRepository} against an in-memory SQLite database.
 *
 * Exercises the full round-trip through {@see SqliteSchema} and {@see PluginHydrator}: persisting a
 * plugin aggregate, reloading it by id and by name, filtering by kind and enabled state, tenant vs
 * global scoping, optimistic-version persistence, health-column mapping, and the soft-delete of an
 * uninstalled plugin.
 */
#[CoversClass(PdoPluginRepository::class)]
#[CoversClass(PluginHydrator::class)]
#[CoversClass(SqliteSchema::class)]
final class PdoPluginRepositoryTest extends TestCase
{
    private PDO $connection;
    private PdoPluginRepository $repository;
    private Clock $clock;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        SqliteSchema::apply($this->connection);

        $this->repository = new PdoPluginRepository($this->connection, new PluginHydrator());
        $this->clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-01T12:00:00+00:00');
            }
        };
    }

    private function install(string $name, ?TenantId $tenantId = null, PluginKind $kind = PluginKind::Worker): RegisteredPlugin
    {
        return RegisteredPlugin::install(
            PluginId::generate(),
            $this->manifest($name, $kind),
            'array:' . $name,
            $tenantId,
            $this->clock,
        );
    }

    private function manifest(string $name, PluginKind $kind): PluginManifest
    {
        $entryPoint = $kind === PluginKind::Worker
            ? ReferencePlugin::class
            : \Nizam\Tests\Unit\Platform\Plugin\Fixture\ToolOnlyPlugin::class;

        return PluginManifest::create(
            name: $name,
            displayName: 'Plugin ' . $name,
            version: SemanticVersion::of(1, 0, 0),
            kind: $kind,
            description: 'Integration test plugin.',
            author: 'Nizam Platform',
            license: 'MIT',
            entryPointClass: $entryPoint,
            platformConstraint: VersionConstraint::parse('>=1.0.0'),
        );
    }

    #[Test]
    public function itRoundTripsAPluginById(): void
    {
        $plugin = $this->install('pkg.a');
        $this->repository->save($plugin);

        $loaded = $this->repository->ofId($plugin->pluginId());

        self::assertNotNull($loaded);
        self::assertSame('pkg.a', $loaded->name());
        self::assertSame(PluginState::Installed, $loaded->state());
        self::assertSame('1.0.0', (string) $loaded->manifest()->version());
        self::assertEquals($plugin->manifest()->toArray(), $loaded->manifest()->toArray());
    }

    #[Test]
    public function itRoundTripsByName(): void
    {
        $this->repository->save($this->install('pkg.a'));

        self::assertNotNull($this->repository->ofName('pkg.a'));
        self::assertNull($this->repository->ofName('pkg.absent'));
    }

    #[Test]
    public function saveUpdatesInPlace(): void
    {
        $plugin = $this->install('pkg.a');
        $this->repository->save($plugin);

        $plugin->enable($this->clock);
        $this->repository->save($plugin);

        $loaded = $this->repository->ofId($plugin->pluginId());
        self::assertNotNull($loaded);
        self::assertSame(PluginState::Enabled, $loaded->state());
        self::assertSame(1, $loaded->version());
        self::assertCount(1, $this->repository->all());
    }

    #[Test]
    public function byKindFilters(): void
    {
        $this->repository->save($this->install('pkg.worker', null, PluginKind::Worker));
        $this->repository->save($this->install('pkg.tool', null, PluginKind::Tool));

        self::assertCount(1, $this->repository->byKind(PluginKind::Worker));
        self::assertCount(1, $this->repository->byKind(PluginKind::Tool));
        self::assertSame('pkg.worker', $this->repository->byKind(PluginKind::Worker)[0]->name());
    }

    #[Test]
    public function enabledFilters(): void
    {
        $on = $this->install('pkg.on');
        $on->enable($this->clock);
        $this->repository->save($on);
        $this->repository->save($this->install('pkg.off'));

        $enabled = $this->repository->enabled();

        self::assertCount(1, $enabled);
        self::assertSame('pkg.on', $enabled[0]->name());
    }

    #[Test]
    public function tenantAndGlobalScopingRoundTrip(): void
    {
        $tenant = TenantId::generate();
        $tenantScoped = $this->install('pkg.tenant', $tenant);
        $global = $this->install('pkg.global', null);
        $this->repository->save($tenantScoped);
        $this->repository->save($global);

        $loadedTenant = $this->repository->ofName('pkg.tenant');
        $loadedGlobal = $this->repository->ofName('pkg.global');

        self::assertNotNull($loadedTenant);
        self::assertNotNull($loadedGlobal);
        self::assertFalse($loadedTenant->isGlobal());
        self::assertNotNull($loadedTenant->tenantId());
        self::assertTrue($tenant->equals($loadedTenant->tenantId()));
        self::assertTrue($loadedGlobal->isGlobal());
        self::assertNull($loadedGlobal->tenantId());
    }

    #[Test]
    public function healthStatusRoundTrips(): void
    {
        $plugin = $this->install('pkg.a');
        $plugin->recordHealth(PluginHealthStatus::degraded(
            new DateTimeImmutable('2026-07-01T13:00:00+00:00'),
            'Slow upstream',
        ));
        $this->repository->save($plugin);

        $loaded = $this->repository->ofId($plugin->pluginId());

        self::assertNotNull($loaded);
        self::assertNotNull($loaded->lastHealth());
        self::assertSame(HealthLevel::Degraded, $loaded->lastHealth()->level());
        self::assertSame('Slow upstream', $loaded->lastHealth()->message());
    }

    #[Test]
    public function failureReasonRoundTrips(): void
    {
        $plugin = $this->install('pkg.a');
        $plugin->markFailed('exploded', $this->clock);
        $this->repository->save($plugin);

        $loaded = $this->repository->ofId($plugin->pluginId());

        self::assertNotNull($loaded);
        self::assertSame(PluginState::Failed, $loaded->state());
        self::assertSame('exploded', $loaded->failureReason());
    }

    #[Test]
    public function uninstalledPluginIsSoftDeletedFromLiveLookupsButAddressableById(): void
    {
        $plugin = $this->install('pkg.a');
        $this->repository->save($plugin);
        $plugin->uninstall($this->clock);
        $this->repository->save($plugin);

        self::assertNull($this->repository->ofName('pkg.a'));
        self::assertSame([], $this->repository->all());
        self::assertNotNull($this->repository->ofId($plugin->pluginId()));
        self::assertSame(PluginState::Uninstalled, $this->repository->ofId($plugin->pluginId())->state());
    }

    #[Test]
    public function nameIsUniqueAmongLiveRows(): void
    {
        $this->repository->save($this->install('pkg.a'));

        // A second live plugin sharing the name must violate the partial unique index.
        $this->expectException(\PDOException::class);

        $this->repository->save($this->install('pkg.a'));
    }

    #[Test]
    public function allIsOrderedByName(): void
    {
        $this->repository->save($this->install('pkg.c'));
        $this->repository->save($this->install('pkg.a'));
        $this->repository->save($this->install('pkg.b'));

        $names = array_map(static fn (RegisteredPlugin $p): string => $p->name(), $this->repository->all());

        self::assertSame(['pkg.a', 'pkg.b', 'pkg.c'], $names);
    }
}
