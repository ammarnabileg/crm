<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Application;

use HaHireAI\Core\Database\Connection;

/**
 * Read-only Learning analytics — the data behind the manager / instructor
 * dashboards. Pure aggregate queries, tenant-scoped by workspace_id. No writes.
 */
final class LearningAnalyticsService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Workspace-wide headline numbers.
     *
     * @return array{programs:int, published:int, enrollments:int, completed:int, in_progress:int, not_started:int, completion_rate:int, certificates:int, active_learners:int}
     */
    public function overview(string $workspaceId): array
    {
        $programs = (int) ($this->connection->selectOne('SELECT COUNT(*) c FROM learning_programs WHERE workspace_id = ? AND deleted_at IS NULL', [$workspaceId])['c'] ?? 0);
        $published = (int) ($this->connection->selectOne("SELECT COUNT(*) c FROM learning_programs WHERE workspace_id = ? AND status = 'published' AND deleted_at IS NULL", [$workspaceId])['c'] ?? 0);
        $row = $this->connection->selectOne(
            "SELECT
                COUNT(*) total,
                SUM(status = 'completed') completed,
                SUM(status = 'in_progress') in_progress,
                SUM(status = 'not_started') not_started,
                COUNT(DISTINCT user_id) learners
             FROM learning_enrollments WHERE workspace_id = ?",
            [$workspaceId],
        );
        $total = (int) ($row['total'] ?? 0);
        $completed = (int) ($row['completed'] ?? 0);
        $certs = (int) ($this->connection->selectOne('SELECT COUNT(*) c FROM learning_certificates WHERE workspace_id = ?', [$workspaceId])['c'] ?? 0);

        return [
            'programs' => $programs,
            'published' => $published,
            'enrollments' => $total,
            'completed' => $completed,
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'not_started' => (int) ($row['not_started'] ?? 0),
            'completion_rate' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'certificates' => $certs,
            'active_learners' => (int) ($row['learners'] ?? 0),
        ];
    }

    /**
     * Per-program roll-up (enrolled / completed / average progress).
     *
     * @return list<array<string,mixed>>
     */
    public function perProgram(string $workspaceId, int $limit = 100): array
    {
        $limit = max(1, min(300, $limit));

        return $this->connection->select(
            "SELECT p.id, p.title, p.status,
                    COUNT(e.id) enrolled,
                    SUM(e.status = 'completed') completed,
                    COALESCE(ROUND(AVG(e.progress_percent)), 0) avg_percent
               FROM learning_programs p
               LEFT JOIN learning_enrollments e ON e.program_id = p.id
              WHERE p.workspace_id = ? AND p.deleted_at IS NULL
              GROUP BY p.id, p.title, p.status
              ORDER BY enrolled DESC, p.title ASC
              LIMIT " . $limit,
            [$workspaceId],
        );
    }

    /**
     * Learners ranked by completed programs (the leaderboard).
     *
     * @return list<array<string,mixed>>
     */
    public function topLearners(string $workspaceId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        return $this->connection->select(
            "SELECT u.id, u.name,
                    COUNT(e.id) enrolled,
                    SUM(e.status = 'completed') completed,
                    COALESCE(ROUND(AVG(e.progress_percent)), 0) avg_percent
               FROM learning_enrollments e
               JOIN users u ON u.id = e.user_id
              WHERE e.workspace_id = ?
              GROUP BY u.id, u.name
              ORDER BY completed DESC, avg_percent DESC
              LIMIT " . $limit,
            [$workspaceId],
        );
    }

    /**
     * The programs a given author/instructor owns or edits, with their roll-up.
     *
     * @return list<array<string,mixed>>
     */
    public function instructorPrograms(string $workspaceId, string $userId): array
    {
        return $this->connection->select(
            "SELECT p.id, p.title, p.status,
                    COUNT(e.id) enrolled,
                    SUM(e.status = 'completed') completed,
                    COALESCE(ROUND(AVG(e.progress_percent)), 0) avg_percent
               FROM learning_programs p
               JOIN learning_program_editors ed ON ed.program_id = p.id AND ed.user_id = ?
               LEFT JOIN learning_enrollments e ON e.program_id = p.id
              WHERE p.workspace_id = ? AND p.deleted_at IS NULL
              GROUP BY p.id, p.title, p.status
              ORDER BY p.updated_at DESC",
            [$userId, $workspaceId],
        );
    }
}
