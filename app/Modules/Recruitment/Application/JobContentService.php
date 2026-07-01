<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * A job's owned content: the interview question bank (spec #4) and the evaluation
 * criteria / rubric (spec #2). Both are workspace DATA created by the workspace's
 * own people — never hardcoded — and are workspace-scoped.
 */
final class JobContentService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array<string,mixed>> */
    public function questions(string $workspaceId, string $jobId): array
    {
        return $this->connection->select(
            'SELECT * FROM job_questions WHERE workspace_id = ? AND job_id = ? ORDER BY position ASC, created_at ASC',
            [$workspaceId, $jobId],
        );
    }

    /** @return list<string> just the question texts — fed to the AI interview room. */
    public function questionTexts(string $workspaceId, string $jobId): array
    {
        return array_map(static fn (array $r): string => (string) $r['text'], $this->questions($workspaceId, $jobId));
    }

    public function addQuestion(string $workspaceId, string $jobId, string $text): string
    {
        $text = trim($text);
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $pos = \HaHireAI\Support\Position::next($this->connection, 'job_questions', ['job_id' => $jobId]);
        $this->connection->statement(
            'INSERT INTO job_questions (id, workspace_id, job_id, text, position, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $jobId, $text, $pos, $now, $now],
        );

        return $id;
    }

    public function removeQuestion(string $workspaceId, string $questionId): void
    {
        $this->connection->statement('DELETE FROM job_questions WHERE id = ? AND workspace_id = ?', [$questionId, $workspaceId]);
    }

    /** @return list<array<string,mixed>> */
    public function criteria(string $workspaceId, string $jobId): array
    {
        return $this->connection->select(
            'SELECT * FROM job_criteria WHERE workspace_id = ? AND job_id = ? ORDER BY position ASC, created_at ASC',
            [$workspaceId, $jobId],
        );
    }

    public function addCriterion(string $workspaceId, string $jobId, string $label, int $weight): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $weight = max(1, min(100, $weight));
        $pos = \HaHireAI\Support\Position::next($this->connection, 'job_criteria', ['job_id' => $jobId]);
        $this->connection->statement(
            'INSERT INTO job_criteria (id, workspace_id, job_id, label, weight, position, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $jobId, trim($label), $weight, $pos, $now, $now],
        );

        return $id;
    }

    public function removeCriterion(string $workspaceId, string $criterionId): void
    {
        $this->connection->statement('DELETE FROM job_criteria WHERE id = ? AND workspace_id = ?', [$criterionId, $workspaceId]);
    }
}
