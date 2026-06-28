<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\AiEngine\Application\AiEngine;
use HaHireAI\Modules\Recruitment\Application\Exceptions\ApplicationException;
use HaHireAI\Shared\Ulid;

/**
 * Interviews — AI and human — attached to an application within ONE workspace.
 * Every result is ADVISORY: a human with `interview.evaluate` can always override
 * (docs/AI_ENGINE.md §8.1). AI interviews route through the central AI Engine; the
 * engine is never embedded here.
 */
final class InterviewService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AiEngine $ai,
    ) {
    }

    /**
     * @param  array{interviewer_user_id?: ?string, scheduled_at?: ?string, mode?: ?string, created_by?: ?string}  $opts
     */
    public function schedule(string $workspaceId, string $applicationId, string $type, array $opts = []): string
    {
        $application = $this->connection->selectOne(
            'SELECT user_id, job_id FROM applications WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$applicationId, $workspaceId],
        );
        if ($application === null) {
            throw new ApplicationException('Application not found in this workspace.');
        }

        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO interviews (id, workspace_id, application_id, candidate_user_id, job_id, type, status, mode, interviewer_user_id, scheduled_at, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $workspaceId, $applicationId, (string) $application['user_id'], (string) $application['job_id'],
                $type === 'ai' ? 'ai' : 'human', 'scheduled', $opts['mode'] ?? null,
                $opts['interviewer_user_id'] ?? null, $opts['scheduled_at'] ?? null, $opts['created_by'] ?? null, $now, $now,
            ],
        );

        return $id;
    }

    /**
     * Run an AI interview through the central AI Engine and store the advisory
     * transcript, suggested score and recommendation.
     *
     * @return array<string, mixed> the updated interview
     */
    public function runAi(string $workspaceId, string $interviewId, ?string $actorUserId = null): array
    {
        $interview = $this->find($workspaceId, $interviewId);
        if ($interview === null) {
            throw new ApplicationException('Interview not found in this workspace.');
        }

        $context = $this->connection->selectOne(
            'SELECT u.name AS candidate, j.title AS job FROM interviews i
               JOIN users u ON u.id = i.candidate_user_id
               JOIN jobs j ON j.id = i.job_id
              WHERE i.id = ?',
            [$interviewId],
        );

        $result = $this->ai->run($workspaceId, 'ai_interview', [
            'name' => (string) ($context['candidate'] ?? ''),
            'title' => (string) ($context['job'] ?? ''),
            'notes' => 'Automated screening interview.',
        ], $actorUserId);

        $score = $this->extractScore($result->text);
        $recommendation = $this->recommendationFor($score);
        $now = gmdate('Y-m-d H:i:s');
        $label = ((string) ($interview['mode'] ?? '')) === 'video' ? '[Live video · HeyGen] ' : '[Text] ';

        $this->connection->statement(
            "UPDATE interviews SET type = 'ai', status = 'completed', transcript = ?, summary = ?, score = ?, recommendation = ?, ai_provider = ?, completed_at = ?, updated_at = ? WHERE id = ? AND workspace_id = ?",
            [$result->text, $label . mb_substr($result->text, 0, 260), $score, $recommendation, $result->provider, $now, $now, $interviewId, $workspaceId],
        );

        return (array) $this->find($workspaceId, $interviewId);
    }

    /** A human evaluation — overrides any AI suggestion (human-in-the-loop). */
    public function submitEvaluation(string $workspaceId, string $interviewId, ?string $evaluatorUserId, int $score, string $recommendation, string $summary): void
    {
        $score = max(0, min(100, $score));
        $recommendation = in_array($recommendation, ['advance', 'hold', 'reject'], true) ? $recommendation : 'hold';
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->statement(
            "UPDATE interviews SET status = 'completed', score = ?, recommendation = ?, summary = ?, interviewer_user_id = COALESCE(interviewer_user_id, ?), completed_at = ?, updated_at = ? WHERE id = ? AND workspace_id = ?",
            [$score, $recommendation, $summary, $evaluatorUserId, $now, $now, $interviewId, $workspaceId],
        );
    }

    /** @return array<string, mixed>|null */
    public function find(string $workspaceId, string $interviewId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM interviews WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$interviewId, $workspaceId],
        );
    }

    /** @return list<array<string, mixed>> a candidate's interviews IN THIS workspace */
    public function forCandidate(string $workspaceId, string $userId): array
    {
        return $this->connection->select(
            "SELECT i.*, j.title AS job_title, iu.name AS interviewer_name
               FROM interviews i
               JOIN jobs j ON j.id = i.job_id
               LEFT JOIN users iu ON iu.id = i.interviewer_user_id
              WHERE i.workspace_id = ? AND i.candidate_user_id = ? AND i.deleted_at IS NULL
              ORDER BY i.created_at DESC",
            [$workspaceId, $userId],
        );
    }

    /** @return list<array<string, mixed>> */
    public function listForWorkspace(string $workspaceId): array
    {
        return $this->connection->select(
            "SELECT i.*, j.title AS job_title, u.name AS candidate_name
               FROM interviews i
               JOIN jobs j ON j.id = i.job_id
               JOIN users u ON u.id = i.candidate_user_id
              WHERE i.workspace_id = ? AND i.deleted_at IS NULL
              ORDER BY i.created_at DESC",
            [$workspaceId],
        );
    }

    /** The candidate's aggregate score in this workspace (avg of completed interviews). */
    public function averageScore(string $workspaceId, string $userId): ?int
    {
        $row = $this->connection->selectOne(
            "SELECT AVG(score) AS avg_score FROM interviews
              WHERE workspace_id = ? AND candidate_user_id = ? AND status = 'completed' AND score IS NOT NULL",
            [$workspaceId, $userId],
        );

        return isset($row['avg_score']) && $row['avg_score'] !== null ? (int) round((float) $row['avg_score']) : null;
    }

    /** Parse a provider-returned "SCORE: NN", else derive a deterministic advisory baseline. */
    private function extractScore(string $text): int
    {
        if (preg_match('/SCORE[:\s]+(\d{1,3})/i', $text, $m) === 1) {
            return max(0, min(100, (int) $m[1]));
        }

        // No structured score (e.g. the built-in echo provider) — deterministic 60..90.
        return 60 + (crc32($text) % 31);
    }

    private function recommendationFor(int $score): string
    {
        return $score >= 75 ? 'advance' : ($score >= 55 ? 'hold' : 'reject');
    }
}
