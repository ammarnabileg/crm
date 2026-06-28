<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/** Stores workflows as DATA (no automation in modules — docs/WORKFLOW_ENGINE.md). */
final class WorkflowService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param  list<array{action: string, params?: array<string,mixed>, condition?: array<string,mixed>}>  $steps
     */
    public function create(string $workspaceId, string $name, string $triggerEvent, array $steps, ?string $createdBy = null): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workflows (id, workspace_id, name, trigger_event, definition, status, enabled, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $name, $triggerEvent, json_encode(['steps' => $steps]), 'published', 1, $createdBy, $now, $now],
        );

        return $id;
    }

    /** @return list<array<string,mixed>> enabled workflows for a trigger, with decoded steps */
    public function findEnabledForTrigger(string $workspaceId, string $triggerEvent): array
    {
        $rows = $this->connection->select(
            "SELECT * FROM workflows WHERE workspace_id = ? AND trigger_event = ? AND enabled = 1 AND status = 'published' AND deleted_at IS NULL ORDER BY created_at ASC",
            [$workspaceId, $triggerEvent],
        );

        foreach ($rows as &$row) {
            $decoded = json_decode((string) $row['definition'], true);
            $row['steps'] = is_array($decoded['steps'] ?? null) ? $decoded['steps'] : [];
        }

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    public function listForWorkspace(string $workspaceId): array
    {
        return $this->connection->select(
            'SELECT * FROM workflows WHERE workspace_id = ? AND deleted_at IS NULL ORDER BY created_at DESC',
            [$workspaceId],
        );
    }

    /** @return list<array<string,mixed>> */
    public function executionsForWorkspace(string $workspaceId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return $this->connection->select(
            'SELECT e.*, w.name AS workflow_name FROM workflow_executions e
               JOIN workflows w ON w.id = e.workflow_id
              WHERE e.workspace_id = ? ORDER BY e.created_at DESC LIMIT ' . $limit,
            [$workspaceId],
        );
    }
}
