<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Fixed-window rate limiter backed by the `rate_limits` table, shared across
 * web processes. One row per (key, window); each hit increments atomically via
 * INSERT … ON DUPLICATE KEY UPDATE (docs/INTEGRATION_PLATFORM.md §4).
 */
final class RateLimiter
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Register one hit against $key and report the outcome for this window.
     *
     * @return array{allowed: bool, limit: int, remaining: int, retry_after: int}
     */
    public function hit(string $key, int $limit, int $windowSeconds = 60): array
    {
        $now = time();
        $windowStart = $now - ($now % $windowSeconds);
        $bucket = $key . ':' . $windowStart;
        $windowStartAt = gmdate('Y-m-d H:i:s', $windowStart);

        $this->connection->statement(
            'INSERT INTO rate_limits (id, bucket, hits, window_start, created_at)
             VALUES (?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE hits = hits + 1',
            [Ulid::generate(), $bucket, $windowStartAt, gmdate('Y-m-d H:i:s', $now)],
        );

        $row = $this->connection->selectOne('SELECT hits FROM rate_limits WHERE bucket = ?', [$bucket]);
        $hits = (int) ($row['hits'] ?? 1);
        $remaining = max(0, $limit - $hits);

        return [
            'allowed' => $hits <= $limit,
            'limit' => $limit,
            'remaining' => $remaining,
            'retry_after' => $hits <= $limit ? 0 : ($windowStart + $windowSeconds - $now),
        ];
    }

    /** Best-effort cleanup of windows older than the cutoff (ops/cron). */
    public function purgeOlderThan(int $seconds = 3600): void
    {
        $this->connection->statement(
            'DELETE FROM rate_limits WHERE window_start < ?',
            [gmdate('Y-m-d H:i:s', time() - $seconds)],
        );
    }
}
