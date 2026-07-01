<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Contracts\RecruitmentSnapshot;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Recruitment\Domain\ApplicationStatus;

/**
 * Computes the Executive Dashboard KPIs for a workspace from real recruitment
 * data (no placeholders). All sources are Recruitment-owned tables, so this stays
 * within the module boundary; it is exposed to the Workspaces dashboard via the
 * RecruitmentSnapshot contract.
 */
final class DashboardService implements RecruitmentSnapshot
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function dashboard(string $workspaceId): array
    {
        $ws = [$workspaceId];

        $scalar = fn (string $sql, array $b = []): int => (int) (($this->connection->selectOne($sql, $b ?: $ws)['n'] ?? 0));

        $jobsTotal = $scalar('SELECT COUNT(*) n FROM jobs WHERE workspace_id = ? AND deleted_at IS NULL');
        $jobsOpen = $scalar("SELECT COUNT(*) n FROM jobs WHERE workspace_id = ? AND status = 'published' AND deleted_at IS NULL");
        $jobsClosed = $scalar("SELECT COUNT(*) n FROM jobs WHERE workspace_id = ? AND status IN ('closed','archived') AND deleted_at IS NULL");
        $applicants = $scalar('SELECT COUNT(*) n FROM applications WHERE workspace_id = ? AND deleted_at IS NULL');
        $employees = $scalar('SELECT COUNT(*) n FROM employees WHERE workspace_id = ?');
        $interviews = $scalar('SELECT COUNT(*) n FROM interviews WHERE workspace_id = ? AND deleted_at IS NULL');
        $offers = $scalar('SELECT COUNT(*) n FROM offers WHERE workspace_id = ? AND deleted_at IS NULL');
        $hires = $scalar("SELECT COUNT(*) n FROM applications WHERE workspace_id = ? AND status = 'hired' AND deleted_at IS NULL");

        // Needs attention: a strong AI screen (>=75) but the human hasn't moved it on.
        $needsAttention = $scalar(
            "SELECT COUNT(DISTINCT a.id) n
               FROM applications a
               JOIN interviews i ON i.application_id = a.id AND i.status = 'completed' AND i.score >= 75
              WHERE a.workspace_id = ? AND a.status IN ('applied','ai_screening') AND a.deleted_at IS NULL",
        );

        // Pipeline summary — applications by status (only non-zero stages).
        $byStatusRows = $this->connection->select(
            'SELECT status, COUNT(*) c FROM applications WHERE workspace_id = ? AND deleted_at IS NULL GROUP BY status',
            $ws,
        );
        $pipeline = [];
        foreach (ApplicationStatus::values() as $st) {
            $pipeline[$st] = 0;
        }
        foreach ($byStatusRows as $r) {
            $pipeline[(string) $r['status']] = (int) $r['c'];
        }

        $today = gmdate('Y-m-d');
        $todayInterviews = $this->connection->select(
            "SELECT i.id, i.type, i.mode, i.scheduled_at, u.name AS candidate_name, j.title AS job_title
               FROM interviews i
               JOIN users u ON u.id = i.candidate_user_id
               JOIN jobs j ON j.id = i.job_id
              WHERE i.workspace_id = ? AND i.deleted_at IS NULL AND DATE(i.scheduled_at) = ?
              ORDER BY i.scheduled_at ASC LIMIT 10",
            [$workspaceId, $today],
        );

        $recentJobs = $this->connection->select(
            'SELECT id, title, status, created_at,
                    (SELECT COUNT(*) FROM applications a WHERE a.job_id = jobs.id AND a.deleted_at IS NULL) AS applicants
               FROM jobs WHERE workspace_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5',
            $ws,
        );

        // Recent activity — latest applications (recruitment-owned event stream).
        $recentActivity = $this->connection->select(
            "SELECT a.id, a.status, a.applied_at, u.name AS candidate_name, j.title AS job_title
               FROM applications a
               JOIN users u ON u.id = a.user_id
               JOIN jobs j ON j.id = a.job_id
              WHERE a.workspace_id = ? AND a.deleted_at IS NULL
              ORDER BY a.applied_at DESC LIMIT 8",
            $ws,
        );

        $aiRecommendations = $this->connection->select(
            "SELECT ca.candidate_user_id, ca.fit_score, ca.recommendation, u.name AS candidate_name
               FROM candidate_assessments ca
               JOIN users u ON u.id = ca.candidate_user_id
              WHERE ca.workspace_id = ? AND ca.fit_score >= 70
              ORDER BY ca.created_at DESC LIMIT 6",
            $ws,
        );

        return [
            'counts' => [
                'employees' => $employees,
                'jobs_total' => $jobsTotal,
                'jobs_open' => $jobsOpen,
                'jobs_closed' => $jobsClosed,
                'applicants' => $applicants,
                'needs_attention' => $needsAttention,
            ],
            'funnel' => ['applications' => $applicants, 'interviews' => $interviews, 'offers' => $offers, 'hires' => $hires],
            'pipeline' => $pipeline,
            'today_interviews' => $todayInterviews,
            'recent_jobs' => $recentJobs,
            'recent_activity' => $recentActivity,
            'ai_recommendations' => $aiRecommendations,
            'health' => $this->health($jobsOpen, $applicants, $interviews, $needsAttention),
        ];
    }

    /** A simple, real workspace-health score from activity signals. */
    private function health(int $jobsOpen, int $applicants, int $interviews, int $needsAttention): array
    {
        $signals = [];
        $score = 100;
        if ($jobsOpen === 0) {
            $signals[] = 'No open jobs — publish a role to start hiring.';
            $score -= 30;
        }
        if ($applicants === 0) {
            $signals[] = 'No applicants yet.';
            $score -= 20;
        }
        if ($needsAttention > 0) {
            $signals[] = "{$needsAttention} qualified candidate(s) awaiting your decision.";
            $score -= min(25, $needsAttention * 5);
        }
        if ($applicants > 0 && $interviews === 0) {
            $signals[] = 'Applicants are in but no interviews yet.';
            $score -= 15;
        }

        return ['score' => max(0, $score), 'signals' => $signals];
    }
}
