<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Contracts\Cache\CacheStore;
use Closure;

/**
 * In-memory cache store. Lives for one request/process — used as a request-level
 * cache and as the default store in tests (no filesystem needed).
 */
final class ArrayStore implements CacheStore
{
    /** @var array<string, array{value: mixed, expires: ?int}> */
    private array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (! isset($this->items[$key])) {
            return $default;
        }

        $item = $this->items[$key];
        if ($item['expires'] !== null && $item['expires'] <= time()) {
            unset($this->items[$key]);
            return $default;
        }

        return $item['value'];
    }

    public function put(string $key, mixed $value, ?int $ttlSeconds = null): bool
    {
        $this->items[$key] = [
            'value'   => $value,
            'expires' => $ttlSeconds !== null ? time() + $ttlSeconds : null,
        ];

        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__miss__') !== '__miss__';
    }

    public function forget(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function remember(string $key, ?int $ttlSeconds, Closure $callback): mixed
    {
        $value = $this->get($key, '__miss__');
        if ($value !== '__miss__') {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttlSeconds);

        return $value;
    }

    public function increment(string $key, int $by = 1): int
    {
        $current = (int) $this->get($key, 0);
        $new = $current + $by;
        $this->put($key, $new);

        return $new;
    }

    public function flush(): bool
    {
        $this->items = [];

        return true;
    }
}
