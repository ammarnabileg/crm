<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Contracts\Cache\CacheStore;
use Closure;

/**
 * File-backed cache store — the default driver. Each key is a JSON file under
 * storage/cache holding the serialized value and an expiry timestamp. No Redis
 * dependency, so it runs on any shared host; swappable for Redis later via the
 * CacheStore binding.
 */
final class FileStore implements CacheStore
{
    public function __construct(private readonly string $path)
    {
        if (! is_dir($this->path)) {
            @mkdir($this->path, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $record = $this->read($key);

        return $record === null ? $default : $record['value'];
    }

    public function put(string $key, mixed $value, ?int $ttlSeconds = null): bool
    {
        $payload = [
            'value'   => $value,
            'expires' => $ttlSeconds !== null ? time() + $ttlSeconds : null,
        ];

        return @file_put_contents(
            $this->file($key),
            serialize($payload),
            LOCK_EX
        ) !== false;
    }

    public function has(string $key): bool
    {
        return $this->read($key) !== null;
    }

    public function forget(string $key): bool
    {
        $file = $this->file($key);
        if (is_file($file)) {
            return @unlink($file);
        }

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
        $record = $this->read($key);
        if ($record !== null) {
            return $record['value'];
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
        foreach (glob($this->path . '/cache_*.dat') ?: [] as $file) {
            @unlink($file);
        }

        return true;
    }

    /**
     * @return array{value: mixed, expires: ?int}|null
     */
    private function read(string $key): ?array
    {
        $file = $this->file($key);
        if (! is_file($file)) {
            return null;
        }

        $payload = @file_get_contents($file);
        if ($payload === false) {
            return null;
        }

        $record = @unserialize($payload, ['allowed_classes' => false]);
        if (! is_array($record) || ! array_key_exists('value', $record)) {
            return null;
        }

        if ($record['expires'] !== null && $record['expires'] <= time()) {
            @unlink($file);
            return null;
        }

        return $record;
    }

    private function file(string $key): string
    {
        return $this->path . '/cache_' . sha1($key) . '.dat';
    }
}
