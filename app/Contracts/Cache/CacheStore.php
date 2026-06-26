<?php

declare(strict_types=1);

namespace App\Contracts\Cache;

use Closure;

/**
 * Contract for a cache backend. Application code depends on this interface only,
 * so the driver (file today; Redis/Memcached later) can be swapped via a single
 * container binding with zero caller changes (docs/47 EAS-10).
 */
interface CacheStore
{
    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value, ?int $ttlSeconds = null): bool;

    public function has(string $key): bool;

    public function forget(string $key): bool;

    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Return the cached value, or compute it via $callback, store it for $ttl
     * seconds, and return it.
     */
    public function remember(string $key, ?int $ttlSeconds, Closure $callback): mixed;

    public function increment(string $key, int $by = 1): int;

    public function flush(): bool;
}
