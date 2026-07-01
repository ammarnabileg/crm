<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin;

use Nizam\Platform\Plugin\Exception\PluginManifestException;
use Nizam\Platform\Plugin\PluginDependency;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\PluginPermission;
use Nizam\Platform\Plugin\SemanticVersion;
use Nizam\Platform\Plugin\VersionConstraint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the self-validating {@see PluginManifest} descriptor and its array round-trip.
 */
#[CoversClass(PluginManifest::class)]
#[CoversClass(PluginPermission::class)]
#[CoversClass(PluginDependency::class)]
final class PluginManifestTest extends TestCase
{
    /**
     * A fully-populated, valid manifest array (the on-disk `plugin.json` shape).
     *
     * @return array<string, mixed>
     */
    private static function validArray(): array
    {
        return [
            'name' => 'acme.crm-sync',
            'displayName' => 'Acme CRM Sync',
            'version' => '2.1.0',
            'kind' => 'integration',
            'description' => 'Synchronises contacts with Acme.',
            'author' => 'Acme Corp',
            'license' => 'MIT',
            'entryPointClass' => 'Acme\\Plugin\\CrmSync',
            'platformConstraint' => '>=1.0.0 <2.0.0',
            'requiredPermissions' => [
                ['key' => 'crm.contacts.read', 'description' => 'Read contacts.'],
                ['key' => 'crm.contacts.write', 'description' => 'Write contacts.'],
            ],
            'requiredCapabilities' => ['http.client', 'queue'],
            'dependencies' => [
                ['plugin' => 'acme.core', 'constraint' => '^1.0', 'optional' => false],
                ['plugin' => 'acme.extras', 'constraint' => '*', 'optional' => true],
            ],
            'configSchema' => ['apiKey' => ['type' => 'string']],
            'healthCheckClass' => 'Acme\\Plugin\\CrmSyncHealth',
            'tags' => ['crm', 'sync'],
        ];
    }

    #[Test]
    public function itBuildsFromArray(): void
    {
        $manifest = PluginManifest::fromArray(self::validArray());

        self::assertSame('acme.crm-sync', $manifest->name());
        self::assertSame('Acme CRM Sync', $manifest->displayName());
        self::assertSame('2.1.0', (string) $manifest->version());
        self::assertSame(PluginKind::Integration, $manifest->kind());
        self::assertSame('Acme\\Plugin\\CrmSync', $manifest->entryPointClass());
        self::assertSame('Acme\\Plugin\\CrmSyncHealth', $manifest->healthCheckClass());
        self::assertSame(['http.client', 'queue'], $manifest->requiredCapabilities());
        self::assertSame(['crm', 'sync'], $manifest->tags());
        self::assertCount(2, $manifest->requiredPermissions());
        self::assertCount(2, $manifest->dependencies());
    }

    #[Test]
    public function itRoundTripsThroughToArray(): void
    {
        $manifest = PluginManifest::fromArray(self::validArray());

        $roundTripped = PluginManifest::fromArray($manifest->toArray());

        self::assertEquals($manifest->toArray(), $roundTripped->toArray());
        self::assertTrue($manifest->equals($roundTripped));
    }

    #[Test]
    public function toArrayProducesTheDeclaredShape(): void
    {
        $manifest = PluginManifest::fromArray(self::validArray());

        $array = $manifest->toArray();

        self::assertSame('acme.crm-sync', $array['name']);
        self::assertSame('integration', $array['kind']);
        self::assertSame('2.1.0', $array['version']);
        self::assertSame(
            [['key' => 'crm.contacts.read', 'description' => 'Read contacts.'],
                ['key' => 'crm.contacts.write', 'description' => 'Write contacts.']],
            $array['requiredPermissions'],
        );
        self::assertSame(
            [['plugin' => 'acme.core', 'constraint' => '^1.0', 'optional' => false],
                ['plugin' => 'acme.extras', 'constraint' => '*', 'optional' => true]],
            $array['dependencies'],
        );
    }

