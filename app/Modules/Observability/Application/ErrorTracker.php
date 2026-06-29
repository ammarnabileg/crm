<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Observability\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Persists captured errors for the ops dashboard. Fed by the `system.error`
 * event the global ErrorHandler publishes (docs/OBSERVABILITY.md §4).
 */
final class ErrorTracker
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function record(array $event): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $class = isset($event['exception_class']) ? (string) $event['exception_class'] : null;
        $file = isset($event['file']) ? (string) $event['file'] : null;
        $line = isset($event['line']) ? (int) $event['line'] : null;

        $this->connection->statement(
            'INSERT INTO error_events (id, level, message, exception_class, file, line, fingerprint, context, workspace_id, occurred_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                (string) ($event['level'] ?? 'error'),
                mb_substr((string) ($event['message'] ?? ''), 0, 2000),
                $class,
                $file !== null ? mb_substr($file, 0, 500) : null,
                $line,
                substr(sha1(($class ?? '') . '|' . ($file ?? '') . '|' . ($line ?? '')), 0, 64),
                isset($event['context']) ? json_encode($event['context']) : null,
                isset($event['workspace_id']) ? (string) $event['workspace_id'] : null,
                $now,
                $now,
            ],
        );

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 25): array
    {
        $limit = max(1, min(200, $limit));

        // ULIDs are monotonic, so id is a deterministic tiebreaker within a second.
        return $this->connection->select(
            'SELECT id, level, message, exception_class, file, line, occurred_at FROM error_events ORDER BY occurred_at DESC, id DESC LIMIT ' . $limit,
        );
    }

    public function countSince(int $sinceTs): int
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS c FROM error_events WHERE occurred_at >= ?',
            [gmdate('Y-m-d H:i:s', $sinceTs)],
        );

        return (int) ($row['c'] ?? 0);
    }
}
