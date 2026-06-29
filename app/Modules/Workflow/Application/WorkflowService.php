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

    /**
     * Create or update a workflow from the visual builder. The definition stores
     * the editable graph ({nodes, edges}) plus the compiled {steps} the engine
     * runs — so the engine path is unchanged and backward compatible.
     *
     * @param  array{nodes: list<array<string,mixed>>, edges: list<array<string,mixed>>, steps: list<array<string,mixed>>}  $definition
     */
    public function save(string $workspaceId, ?string $id, string $name, string $triggerEvent, array $definition, bool $enabled, ?string $userId = null): string
    {
        $now = gmdate('Y-m-d H:i:s');
        $json = json_encode($definition);

        if ($id !== null && $id !== '' && $this->find($workspaceId, $id) !== null) {
            $this->connection->statement(
                'UPDATE workflows SET name = ?, trigger_event = ?, definition = ?, enabled = ?, updated_at = ? WHERE workspace_id = ? AND id = ?',
                [$name, $triggerEvent, $json, $enabled ? 1 : 0, $now, $workspaceId, $id],
            );

            return $id;
        }

        $newId = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO workflows (id, workspace_id, name, trigger_event, definition, status, enabled, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$newId, $workspaceId, $name, $triggerEvent, $json, 'published', $enabled ? 1 : 0, $userId, $now, $now],
        );

        return $newId;
    }

    /** @return array<string,mixed>|null one workflow with decoded nodes/edges/steps */
    public function find(string $workspaceId, string $id): ?array
    {
        $row = $this->connection->selectOne(
            'SELECT * FROM workflows WHERE workspace_id = ? AND id = ? AND deleted_at IS NULL',
            [$workspaceId, $id],
        );
        if ($row === null) {
            return null;
        }

        $def = json_decode((string) $row['definition'], true);
        $def = is_array($def) ? $def : [];
        $row['nodes'] = is_array($def['nodes'] ?? null) ? $def['nodes'] : [];
        $row['edges'] = is_array($def['edges'] ?? null) ? $def['edges'] : [];
        $row['steps'] = is_array($def['steps'] ?? null) ? $def['steps'] : [];

        return $row;
    }

    public function setEnabled(string $workspaceId, string $id, bool $enabled): void
    {
        $this->connection->statement(
            'UPDATE workflows SET enabled = ?, updated_at = ? WHERE workspace_id = ? AND id = ?',
            [$enabled ? 1 : 0, gmdate('Y-m-d H:i:s'), $workspaceId, $id],
        );
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
