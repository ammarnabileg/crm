<?php

declare(strict_types=1);

namespace App\Support;

/**
 * File-backed fixed-window rate limiter. No Redis dependency, so it runs on any
 * shared host. Each key maps to a small JSON file recording the hit count and
 * the window expiry timestamp.
 */
final class RateLimiter
{
    public function __construct(private readonly string $cachePath)
    {
        if (! is_dir($this->cachePath)) {
            @mkdir($this->cachePath, 0775, true);
        }
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds): int
    {
        $record = $this->read($key);
        $now = time();

        if ($record === null || $record['expires_at'] <= $now) {
            $record = ['count' => 0, 'expires_at' => $now + $decaySeconds];
        }

        $record['count']++;
        $this->write($key, $record);

        return $record['count'];
    }

    public function attempts(string $key): int
    {
        $record = $this->read($key);

        if ($record === null || $record['expires_at'] <= time()) {
            return 0;
        }

        return $record['count'];
    }

    public function availableIn(string $key): int
    {
        $record = $this->read($key);

        if ($record === null) {
            return 0;
        }

        return max(0, $record['expires_at'] - time());
    }

    public function clear(string $key): void
    {
        $file = $this->path($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function read(string $key): ?array
    {
        $file = $this->path($key);
        if (! is_file($file)) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) && isset($data['count'], $data['expires_at']) ? $data : null;
    }

    private function write(string $key, array $record): void
    {
        @file_put_contents($this->path($key), json_encode($record), LOCK_EX);
    }

    private function path(string $key): string
    {
        return $this->cachePath . '/rl_' . sha1($key) . '.json';
    }
}
