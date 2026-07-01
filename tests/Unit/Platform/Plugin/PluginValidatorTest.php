<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin;

use Nizam\Platform\Plugin\Exception\PluginValidationException;
use Nizam\Platform\Plugin\Infrastructure\Testing\ReferencePlugin;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\SemanticVersion;
use Nizam\Platform\Plugin\Service\PluginValidator;
use Nizam\Platform\Plugin\VersionConstraint;
use Nizam\Tests\Unit\Platform\Plugin\Fixture\EchoHealthCheck;
use Nizam\Tests\Unit\Platform\Plugin\Fixture\ToolOnlyPlugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PluginValidator}: contract reflection, platform compatibility, well-formedness.
 */
#[CoversClass(PluginValidator::class)]
final class PluginValidatorTest extends TestCase
{
    private PluginValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PluginValidator();
    }

    #[Test]
    public function itAcceptsAValidManifest(): void
    {
        $result = $this->validator->validate(ReferencePlugin::manifestDescriptor(), '1.4.0');

        self::assertTrue($result->isOk());
        self::assertInstanceOf(PluginManifest::class, $result->value());
    }

    #[Test]
    public function itRejectsWhenEntryPointDoesNotImplementKindContract(): void
    {
        // Declares Worker but points at a Tool-only class.
        $manifest = PluginManifest::create(
            name: 'test.mismatch',
            displayName: 'Mismatch',
            version: SemanticVersion::of(1, 0, 0),
            kind: PluginKind::Worker,
            description: '',
            author: 'Test',
            license: 'MIT',
            entryPointClass: ToolOnlyPlugin::class,
            platformConstraint: VersionConstraint::parse('*'),
        );

        $result = $this->validator->validate($manifest, '1.0.0');

        self::assertTrue($result->isErr());
        self::assertSame(PluginValidationException::CODE, $result->errorCode());
        self::assertStringContainsString('kind contract', (string) $result->errorMessage());
    }

    #[Test]
    public function itRejectsAMissingEntryPointClass(): void
    {
        $manifest = PluginManifest::create(
            name: 'test.ghost',
            displayName: 'Ghost',
            version: SemanticVersion::of(1, 0, 0),
            kind: PluginKind::Worker,
            description: '',
            author: 'Test',
            license: 'MIT',
            entryPointClass: 'Nizam\\Nonexistent\\GhostPlugin',
            platformConstraint: VersionConstraint::parse('*'),
        );

        $result = $this->validator->validate($manifest, '1.0.0');

        self::assertTrue($result->isErr());
        self::assertStringContainsString('does not exist', (string) $result->errorMessage());
    }

    #[Test]
    public function itRejectsAnIncompatiblePlatformVersion(): void
    {
        $manifest = PluginManifest::create(
            name: 'test.needs-two',
            displayName: 'Needs Two',
            version: SemanticVersion::of(1, 0, 0),
            kind: PluginKind::Worker,
            description: '',
            author: 'Test',
            license: 'MIT',
            entryPointClass: ReferencePlugin::class,
            platformConstraint: VersionConstraint::parse('>=2.0.0'),
        );

        $result = $this->validator->validate($manifest, '1.5.0');

        self::assertTrue($result->isErr());
        self::assertStringContainsString('requires platform', (string) $result->errorMessage());
    }

    #[Test]
    public function itRejectsAnUnparsablePlatformVersion(): void
    {
        $result = $this->validator->validate(ReferencePlugin::manifestDescriptor(), 'not-a-version');

        self::assertTrue($result->isErr());
        self::assertStringContainsString('not a valid platform version', (string) $result->errorMessage());
    }

    #[Test]
    public function itAcceptsAValidHealthCheckClass(): void
    {
        $manifest = PluginManifest::create(
            name: 'test.healthy',
            displayName: 'Healthy',
            version: SemanticVersion::of(1, 0, 0),
            kind: PluginKind::Worker,
            description: '',
            author: 'Test',
            license: 'MIT',
            entryPointClass: ReferencePlugin::class,
            platformConstraint: VersionConstraint::parse('*'),
            healthCheckClass: EchoHealthCheck::class,
        );

        self::assertTrue($this->validator->validate($manifest, '1.0.0')->isOk());
    }

    #[Test]
    public function itRejectsAHealthCheckClassThatDoesNotImplementThePort(): void
    {
        $manifest = PluginManifest::create(
            name: 'test.bad-health',
            displayName: 'Bad Health',
            version: SemanticVersion::of(1, 0, 0),
            kind: PluginKind::Worker,
            description: '',
            author: 'Test',
            license: 'MIT',
            entryPointClass: ReferencePlugin::class,
            platformConstraint: VersionConstraint::parse('*'),
            healthCheckClass: ToolOnlyPlugin::class,
        );

        $result = $this->validator->validate($manifest, '1.0.0');

        self::assertTrue($result->isErr());
        self::assertStringContainsString('must implement', (string) $result->errorMessage());
    }
}
