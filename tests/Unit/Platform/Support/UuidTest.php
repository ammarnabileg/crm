<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Support;

use Nizam\Platform\Exception\InvalidArgumentException;
use Nizam\Platform\Support\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Uuid::class)]
final class UuidTest extends TestCase
{
    public function testV7HasVersionNibbleSeven(): void
    {
        $uuid = Uuid::v7();

        // The 13th hex character (index 14 including hyphens) is the version nibble.
        self::assertSame('7', $uuid[14]);
    }

    public function testV7HasCorrectVariantBits(): void
    {
        $uuid = Uuid::v7();

        // The variant nibble (index 19) must be one of 8, 9, a, or b (0b10xx).
        self::assertContains(strtolower($uuid[19]), ['8', '9', 'a', 'b']);
    }

    public function testV7ProducesValidCanonicalForm(): void
    {
        $uuid = Uuid::v7();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid,
        );
        self::assertTrue(Uuid::isValid($uuid));
        self::assertTrue(Uuid::isV7($uuid));
    }

    public function testV7BytesAreSixteenBytes(): void
    {
        self::assertSame(16, strlen(Uuid::v7Bytes()));
    }

    public function testUniquenessAcrossManyGenerations(): void
    {
        $count = 5000;
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $ids[Uuid::v7()] = true;
        }

        self::assertCount($count, $ids, 'Expected all generated UUIDs to be unique.');
    }

    public function testTimeOrderingIsRoughlyMonotonic(): void
    {
        $first = Uuid::v7();
        usleep(2000);
        $second = Uuid::v7();

        // The leading timestamp segment means later ids sort after earlier ones.
        self::assertLessThan(0, strcmp($first, $second));
    }

    public function testIsValidRejectsGarbage(): void
    {
        self::assertFalse(Uuid::isValid('not-a-uuid'));
        self::assertFalse(Uuid::isValid(''));
        self::assertFalse(Uuid::isValid('018f1dd2-417e-7ee6-8a9c-82d359e1957'));
    }

    public function testFormatRejectsWrongByteLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Uuid::format('short');
    }
}
