<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Recruitment\Domain\CandidateHealthScore;

/**
 * Gathers the Candidate Health Score components from data ALREADY collected — the
 * First Impression report (job-match / resume / social sub-scores), AI assessments
 * (fit + per-skill scores), AI interview scores, human interview ratings, learning
 * progress, the normalised profile fields, and platform activity — then delegates
 * the weighting to the pure {@see CandidateHealthScore}. No fresh AI is spent; a
 * signal that does not exist yet is simply omitted and the weights re-normalise.
 * Every query is workspace-scoped (tenant isolation is absolute).
 */
final class CandidateHealthService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{
     *     score: int, band: string,
     *     components: list<array{key: string, label: string, value: int|null, weight: int, contributing: bool}>
     * }
     */
    public function forCandidate(string $workspaceId, string $userId): array
    {
        $fi = $this->latestFirstImpression($workspaceId, $userId);

        return CandidateHealthScore::compute([
            'job_match' => $this->firstNonNull($fi['job_match_score'] ?? null, $this->maxFitScore($workspaceId, $userId)),
            'resume_quality' => $fi['resume_score'] ?? null,
            'experience' => $this->experience($workspaceId, $userId),
            'skills' => $this->skills($workspaceId, $userId),
            'learning' => $this->learning($workspaceId, $userId),
            'certifications' => $this->certifications($workspaceId, $userId),
            'interview_score' => $this->interviewScore($workspaceId, $userId),
            'human_evaluation' => $this->humanEvaluation($workspaceId, $userId),
            'social_credibility' => $fi['social_score'] ?? null,
            'activity' => $this->activity($workspaceId, $userId),
        ]);
    }

    /** @return array<string, mixed> the latest FI report sub-scores (or empty) */
    private function latestFirstImpression(string $workspaceId, string $userId): array
    {
        return $this->connection->selectOne(
            'SELECT job_match_score, resume_score, social_score FROM first_impression_reports
              WHERE workspace_id = ? AND candidate_user_id = ? ORDER BY created_at DESC LIMIT 1',
            [$workspaceId, $userId],
        ) ?? [];
    }

    private function maxFitScore(string $workspaceId, string $userId): ?float
    {
        $row = $this->connection->selectOne(
            'SELECT MAX(fit_score) AS s FROM candidate_assessments WHERE workspace_id = ? AND candidate_user_id = ?',
            [$workspaceId, $userId],
        );

        return isset($row['s']) && $row['s'] !== null ? (float) $row['s'] : null;
    }

    private function experience(string $workspaceId, string $userId): ?float
    {
        $row = $this->connection->selectOne(
            "SELECT field_value AS v FROM candidate_profile_fields
              WHERE workspace_id = ? AND user_id = ? AND field_key = 'years_experience' ORDER BY position LIMIT 1",
            [$workspaceId, $userId],
        );
        if ($row === null || ! is_numeric($row['v'])) {
            return null;
        }

        // ~10 years of experience saturates the component.
        return CandidateHealthScore::clamp((float) $row['v'] * 10.0);
    }

    private function skills(string $workspaceId, string $userId): ?float
    {
        // Prefer the AI assessment's per-skill scores (0-100 each); average them.
        $row = $this->connection->selectOne(
            'SELECT skills FROM candidate_assessments
              WHERE workspace_id = ? AND candidate_user_id = ? AND skills IS NOT NULL
              ORDER BY created_at DESC LIMIT 1',
            [$workspaceId, $userId],
        );
        if ($row !== null && $row['skills'] !== null) {
            $decoded = json_decode((string) $row['skills'], true);
            if (is_array($decoded) && $decoded !== []) {
                $scores = [];
                foreach ($decoded as $entry) {
                    if (is_array($entry) && isset($entry['score']) && is_numeric($entry['score'])) {
                        $scores[] = (float) $entry['score'];
                    } elseif (is_numeric($entry)) {
                        $scores[] = (float) $entry;
                    }
                }
                if ($scores !== []) {
                    return CandidateHealthScore::clamp(array_sum($scores) / count($scores));
                }
            }
        }

        // Fallback: breadth of listed skills (5+ distinct skills saturates).
        $count = $this->countField($workspaceId, $userId, 'skills');

        return $count === 0 ? null : CandidateHealthScore::clamp($count * 20.0);
    }

    private function learning(string $workspaceId, string $userId): ?float
    {
        $row = $this->connection->selectOne(
            'SELECT AVG(progress_percent) AS p FROM learning_enrollments WHERE workspace_id = ? AND user_id = ?',
            [$workspaceId, $userId],
        );

        return isset($row['p']) && $row['p'] !== null ? CandidateHealthScore::clamp((float) $row['p']) : null;
    }

    private function certifications(string $workspaceId, string $userId): ?float
    {
        $count = $this->countField($workspaceId, $userId, 'certifications');

        return $count === 0 ? null : CandidateHealthScore::clamp($count * 25.0);
    }

    private function interviewScore(string $workspaceId, string $userId): ?float
    {
        $row = $this->connection->selectOne(
            "SELECT AVG(score) AS s FROM interviews
              WHERE workspace_id = ? AND candidate_user_id = ? AND status = 'completed' AND score IS NOT NULL AND deleted_at IS NULL",
            [$workspaceId, $userId],
        );

        return isset($row['s']) && $row['s'] !== null ? CandidateHealthScore::clamp((float) $row['s']) : null;
    }

    private function humanEvaluation(string $workspaceId, string $userId): ?float
    {
        // interview_feedback.rating is 1-5; map to 0-100.
        $row = $this->connection->selectOne(
            'SELECT AVG(f.rating) AS r FROM interview_feedback f
               JOIN interviews i ON i.id = f.interview_id
              WHERE f.workspace_id = ? AND i.candidate_user_id = ?',
            [$workspaceId, $userId],
        );

        return isset($row['r']) && $row['r'] !== null ? CandidateHealthScore::clamp((float) $row['r'] * 20.0) : null;
    }

    private function activity(string $workspaceId, string $userId): ?float
    {
        $apps = (int) ($this->connection->selectOne('SELECT COUNT(*) AS c FROM applications WHERE workspace_id = ? AND user_id = ? AND deleted_at IS NULL', [$workspaceId, $userId])['c'] ?? 0);
        $ivs = (int) ($this->connection->selectOne('SELECT COUNT(*) AS c FROM interviews WHERE workspace_id = ? AND candidate_user_id = ? AND deleted_at IS NULL', [$workspaceId, $userId])['c'] ?? 0);
        $entries = (int) ($this->connection->selectOne('SELECT COUNT(*) AS c FROM candidate_timeline_entries WHERE workspace_id = ? AND user_id = ? AND deleted_at IS NULL', [$workspaceId, $userId])['c'] ?? 0);
        $total = $apps + $ivs + $entries;

        return $total === 0 ? null : CandidateHealthScore::clamp($total * 12.0);
    }

    private function countField(string $workspaceId, string $userId, string $fieldKey): int
    {
        return (int) ($this->connection->selectOne(
            "SELECT COUNT(*) AS c FROM candidate_profile_fields WHERE workspace_id = ? AND user_id = ? AND field_key = ? AND field_value <> ''",
            [$workspaceId, $userId, $fieldKey],
        )['c'] ?? 0);
    }

    private function firstNonNull(mixed ...$values): ?float
    {
        foreach ($values as $v) {
            if ($v !== null) {
                return (float) $v;
            }
        }

        return null;
    }
}
