<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Config;

use Nizam\Platform\Config\Env;
use Nizam\Platform\Exception\ConfigException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Env::class)]
final class EnvTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $touched = [];

    protected function tearDown(): void
    {
        foreach (array_keys($this->touched) as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }

        $this->touched = [];
    }

    private function put(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        $this->touched[$key] = true;
    }

    public function testCastsBooleanAndNullStrings(): void
    {
        $this->put('NZ_TRUE', 'true');
        $this->put('NZ_FALSE', 'false');
        $this->put('NZ_NULL', 'null');

        self::assertTrue(Env::get('NZ_TRUE'));
        self::assertFalse(Env::get('NZ_FALSE'));
        self::assertNull(Env::get('NZ_NULL'));
    }

    public function testStripsSurroundingQuotes(): void
    {
        $this->put('NZ_QUOTED', '"hello world"');

        self::assertSame('hello world', Env::get('NZ_QUOTED'));
    }

    public function testGetReturnsDefaultWhenUnset(): void
    {
        self::assertSame('default', Env::get('NZ_DEFINITELY_UNSET', 'default'));
    }

    public function testTypedReaders(): void
    {
        $this->put('NZ_INT', '8080');
        $this->put('NZ_BOOL', 'true');

        self::assertSame(8080, Env::int('NZ_INT'));
        self::assertSame(0, Env::int('NZ_MISSING_INT'));
        self::assertTrue(Env::bool('NZ_BOOL'));
        self::assertFalse(Env::bool('NZ_MISSING_BOOL'));
    }

    public function testRequiredReturnsValue(): void
    {
        $this->put('NZ_REQUIRED', 'present');

        self::assertSame('present', Env::required('NZ_REQUIRED'));
    }

    public function testRequiredThrowsWhenMissing(): void
    {
        $this->expectException(ConfigException::class);

        Env::required('NZ_REQUIRED_MISSING');
    }
}