    #[Test]
    public function permissionsMayBeDeclaredAsBareStrings(): void
    {
        $data = self::validArray();
        $data['requiredPermissions'] = ['crm.contacts.read', 'crm.contacts.write'];

        $manifest = PluginManifest::fromArray($data);

        self::assertCount(2, $manifest->requiredPermissions());
        self::assertSame('crm.contacts.read', $manifest->requiredPermissions()[0]->key());
    }

    #[Test]
    public function createNormalisesLeadingBackslashOnEntryPoint(): void
    {
        $manifest = PluginManifest::create(
            name: 'x.y',
            displayName: 'X Y',
            version: SemanticVersion::of(1, 0, 0),
            kind: PluginKind::Worker,
            description: '',
            author: 'A',
            license: 'MIT',
            entryPointClass: '\\Acme\\Plugin\\Entry',
            platformConstraint: VersionConstraint::parse('*'),
        );

        self::assertSame('Acme\\Plugin\\Entry', $manifest->entryPointClass());
    }

    #[Test]
    public function requiredPermissionSetDeduplicatesByKey(): void
    {
        $manifest = PluginManifest::fromArray(self::validArray());

        self::assertSame(['crm.contacts.read', 'crm.contacts.write'], $manifest->requiredPermissionSet()->keys());
    }

    /**
     * @return list<array{string}>
     */
    public static function requiredFields(): array
    {
        return [['name'], ['displayName'], ['version'], ['kind'], ['entryPointClass'], ['author'], ['license'], ['platformConstraint']];
    }

    #[Test]
    #[DataProvider('requiredFields')]
    public function itThrowsWhenARequiredFieldIsMissing(string $field): void
    {
        $data = self::validArray();
        unset($data[$field]);

        $this->expectException(PluginManifestException::class);

        PluginManifest::fromArray($data);
    }

    #[Test]
    public function itThrowsOnUnknownKind(): void
    {
        $data = self::validArray();
        $data['kind'] = 'wizard';

        $this->expectException(PluginManifestException::class);
        $this->expectExceptionMessage('unknown kind');

        PluginManifest::fromArray($data);
    }

    #[Test]
    public function itThrowsOnMalformedName(): void
    {
        $data = self::validArray();
        $data['name'] = 'Acme CRM Sync';

        $this->expectException(PluginManifestException::class);
        $this->expectExceptionMessage('kebab or dotted');

        PluginManifest::fromArray($data);
    }

    #[Test]
    public function itThrowsOnInvalidPermissionKey(): void
    {
        $data = self::validArray();
        $data['requiredPermissions'] = ['Not A Key'];

        $this->expectException(PluginManifestException::class);

        PluginManifest::fromArray($data);
    }

    #[Test]
    public function itThrowsOnInvalidVersion(): void
    {
        $data = self::validArray();
        $data['version'] = '2.1';

        $this->expectException(\Nizam\Platform\Plugin\Exception\PluginValidationException::class);

        PluginManifest::fromArray($data);
    }

    #[Test]
    public function itThrowsWhenFieldHasWrongType(): void
    {
        $data = self::validArray();
        $data['requiredCapabilities'] = 'not-an-array';

        $this->expectException(PluginManifestException::class);

        PluginManifest::fromArray($data);
    }

    #[Test]
    public function equalityIsByNameAndVersion(): void
    {
        $a = PluginManifest::fromArray(self::validArray());
        $sameData = self::validArray();
        $sameData['displayName'] = 'A Different Display Name';
        $b = PluginManifest::fromArray($sameData);

        self::assertTrue($a->equals($b), 'Display name should not affect identity.');

        $olderData = self::validArray();
        $olderData['version'] = '2.0.0';
        self::assertFalse($a->equals(PluginManifest::fromArray($olderData)));
    }
}
