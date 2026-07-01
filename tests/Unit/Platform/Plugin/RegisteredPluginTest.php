<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\Event\PluginDisabled;
use Nizam\Platform\Plugin\Event\PluginEnabled;
use Nizam\Platform\Plugin\Event\PluginFailed;
use Nizam\Platform\Plugin\Event\PluginInstalled;
use Nizam\Platform\Plugin\Event\PluginUninstalled;
use Nizam\Platform\Plugin\Event\PluginUpdated;
use Nizam\Platform\Plugin\Exception\PluginStateException;
use Nizam\Platform\Plugin\PluginId;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\PluginState;
use Nizam\Platform\Plugin\RegisteredPlugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the {@see RegisteredPlugin} lifecycle state machine and its recorded events.
 */
#[CoversClass(RegisteredPlugin::class)]
#[CoversClass(PluginInstalled::class)]
#[CoversClass(PluginEnabled::class)]
#[CoversClass(PluginDisabled::class)]
#[CoversClass(PluginUpdated::class)]
#[CoversClass(PluginUninstalled::class)]
#[CoversClass(PluginFailed::class)]
final class RegisteredPluginTest extends TestCase
{
    private FixedTestClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FixedTestClock();
    }

    private function install(?TenantId $tenantId = null): RegisteredPlugin
    {
        return RegisteredPlugin::install(
            PluginId::generate(),
            PluginTestFactory::manifest('pkg.a', '1.0.0'),
            'array:pkg.a@1.0.0',
            $tenantId,
            $this->clock,
        );
    }

    #[Test]
    public function installStartsInstalledAndRecordsEvent(): void
    {
        $plugin = $this->install();

        self::assertSame(PluginState::Installed, $plugin->state());
        self::assertFalse($plugin->isEnabled());
        self::assertTrue($plugin->isGlobal());

        $events = $plugin->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PluginInstalled::class, $events[0]);
        self::assertSame('plugin.installed', $events[0]->eventName());
    }

    #[Test]
    public function enableTransitionsAndRecordsEvent(): void
    {
        $plugin = $this->install();
        $plugin->pullDomainEvents();

        $plugin->enable($this->clock);

        self::assertSame(PluginState::Enabled, $plugin->state());
        self::assertTrue($plugin->isEnabled());
        self::assertNotNull($plugin->enabledAt());

        $events = $plugin->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PluginEnabled::class, $events[0]);
    }

    #[Test]
    public function disableIsLegalOnlyFromEnabled(): void
    {
        $plugin = $this->install();
        $plugin->enable($this->clock);
        $plugin->pullDomainEvents();

        $plugin->disable($this->clock);

        self::assertSame(PluginState::Disabled, $plugin->state());
        self::assertInstanceOf(PluginDisabled::class, $plugin->pullDomainEvents()[0]);
    }

    #[Test]
    public function disableFromInstalledIsIllegal(): void
    {
        $plugin = $this->install();

        $this->expectException(PluginStateException::class);
        $this->expectExceptionMessage('Cannot disable');

        $plugin->disable($this->clock);
    }

    #[Test]
    public function reEnableAfterDisableClearsAndRecords(): void
    {
        $plugin = $this->install();
        $plugin->enable($this->clock);
        $plugin->disable($this->clock);
        $plugin->pullDomainEvents();

        $plugin->enable($this->clock);

        self::assertSame(PluginState::Enabled, $plugin->state());
        self::assertInstanceOf(PluginEnabled::class, $plugin->pullDomainEvents()[0]);
    }

    #[Test]
    public function markFailedThenEnableClearsFailure(): void
    {
        $plugin = $this->install();
        $plugin->markFailed('boom', $this->clock);
        self::assertSame(PluginState::Failed, $plugin->state());
        self::assertSame('boom', $plugin->failureReason());
        self::assertInstanceOf(PluginFailed::class, $plugin->pullDomainEvents()[1]);

        $plugin->enable($this->clock);

        self::assertSame(PluginState::Enabled, $plugin->state());
        self::assertNull($plugin->failureReason());
    }

    #[Test]
    public function markFailedRequiresAReason(): void
    {
        $plugin = $this->install();

        $this->expectException(\Nizam\Platform\Exception\InvalidArgumentException::class);

        $plugin->markFailed('', $this->clock);
    }

    #[Test]
    public function markIncompatibleRecordsAFailedEvent(): void
    {
        $plugin = $this->install();
        $plugin->pullDomainEvents();

        $plugin->markIncompatible('needs platform 2', $this->clock);

        self::assertSame(PluginState::Incompatible, $plugin->state());
        self::assertInstanceOf(PluginFailed::class, $plugin->pullDomainEvents()[0]);
    }

    #[Test]
    public function updateToNewerVersionAdoptsManifestAndRecordsEvent(): void
    {
        $plugin = $this->install();
        $plugin->pullDomainEvents();
        $newer = PluginTestFactory::manifest('pkg.a', '1.1.0');

        $plugin->update($newer, 'array:pkg.a@1.1.0', $this->clock);

        self::assertSame('1.1.0', (string) $plugin->manifest()->version());
        $events = $plugin->pullDomainEvents();
        self::assertInstanceOf(PluginUpdated::class, $events[0]);
        self::assertSame('1.0.0', $events[0]->previousVersion());
        self::assertSame('1.1.0', $events[0]->newVersion());
    }

    #[Test]
    public function updateToNonNewerVersionIsIllegal(): void
    {
        $plugin = $this->install();
        $same = PluginTestFactory::manifest('pkg.a', '1.0.0');

        $this->expectException(PluginStateException::class);

        $plugin->update($same, 'array:pkg.a@1.0.0', $this->clock);
    }

    #[Test]
    public function updateToADifferentNameIsIllegal(): void
    {
        $plugin = $this->install();
        $other = PluginTestFactory::manifest('pkg.b', '2.0.0');

        $this->expectException(PluginStateException::class);

        $plugin->update($other, 'array:pkg.b@2.0.0', $this->clock);
    }

    #[Test]
    public function updatePreservesEnabledState(): void
    {
        $plugin = $this->install();
        $plugin->enable($this->clock);
        $plugin->pullDomainEvents();

        $plugin->update(PluginTestFactory::manifest('pkg.a', '1.2.0'), 'array:pkg.a@1.2.0', $this->clock);

        self::assertSame(PluginState::Enabled, $plugin->state());
    }

    #[Test]
    public function uninstallIsTerminalAndRecordsEvent(): void
    {
        $plugin = $this->install();
        $plugin->enable($this->clock);
        $plugin->pullDomainEvents();

        $plugin->uninstall($this->clock);

        self::assertSame(PluginState::Uninstalled, $plugin->state());
        self::assertNull($plugin->enabledAt());
        self::assertInstanceOf(PluginUninstalled::class, $plugin->pullDomainEvents()[0]);
    }

    #[Test]
    public function everyTransitionFromUninstalledIsIllegal(): void
    {
        $plugin = $this->install();
        $plugin->uninstall($this->clock);
        $plugin->pullDomainEvents();

        $this->expectException(PluginStateException::class);

        $plugin->enable($this->clock);
    }

    #[Test]
    public function uninstallTwiceIsIllegal(): void
    {
        $plugin = $this->install();
        $plugin->uninstall($this->clock);

        $this->expectException(PluginStateException::class);

        $plugin->uninstall($this->clock);
    }

    #[Test]
    public function eachMutationAdvancesTheVersion(): void
    {
        $plugin = $this->install();
        self::assertSame(0, $plugin->version());

        $plugin->enable($this->clock);
        self::assertSame(1, $plugin->version());

        $plugin->disable($this->clock);
        self::assertSame(2, $plugin->version());
    }

    #[Test]
    public function eventsCarryTheOwningTenant(): void
    {
        $tenant = TenantId::generate();
        $plugin = $this->install($tenant);

        self::assertFalse($plugin->isGlobal());
        $events = $plugin->pullDomainEvents();
        self::assertInstanceOf(PluginInstalled::class, $events[0]);
        self::assertNotNull($events[0]->tenantId());
        self::assertTrue($tenant->equals($events[0]->tenantId()));
    }

    #[Test]
    public function reconstituteRecordsNoEvents(): void
    {
        $original = $this->install();
        $manifest = $original->manifest();

        $rehydrated = RegisteredPlugin::reconstitute(
            $original->pluginId(),
            $manifest,
            PluginState::Enabled,
            null,
            'array:pkg.a@1.0.0',
            $this->clock->now(),
            $this->clock->now(),
            $this->clock->now(),
            null,
            null,
            3,
        );

        self::assertFalse($rehydrated->hasRecordedEvents());
        self::assertSame(3, $rehydrated->version());
        self::assertSame(PluginState::Enabled, $rehydrated->state());
    }

    #[Test]
    public function manifestFactoryProducesReferenceEntryPoint(): void
    {
        self::assertInstanceOf(
            PluginManifest::class,
            PluginTestFactory::manifest(),
        );
    }
}
