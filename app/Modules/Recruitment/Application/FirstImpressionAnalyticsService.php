<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;

/**
 * First Impression Analytics — workspace (and per-job) aggregates over the
 * stored reports: pass/filter counts, average score, score distribution, top
 * matched / missing skills, common weaknesses, the funnel, the interview
 * conversion rate, and the AI credits saved by NOT interviewing filtered
 * applicants. All read-only SQL over the normalised tables; no scoring here.
 */
final class FirstImpressionAnalyticsService
{
    /**
     * A conservative estimate of the model tokens one AI interview (room turns +
     * assessment) would consume, used to express the credits saved by filtering.
     */
    private const EST_TOKENS_PER_INTERVIEW = 9000;

    /** The AI Engine accounts cost at 1 cent per 1,000 tokens (see AiEngine). */
    private const CENTS_PER_1K_TOKENS = 1;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(string $workspaceId, ?string $jobId = null): array
    {
        $where = 'workspace_id = ?';
        $bind = [$workspaceId];
        if ($jobId !== null && $jobId !== '') {
            $where .= ' AND job_id = ?';
            $bind[] = $jobId;
        }

        $totals = $this->connection->selectOne(
            "SELECT COUNT(*) AS applicants,
                    SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) AS passed,
                    SUM(CASE WHEN passed = 0 THEN 1 ELSE 0 END) AS filtered,
                    SUM(CASE WHEN overridden = 1 THEN 1 ELSE 0 END) AS overridden,
                    AVG(overall_score) AS avg_score,
                    AVG(resume_score) AS avg_resume,
                    AVG(job_match_score) AS avg_job_match
               FROM first_impression_reports WHERE {$where}",
            $bind,
        ) ?? [];

        $applicants = (int) ($totals['applicants'] ?? 0);
        $passed = (int) ($totals['passed'] ?? 0);
        $filtered = (int) ($totals['filtered'] ?? 0);

        // Score distribution in 20-point bands.
        $distribution = $this->connection->select(
            "SELECT FLOOR(LEAST(overall_score, 99) / 20) AS band, COUNT(*) AS c
               FROM first_impression_reports WHERE {$where} GROUP BY band ORDER BY band",
            $bind,
        );
        $bands = ['0–19' => 0, '20–39' => 0, '40–59' => 0, '60–79' => 0, '80–100' => 0];
        $bandKeys = array_keys($bands);
        foreach ($distribution as $row) {
            $idx = min(4, (int) $row['band']);
            $bands[$bandKeys[$idx]] = (int) $row['c'];
        }

        $estTokens = $filtered * self::EST_TOKENS_PER_INTERVIEW;

        return [
            'applicants' => $applicants,
            'passed' => $passed,
            'filtered' => $filtered,
            'overridden' => (int) ($totals['overridden'] ?? 0),
            'avg_score' => (int) round((float) ($totals['avg_score'] ?? 0)),
            'avg_resume' => (int) round((float) ($totals['avg_resume'] ?? 0)),
            'avg_job_match' => (int) round((float) ($totals['avg_job_match'] ?? 0)),
            'pass_rate' => $applicants > 0 ? (int) round($passed / $applicants * 100) : 0,
            'conversion_rate' => $applicants > 0 ? (int) round($passed / $applicants * 100) : 0,
            'distribution' => $bands,
            'top_matched' => $this->topDetails($workspaceId, $jobId, 'skill_matched', 10),
            'top_missing' => $this->topDetails($workspaceId, $jobId, 'skill_missing', 10),
            'top_weaknesses' => $this->topDetails($workspaceId, $jobId, 'weakness', 8),
            'funnel' => [
                ['label' => 'Applied', 'value' => $applicants],
                ['label' => 'Passed first impression', 'value' => $passed],
                ['label' => 'Filtered before AI', 'value' => $filtered],
            ],
            'ai_interviews_avoided' => $filtered,
            'est_tokens_saved' => $estTokens,
            'est_cost_cents_saved' => (int) round($estTokens / 1000 * self::CENTS_PER_1K_TOKENS),
            'by_month' => $this->byMonth($workspaceId, $jobId),
            'jobs' => $this->jobsWithReports($workspaceId),
            'selected_job' => $jobId,
        ];
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    private function topDetails(string $workspaceId, ?string $jobId, string $kind, int $limit): array
    {
        $where = 'd.workspace_id = ? AND d.kind = ?';
        $bind = [$workspaceId, $kind];
        if ($jobId !== null && $jobId !== '') {
            $where .= ' AND d.report_id IN (SELECT id FROM first_impression_reports WHERE workspace_id = ? AND job_id = ?)';
            $bind[] = $workspaceId;
            $bind[] = $jobId;
        }
        $rows = $this->connection->select(
            "SELECT d.label, COUNT(*) AS c FROM resume_analysis_details d
              WHERE {$where} GROUP BY d.label ORDER BY c DESC, d.label LIMIT " . max(1, min(50, $limit)),
            $bind,
        );

        return array_map(static fn (array $r): array => ['label' => (string) $r['label'], 'count' => (int) $r['c']], $rows);
    }

    /**
     * @return list<array{month: string, applicants: int, passed: int, filtered: int, avg_score: int}>
     */
    private function byMonth(string $workspaceId, ?string $jobId): array
    {
        $where = 'workspace_id = ?';
        $bind = [$workspaceId];
        if ($jobId !== null && $jobId !== '') {
            $where .= ' AND job_id = ?';
            $bind[] = $jobId;
        }
        $rows = $this->connection->select(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COUNT(*) AS applicants,
                    SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) AS passed,
                    SUM(CASE WHEN passed = 0 THEN 1 ELSE 0 END) AS filtered,
                    AVG(overall_score) AS avg_score
               FROM first_impression_reports WHERE {$where}
              GROUP BY month ORDER BY month DESC LIMIT 12",
            $bind,
        );

        return array_map(static fn (array $r): array => [
            'month' => (string) $r['month'],
            'applicants' => (int) $r['applicants'],
            'passed' => (int) $r['passed'],
            'filtered' => (int) $r['filtered'],
            'avg_score' => (int) round((float) $r['avg_score']),
        ], $rows);
    }

    /** @return list<array{id: string, title: string}> jobs that have reports (for the filter) */
    private function jobsWithReports(string $workspaceId): array
    {
        $rows = $this->connection->select(
            'SELECT DISTINCT r.job_id AS id, j.title FROM first_impression_reports r
               JOIN jobs j ON j.id = r.job_id WHERE r.workspace_id = ? ORDER BY j.title',
            [$workspaceId],
        );

        return array_map(static fn (array $r): array => ['id' => (string) $r['id'], 'title' => (string) ($r['title'] ?? 'Job')], $rows);
    }
}
