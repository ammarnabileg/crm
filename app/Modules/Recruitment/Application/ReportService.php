<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;

/**
 * Recruitment analytics for ONE workspace — the hiring funnel and activity,
 * aggregated from the workspace's own data (no separate warehouse). Strictly
 * workspace-scoped (docs/DASHBOARD_GUIDE.md).
 */
final class ReportService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed> */
    public function workspaceReport(string $workspaceId): array
    {
        return [
            'jobs' => [
                'total' => $this->int('SELECT COUNT(*) AS c FROM jobs WHERE workspace_id = ? AND deleted_at IS NULL', [$workspaceId]),
                'published' => $this->int("SELECT COUNT(*) AS c FROM jobs WHERE workspace_id = ? AND status = 'published' AND deleted_at IS NULL", [$workspaceId]),
            ],
            'funnel' => [
                'applications' => $this->int('SELECT COUNT(*) AS c FROM applications WHERE workspace_id = ? AND deleted_at IS NULL', [$workspaceId]),
                'interviews' => $this->int('SELECT COUNT(*) AS c FROM interviews WHERE workspace_id = ? AND deleted_at IS NULL', [$workspaceId]),
                'offers' => $this->int('SELECT COUNT(*) AS c FROM offers WHERE workspace_id = ? AND deleted_at IS NULL', [$workspaceId]),
                'hires' => $this->int("SELECT COUNT(*) AS c FROM applications WHERE workspace_id = ? AND status = 'hired' AND deleted_at IS NULL", [$workspaceId]),
            ],
            'applications_by_status' => $this->byStatus($workspaceId),
            'interviews' => [
                'completed' => $this->int("SELECT COUNT(*) AS c FROM interviews WHERE workspace_id = ? AND status = 'completed' AND deleted_at IS NULL", [$workspaceId]),
                'avg_score' => $this->avgScore($workspaceId),
            ],
            'offers' => [
                'sent' => $this->int("SELECT COUNT(*) AS c FROM offers WHERE workspace_id = ? AND status IN ('sent','accepted') AND deleted_at IS NULL", [$workspaceId]),
                'accepted' => $this->int("SELECT COUNT(*) AS c FROM offers WHERE workspace_id = ? AND status = 'accepted' AND deleted_at IS NULL", [$workspaceId]),
            ],
            'ai' => [
                'sessions' => $this->int('SELECT COUNT(*) AS c FROM ai_sessions WHERE workspace_id = ?', [$workspaceId]),
                'cost_cents' => $this->int('SELECT COALESCE(SUM(cost_cents),0) AS c FROM ai_sessions WHERE workspace_id = ?', [$workspaceId]),
            ],
        ];
    }

    /** @return array<string, int> application counts by status */
    private function byStatus(string $workspaceId): array
    {
        $out = [];
        foreach ($this->connection->select(
            'SELECT status, COUNT(*) AS c FROM applications WHERE workspace_id = ? AND deleted_at IS NULL GROUP BY status',
            [$workspaceId],
        ) as $row) {
            $out[(string) $row['status']] = (int) $row['c'];
        }

        return $out;
    }

    private function avgScore(string $workspaceId): ?int
    {
        $row = $this->connection->selectOne(
            "SELECT AVG(score) AS a FROM interviews WHERE workspace_id = ? AND status = 'completed' AND score IS NOT NULL AND deleted_at IS NULL",
            [$workspaceId],
        );

        return isset($row['a']) && $row['a'] !== null ? (int) round((float) $row['a']) : null;
    }

    /** @param list<mixed> $args */
    private function int(string $sql, array $args): int
    {
        $row = $this->connection->selectOne($sql, $args);

        return (int) ($row['c'] ?? 0);
    }
}
