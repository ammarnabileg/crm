<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Audit\Application;

use HaHireAI\Core\Database\Connection;

/**
 * Reads the workspace activity timeline from the audit log (tenant-isolated).
 * See docs/AUDIT_LOG.md, docs/WORKSPACE_PLATFORM.md.
 */
final class ActivityFeed
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> recent activity for a workspace */
    public function forWorkspace(string $workspaceId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return $this->connection->select(
            'SELECT a.action, a.entity_type, a.entity_id, a.created_at, u.name AS actor_name
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.actor_user_id
              WHERE a.workspace_id = ?
              ORDER BY a.created_at DESC, a.id DESC
              LIMIT ' . $limit,
            [$workspaceId],
        );
    }

    public function countForWorkspace(string $workspaceId): int
    {
        $row = $this->connection->selectOne('SELECT COUNT(*) AS c FROM audit_logs WHERE workspace_id = ?', [$workspaceId]);

        return (int) ($row['c'] ?? 0);
    }
}
