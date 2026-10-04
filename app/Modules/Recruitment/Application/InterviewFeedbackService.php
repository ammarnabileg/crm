<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Candidate feedback on the AI interview experience (recruitment spec #17).
 * One rating (1–5) + optional comment per interview; surfaced to the hiring team.
 */
final class InterviewFeedbackService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function record(string $workspaceId, string $interviewId, int $rating, ?string $comment = null): void
    {
        $rating = max(1, min(5, $rating));
        $this->connection->statement(
            'INSERT IGNORE INTO interview_feedback (id, workspace_id, interview_id, rating, comment, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $interviewId, $rating, $comment, gmdate('Y-m-d H:i:s')],
        );
    }

    public function exists(string $interviewId): bool
    {
        return $this->connection->selectOne('SELECT id FROM interview_feedback WHERE interview_id = ?', [$interviewId]) !== null;
    }

    /** @return array<string,mixed>|null */
    public function forInterview(string $interviewId): ?array
    {
        return $this->connection->selectOne('SELECT rating, comment, created_at FROM interview_feedback WHERE interview_id = ?', [$interviewId]);
    }

    /** @return array{count: int, average: float} */
    public function summary(string $workspaceId): array
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS c, COALESCE(AVG(rating), 0) AS a FROM interview_feedback WHERE workspace_id = ?',
            [$workspaceId],
        );

        return ['count' => (int) ($row['c'] ?? 0), 'average' => round((float) ($row['a'] ?? 0), 2)];
    }
}
