<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;

/**
 * Read side of the First Impression Engine: assembles a report and all its
 * normalised children (résumé sub-scores + evidence, social roll-up + per-source
 * snapshots + signals) for the Decision Center, the candidate's own read-only
 * insights page, and printing. No scoring here — purely loading.
 */
final class FirstImpressionReportService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $workspaceId, string $reportId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM first_impression_reports WHERE id = ? AND workspace_id = ?',
            [$reportId, $workspaceId],
        );
    }

    /** The most recent report for an application (the Decision Center view). */
    public function latestForApplication(string $workspaceId, string $applicationId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM first_impression_reports WHERE workspace_id = ? AND application_id = ? ORDER BY created_at DESC LIMIT 1',
            [$workspaceId, $applicationId],
        );
    }

    /** The most recent report for a candidate in this workspace. */
    public function latestForCandidate(string $workspaceId, string $candidateUserId): ?array
    {
        return $this->connection->selectOne(
            'SELECT r.*, j.title AS job_title FROM first_impression_reports r
               LEFT JOIN jobs j ON j.id = r.job_id
              WHERE r.workspace_id = ? AND r.candidate_user_id = ? ORDER BY r.created_at DESC LIMIT 1',
            [$workspaceId, $candidateUserId],
        );
    }

    /**
     * A report with every normalised child, ready for a view.
     *
     * @return array<string, mixed>|null
     */
    public function full(string $workspaceId, string $reportId): ?array
    {
        $report = $this->find($workspaceId, $reportId);
        if ($report === null) {
            return null;
        }

        $analysis = $this->connection->selectOne(
            'SELECT * FROM resume_analysis WHERE report_id = ? AND workspace_id = ?',
            [$reportId, $workspaceId],
        );

        $details = $this->connection->select(
            'SELECT kind, label, value, score FROM resume_analysis_details WHERE report_id = ? AND workspace_id = ? ORDER BY kind, position',
            [$reportId, $workspaceId],
        );
        $grouped = [];
        foreach ($details as $d) {
            $grouped[(string) $d['kind']][] = $d;
        }

        $social = $this->connection->selectOne(
            'SELECT * FROM social_analysis WHERE report_id = ? AND workspace_id = ?',
            [$reportId, $workspaceId],
        );
        $snapshots = [];
        if ($social !== null) {
            $snapshots = $this->connection->select(
                'SELECT * FROM social_profiles_snapshot WHERE social_analysis_id = ? AND workspace_id = ? ORDER BY created_at',
                [(string) $social['id'], $workspaceId],
            );
            foreach ($snapshots as $i => $snap) {
                $snapshots[$i]['signals'] = $this->connection->select(
                    'SELECT signal_key, string_value, numeric_value FROM social_signals_snapshot WHERE snapshot_id = ? AND workspace_id = ? ORDER BY id',
                    [(string) $snap['id'], $workspaceId],
                );
            }
        }

        return [
            'report' => $report,
            'analysis' => $analysis,
            'details' => $grouped,
            'social' => $social,
            'snapshots' => $snapshots,
        ];
    }

    /**
     * The candidate's OWN reports across every workspace — for their read-only
     * insights page. Scoped to the candidate themselves (they own their data),
     * newest first. Each refreshes with every new application.
     *
     * @return list<array<string, mixed>>
     */
    public function forCandidateGlobal(string $candidateUserId, int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));

        return $this->connection->select(
            'SELECT r.id, r.workspace_id, r.job_id, r.overall_score, r.resume_score, r.job_match_score, r.social_score,
                    r.passed, r.decision, r.confidence, r.created_at, j.title AS job_title, w.name AS workspace_name
               FROM first_impression_reports r
               LEFT JOIN jobs j ON j.id = r.job_id
               LEFT JOIN workspaces w ON w.id = r.workspace_id
              WHERE r.candidate_user_id = ? ORDER BY r.created_at DESC LIMIT ' . $limit,
            [$candidateUserId],
        );
    }

    /** Full report scoped to the owning candidate (for their read-only view). */
    public function fullForCandidate(string $candidateUserId, string $reportId): ?array
    {
        $report = $this->connection->selectOne(
            'SELECT workspace_id FROM first_impression_reports WHERE id = ? AND candidate_user_id = ?',
            [$reportId, $candidateUserId],
        );

        return $report === null ? null : $this->full((string) $report['workspace_id'], $reportId);
    }
}
