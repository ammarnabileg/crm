<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Config;

use Nizam\Platform\Config\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    private function config(): Config
    {
        return new Config([
            'app' => [
                'name' => 'Nizam',
                'timezone' => 'UTC',
            ],
            'logging' => [
                'channels' => [
                    'app' => ['level' => 'debug'],
                ],
            ],
            'nullable' => null,
        ]);
    }

    public function testGetReadsNestedDottedKey(): void
    {
        self::assertSame('Nizam', $this->config()->get('app.name'));
        self::assertSame('debug', $this->config()->get('logging.channels.app.level'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        self::assertSame('fallback', $this->config()->get('app.missing', 'fallback'));
        self::assertNull($this->config()->get('does.not.exist'));
    }

    public function testGetReturnsSubtreeArray(): void
    {
        self::assertSame(['name' => 'Nizam', 'timezone' => 'UTC'], $this->config()->get('app'));
    }

    public function testHasDistinguishesNullFromAbsent(): void
    {
        $config = $this->config();

        self::assertTrue($config->has('nullable'));
        self::assertNull($config->get('nullable'));
        self::assertFalse($config->has('absent'));
        self::assertTrue($config->has('app.timezone'));
    }

    public function testSetCreatesIntermediateArrays(): void
    {
        $config = $this->config();
        $config->set('cache.stores.redis.host', '127.0.0.1');

        self::assertSame('127.0.0.1', $config->get('cache.stores.redis.host'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $config = $this->config();
        $config->set('app.name', 'Renamed');

        self::assertSame('Renamed', $config->get('app.name'));
    }

    public function testAllReturnsWholeTree(): void
    {
        self::assertArrayHasKey('app', $this->config()->all());
    }
}
