<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin;

use Nizam\Platform\Plugin\Exception\PluginValidationException;
use Nizam\Platform\Plugin\SemanticVersion;
use Nizam\Platform\Plugin\VersionBound;
use Nizam\Platform\Plugin\VersionConstraint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the {@see VersionConstraint} dialect (caret, tilde, ranges, x-ranges, wildcard).
 */
#[CoversClass(VersionConstraint::class)]
#[CoversClass(VersionBound::class)]
final class VersionConstraintTest extends TestCase
{
    /**
     * @return list<array{string, string, bool}>
     */
    public static function satisfactionCases(): array
    {
        return [
            // Wildcard.
            ['*', '0.0.1', true],
            ['*', '99.99.99', true],
            ['', '2.3.4', true],

            // Exact.
            ['1.2.3', '1.2.3', true],
            ['1.2.3', '1.2.4', false],
            ['=1.2.3', '1.2.3', true],

            // Caret (compatible-within-major).
            ['^1.2.3', '1.2.3', true],
            ['^1.2.3', '1.9.0', true],
            ['^1.2.3', '1.2.2', false],
            ['^1.2.3', '2.0.0', false],
            ['^1.2', '1.5.0', true],
            ['^1.2', '2.0.0', false],

            // Caret with zero major (within-minor).
            ['^0.2.3', '0.2.9', true],
            ['^0.2.3', '0.3.0', false],

            // Caret with zero major+minor (within-patch).
            ['^0.0.3', '0.0.3', true],
            ['^0.0.3', '0.0.4', false],

            // Tilde: ~1.2.3 and ~1.2 both allow patch-level changes only (>=x.y.0 <x.(y+1).0);
            // only ~1 (a single component) widens to the whole major.
            ['~1.2.3', '1.2.9', true],
            ['~1.2.3', '1.3.0', false],
            ['~1.2', '1.2.9', true],
            ['~1.2', '1.3.0', false],
            ['~1.2', '2.0.0', false],
            ['~1', '1.9.9', true],
            ['~1', '2.0.0', false],

            // Explicit ranges (AND).
            ['>=1.0.0 <2.0.0', '1.5.0', true],
            ['>=1.0.0 <2.0.0', '2.0.0', false],
            ['>=1.0.0 <2.0.0', '0.9.0', false],
            ['>1.0.0', '1.0.1', true],
            ['>1.0.0', '1.0.0', false],
            ['<=1.0.0', '1.0.0', true],
            ['<=1.0.0', '1.0.1', false],

            // X-ranges.
            ['1.x', '1.0.0', true],
            ['1.x', '1.9.9', true],
            ['1.x', '2.0.0', false],
            ['1.2.*', '1.2.5', true],
            ['1.2.*', '1.3.0', false],
        ];
    }

    #[Test]
    #[DataProvider('satisfactionCases')]
    public function itDecidesSatisfaction(string $constraint, string $version, bool $expected): void
    {
        $satisfied = VersionConstraint::parse($constraint)->satisfies(SemanticVersion::parse($version));

        self::assertSame($expected, $satisfied);
    }

    #[Test]
    public function stableRangeRejectsPreRelease(): void
    {
        $constraint = VersionConstraint::parse('>=1.0.0 <2.0.0');

        self::assertFalse($constraint->satisfies(SemanticVersion::parse('1.5.0-rc.1')));
    }

    #[Test]
    public function preReleaseIsAdmittedWhenBoundPinsSameCore(): void
    {
        $constraint = VersionConstraint::parse('>=1.5.0-alpha.1');

        self::assertTrue($constraint->satisfies(SemanticVersion::parse('1.5.0-alpha.2')));
    }

    #[Test]
    public function rawIsPreserved(): void
    {
        self::assertSame('^1.2.3', VersionConstraint::parse('^1.2.3')->raw());
        self::assertSame('^1.2.3', (string) VersionConstraint::parse('^1.2.3'));
        self::assertSame('*', VersionConstraint::parse('')->raw());
    }

    #[Test]
    public function equalityIsByNormalizedRaw(): void
    {
        self::assertTrue(VersionConstraint::parse('^1.0')->equals(VersionConstraint::parse('  ^1.0 ')));
        self::assertFalse(VersionConstraint::parse('^1.0')->equals(VersionConstraint::parse('~1.0')));
    }

    /**
     * @return list<array{string}>
     */
    public static function invalidConstraints(): array
    {
        return [
            ['^1.2.3.4'],
            ['~a.b.c'],
            ['1.2.3.4.5'],
            ['>=abc'],
        ];
    }

    #[Test]
    #[DataProvider('invalidConstraints')]
    public function itRejectsInvalidConstraints(string $constraint): void
    {
        $this->expectException(PluginValidationException::class);

        VersionConstraint::parse($constraint);
    }
}
