<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin;

use Nizam\Platform\Plugin\Exception\PluginPermissionDeniedException;
use Nizam\Platform\Plugin\PermissionSet;
use Nizam\Platform\Plugin\PluginId;
use Nizam\Platform\Plugin\PluginPermission;
use Nizam\Platform\Plugin\RegisteredPlugin;
use Nizam\Platform\Plugin\Service\PluginPermissionGate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PluginPermissionGate}: a plugin acts only within its granted permissions.
 */
#[CoversClass(PluginPermissionGate::class)]
#[CoversClass(PermissionSet::class)]
final class PluginPermissionGateTest extends TestCase
{
    private PluginPermissionGate $gate;
    private RegisteredPlugin $plugin;

    protected function setUp(): void
    {
        $this->gate = new PluginPermissionGate();
        $this->plugin = RegisteredPlugin::install(
            PluginId::generate(),
            PluginTestFactory::manifest('pkg.a', '1.0.0'),
            'array:pkg.a@1.0.0',
            null,
            new FixedTestClock(),
        );
    }

    private function granted(string ...$keys): PermissionSet
    {
        return PermissionSet::of(array_map(static fn (string $k): PluginPermission => new PluginPermission($k), $keys));
    }

    #[Test]
    public function itAuthorizesAGrantedPermission(): void
    {
        $this->expectNotToPerformAssertions();

        $this->gate->authorize($this->plugin, 'tools.invoke', $this->granted('tools.invoke'));
    }

    #[Test]
    public function itDeniesAnUngrantedPermission(): void
    {
        $this->expectException(PluginPermissionDeniedException::class);
        $this->expectExceptionMessage('was not granted permission "tools.invoke"');

        $this->gate->authorize($this->plugin, 'tools.invoke', $this->granted('crm.read'));
    }

    #[Test]
    public function deniedExceptionCarriesTheStableCode(): void
    {
        try {
            $this->gate->authorize($this->plugin, 'tools.invoke', PermissionSet::empty());
            self::fail('Expected a permission-denied exception.');
        } catch (PluginPermissionDeniedException $exception) {
            self::assertSame(PluginPermissionDeniedException::CODE, $exception->errorCode());
        }
    }

    #[Test]
    public function allowsIsANonThrowingCompanion(): void
    {
        self::assertTrue($this->gate->allows('tools.invoke', $this->granted('tools.invoke')));
        self::assertFalse($this->gate->allows('tools.invoke', $this->granted('crm.read')));
    }

    #[Test]
    public function grantIsAuthoritativeNotTheManifestDeclaration(): void
    {
        // The plugin's manifest declares 'worker.execute', but only 'crm.read' was granted.
        $this->expectException(PluginPermissionDeniedException::class);

        $this->gate->authorize($this->plugin, 'worker.execute', $this->granted('crm.read'));
    }

    #[Test]
    public function permissionSetGrantsIsSupersetContainment(): void
    {
        $granted = $this->granted('a.read', 'a.write', 'a.delete');
        $required = $this->granted('a.read', 'a.write');

        self::assertTrue($granted->grants($required));
        self::assertFalse($required->grants($granted));
    }
}
