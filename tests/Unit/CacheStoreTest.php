<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Cache\CacheStore;
use App\Infrastructure\Cache\ArrayStore;
use App\Infrastructure\Cache\FileStore;
use Tests\TestCase;

/**
 * Exercises both cache drivers against the shared CacheStore contract, proving
 * they are interchangeable (docs/47 EAS-10).
 */
return new class extends TestCase {
    /** @return CacheStore[] */
    private function stores(): array
    {
        $dir = sys_get_temp_dir() . '/halaops-cache-test-' . getmypid();

        return [new ArrayStore(), new FileStore($dir)];
    }

    public function test_put_and_get(): void
    {
        foreach ($this->stores() as $store) {
            $store->flush();
            $store->put('k', 'v');
            $this->assertSame('v', $store->get('k'));
        }
    }

    public function test_get_missing_returns_default(): void
    {
        foreach ($this->stores() as $store) {
            $store->flush();
            $this->assertSame('fallback', $store->get('missing', 'fallback'));
        }
    }

    public function test_has_and_forget(): void
    {
        foreach ($this->stores() as $store) {
            $store->flush();
            $store->put('k', 1);
            $this->assertTrue($store->has('k'));
            $store->forget('k');
            $this->assertFalse($store->has('k'));
        }
    }

    public function test_expired_ttl_is_a_miss(): void
    {
        foreach ($this->stores() as $store) {
            $store->flush();
            $store->put('k', 'v', 0); // expires immediately
            $this->assertNull($store->get('k'));
        }
    }

    public function test_remember_computes_once_then_caches(): void
    {
        foreach ($this->stores() as $store) {
            $store->flush();
            $calls = 0;
            $compute = function () use (&$calls) {
                $calls++;
                return 'computed';
            };
            $this->assertSame('computed', $store->remember('k', 60, $compute));
            $this->assertSame('computed', $store->remember('k', 60, $compute));
            $this->assertSame(1, $calls);
        }
    }

    public function test_increment(): void
    {
        foreach ($this->stores() as $store) {
            $store->flush();
            $this->assertSame(1, $store->increment('n'));
            $this->assertSame(3, $store->increment('n', 2));
        }
    }

    public function test_pull_returns_and_removes(): void
    {
        foreach ($this->stores() as $store) {
            $store->flush();
            $store->put('k', 'v');
            $this->assertSame('v', $store->pull('k'));
            $this->assertFalse($store->has('k'));
        }
    }

    public function test_stores_arrays(): void
    {
        foreach ($this->stores() as $store) {
            $store->flush();
            $store->put('arr', ['a' => 1, 'b' => 2]);
            $this->assertSame(['a' => 1, 'b' => 2], $store->get('arr'));
        }
    }
};
