<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;

/**
 * The candidate side of the User↔Workspace relationship (the mirror of a
 * Membership). A User is a *candidate* in a Workspace when they have applied to a
 * job there — independent of any role. This service answers "which workspaces am
 * I a candidate in?" and builds the Candidate Portal overview, always
 * workspace-scoped for privacy (a Microsoft profile ≠ a Google profile).
 */
final class CandidacyService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Workspaces this user has applied to (their candidate workspaces), newest
     * application first.
     *
     * @return list<array<string, mixed>>
     */
    public function workspacesForCandidate(string $userId): array
    {
        // A workspace where the user holds a role is *not* a candidate workspace —
        // staff see the staff sidebar, candidates see the portal (never both).
        return $this->connection->select(
            "SELECT w.id, w.name, w.slug,
                    COUNT(a.id) AS applications_count,
                    MAX(a.applied_at) AS last_applied_at
               FROM applications a
               JOIN workspaces w ON w.id = a.workspace_id AND w.deleted_at IS NULL
              WHERE a.user_id = ? AND a.deleted_at IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM memberships m
                     WHERE m.workspace_id = w.id AND m.user_id = a.user_id AND m.deleted_at IS NULL
                )
              GROUP BY w.id, w.name, w.slug
              ORDER BY last_applied_at DESC",
            [$userId],
        );
    }

    /** True if the user is a candidate (applied, holds no role) in this workspace. */
    public function isCandidate(string $workspaceId, string $userId): bool
    {
        return $this->connection->selectOne(
            'SELECT 1 AS x FROM applications a
              WHERE a.workspace_id = ? AND a.user_id = ? AND a.deleted_at IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM memberships m
                     WHERE m.workspace_id = a.workspace_id AND m.user_id = a.user_id AND m.deleted_at IS NULL
                )
              LIMIT 1',
            [$workspaceId, $userId],
        ) !== null;
    }

    /**
     * The Candidate Portal overview for one workspace: application-status counts,
     * interview summary, offer summary, latest open jobs, and recent activity.
     *
     * @return array<string, mixed>
     */
    public function overview(string $workspaceId, string $userId): array
    {
        $applications = $this->connection->select(
            "SELECT a.id, a.status, a.applied_at, j.title AS job_title, s.name AS stage
               FROM applications a
               JOIN jobs j ON j.id = a.job_id
               LEFT JOIN pipeline_stages s ON s.id = a.current_stage_id
              WHERE a.workspace_id = ? AND a.user_id = ? AND a.deleted_at IS NULL
              ORDER BY a.applied_at DESC",
            [$workspaceId, $userId],
        );

        $statusCounts = [];
        foreach ($applications as $a) {
            $key = (string) $a['status'];
            $statusCounts[$key] = ($statusCounts[$key] ?? 0) + 1;
        }

        $interviews = $this->connection->select(
            "SELECT i.id, i.type, i.mode, i.status, i.scheduled_at, i.meeting_link, i.score, j.title AS job_title
               FROM interviews i
               JOIN jobs j ON j.id = i.job_id
              WHERE i.workspace_id = ? AND i.candidate_user_id = ? AND i.deleted_at IS NULL
              ORDER BY (i.scheduled_at IS NULL), i.scheduled_at ASC, i.created_at DESC
              LIMIT 10",
            [$workspaceId, $userId],
        );

        $offers = $this->connection->select(
            "SELECT o.id, o.title, o.salary, o.currency, o.status, o.proposed_by, o.created_at, j.title AS job_title
               FROM offers o
               JOIN applications a ON a.id = o.application_id
               JOIN jobs j ON j.id = a.job_id
              WHERE o.workspace_id = ? AND a.user_id = ? AND o.deleted_at IS NULL
              ORDER BY o.created_at DESC
              LIMIT 10",
            [$workspaceId, $userId],
        );

        $latestJobs = $this->connection->select(
            "SELECT id, title, location, employment_type, published_at
               FROM jobs
              WHERE workspace_id = ? AND status = 'published' AND deleted_at IS NULL
              ORDER BY published_at DESC, created_at DESC
              LIMIT 5",
            [$workspaceId],
        );

        return [
            'applications' => $applications,
            'status_counts' => $statusCounts,
            'interviews' => $interviews,
            'offers' => $offers,
            'latest_jobs' => $latestJobs,
            'counts' => [
                'applications' => count($applications),
                'interviews' => count($interviews),
                'offers_pending' => count(array_filter($offers, static fn (array $o): bool => (string) $o['status'] === 'sent')),
            ],
        ];
    }
}
