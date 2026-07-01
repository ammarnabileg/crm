<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin;

use Nizam\Platform\Plugin\Exception\PluginValidationException;
use Nizam\Platform\Plugin\SemanticVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the strict SemVer 2.0.0 value object {@see SemanticVersion}.
 */
#[CoversClass(SemanticVersion::class)]
final class SemanticVersionTest extends TestCase
{
    #[Test]
    public function itParsesCoreComponents(): void
    {
        $version = SemanticVersion::parse('1.2.3');

        self::assertSame(1, $version->major());
        self::assertSame(2, $version->minor());
        self::assertSame(3, $version->patch());
        self::assertSame([], $version->preRelease());
        self::assertSame([], $version->buildMetadata());
        self::assertFalse($version->isPreRelease());
        self::assertSame('1.2.3', (string) $version);
    }

    #[Test]
    public function itParsesPreReleaseAndBuildMetadata(): void
    {
        $version = SemanticVersion::parse('2.0.0-rc.1+build.7');

        self::assertSame(['rc', '1'], $version->preRelease());
        self::assertSame(['build', '7'], $version->buildMetadata());
        self::assertTrue($version->isPreRelease());
        self::assertSame('2.0.0-rc.1+build.7', (string) $version);
    }

    #[Test]
    public function itTrimsSurroundingWhitespaceWhenParsing(): void
    {
        $version = SemanticVersion::parse('  1.0.0  ');

        self::assertSame('1.0.0', (string) $version);
    }

    #[Test]
    public function ofBuildsAReleaseVersion(): void
    {
        $version = SemanticVersion::of(3, 4, 5);

        self::assertSame('3.4.5', (string) $version);
        self::assertFalse($version->isPreRelease());
    }

    /**
     * @return list<array{string}>
     */
    public static function invalidVersions(): array
    {
        return [
            ['1.2'],
            ['1'],
            ['v1.2.3'],
            ['1.2.3.4'],
            ['01.2.3'],
            ['1.02.3'],
            ['1.2.03'],
            ['1.2.x'],
            [''],
            ['1.2.3-'],
            ['not-a-version'],
        ];
    }

    #[Test]
    #[DataProvider('invalidVersions')]
    public function itRejectsInvalidVersions(string $version): void
    {
        $this->expectException(PluginValidationException::class);
        $this->expectExceptionMessage('not a valid semantic version');

        SemanticVersion::parse($version);
    }

    #[Test]
    public function ofRejectsNegativeComponents(): void
    {
        $this->expectException(PluginValidationException::class);

        SemanticVersion::of(-1, 0, 0);
    }

    /**
     * @return list<array{string, string, int}>
     */
    public static function comparisons(): array
    {
        return [
            'major dominates' => ['2.0.0', '1.9.9', 1],
            'minor dominates' => ['1.2.0', '1.1.9', 1],
            'patch dominates' => ['1.1.2', '1.1.1', 1],
            'equal cores' => ['1.2.3', '1.2.3', 0],
            'lower is negative' => ['1.0.0', '1.0.1', -1],
            'pre-release lower than release' => ['1.0.0-alpha', '1.0.0', -1],
            'release higher than pre-release' => ['1.0.0', '1.0.0-alpha', 1],
            'numeric identifiers compare numerically' => ['1.0.0-alpha.2', '1.0.0-alpha.11', -1],
            'numeric ranks below alphanumeric' => ['1.0.0-alpha.1', '1.0.0-alpha.beta', -1],
            'longer pre-release wins when prefix equal' => ['1.0.0-alpha.1', '1.0.0-alpha', 1],
            'alphabetical pre-release order' => ['1.0.0-alpha', '1.0.0-beta', -1],
        ];
    }

    #[Test]
    #[DataProvider('comparisons')]
    public function itComparesByPrecedence(string $left, string $right, int $expectedSign): void
    {
        $comparison = SemanticVersion::parse($left)->compareTo(SemanticVersion::parse($right));

        self::assertSame($expectedSign, $comparison <=> 0);
    }

    #[Test]
    public function itFollowsTheSemverPrecedenceExample(): void
    {
        // From the SemVer 2.0.0 spec §11.
        $ordered = [
            '1.0.0-alpha',
            '1.0.0-alpha.1',
            '1.0.0-alpha.beta',
            '1.0.0-beta',
            '1.0.0-beta.2',
            '1.0.0-beta.11',
            '1.0.0-rc.1',
            '1.0.0',
        ];

        for ($i = 1, $count = count($ordered); $i < $count; $i++) {
            $lower = SemanticVersion::parse($ordered[$i - 1]);
            $higher = SemanticVersion::parse($ordered[$i]);

            self::assertTrue(
                $higher->isGreaterThan($lower),
                sprintf('%s should outrank %s', $ordered[$i], $ordered[$i - 1]),
            );
            self::assertTrue($lower->isLessThan($higher));
        }
    }

    #[Test]
    public function buildMetadataDoesNotAffectPrecedence(): void
    {
        $a = SemanticVersion::parse('1.0.0+build.1');
        $b = SemanticVersion::parse('1.0.0+build.999');

        self::assertSame(0, $a->compareTo($b));
        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function equalsIsFalseAcrossDifferentPrecedence(): void
    {
        self::assertFalse(SemanticVersion::parse('1.0.0')->equals(SemanticVersion::parse('1.0.1')));
    }
}
