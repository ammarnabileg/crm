<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Tasks\Application;

use HaHireAI\Core\Contracts\TaskBoard;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Workspace tasks — lightweight hiring to-dos (Feature 14). Tenant-scoped by
 * workspace_id on every read and write (privacy isolation). The shared read
 * surface is the TaskBoard contract (ARCHITECTURE.md §4).
 */
final class TaskService implements TaskBoard
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param  array{description?: ?string, assignee_user_id?: ?string, due_at?: ?string, priority?: string, entity_type?: ?string, entity_id?: ?string}  $opts
     */
    public function create(string $workspaceId, string $title, ?string $createdBy, array $opts = []): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO tasks (id, workspace_id, title, description, assignee_user_id, created_by, status, priority, due_at, entity_type, entity_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $workspaceId, $title,
                $opts['description'] ?? null,
                ($opts['assignee_user_id'] ?? '') !== '' ? $opts['assignee_user_id'] : null,
                $createdBy,
                'open',
                in_array($opts['priority'] ?? 'normal', ['low', 'normal', 'high'], true) ? ($opts['priority'] ?? 'normal') : 'normal',
                ($opts['due_at'] ?? '') !== '' ? $opts['due_at'] : null,
                $opts['entity_type'] ?? null,
                $opts['entity_id'] ?? null,
                $now, $now,
            ],
        );

        return $id;
    }

    /** @return array<string, mixed>|null */
    public function find(string $workspaceId, string $taskId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM tasks WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$taskId, $workspaceId],
        );
    }

    /**
     * @param  array{status?: string, assignee_user_id?: string, q?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function listForWorkspace(string $workspaceId, array $filters = [], int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $where = 't.workspace_id = ? AND t.deleted_at IS NULL';
        $bindings = [$workspaceId];

        $status = (string) ($filters['status'] ?? '');
        if ($status === 'open' || $status === 'done') {
            $where .= ' AND t.status = ?';
            $bindings[] = $status;
        }
        $assignee = trim((string) ($filters['assignee_user_id'] ?? ''));
        if ($assignee !== '') {
            $where .= ' AND t.assignee_user_id = ?';
            $bindings[] = $assignee;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (t.title LIKE ? OR t.description LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bindings, $like, $like);
        }

        return $this->connection->select(
            "SELECT t.*, u.name AS assignee_name
               FROM tasks t
               LEFT JOIN users u ON u.id = t.assignee_user_id
              WHERE {$where}
              ORDER BY (t.status = 'done') ASC, t.due_at IS NULL ASC, t.due_at ASC, t.created_at DESC
              LIMIT " . $limit,
            $bindings,
        );
    }

    /** Open tasks assigned to a user, soonest-due first (for the dashboard "My tasks"). */
    public function openForUser(string $workspaceId, string $userId, int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));

        return $this->connection->select(
            "SELECT t.id, t.title, t.due_at, t.priority
               FROM tasks t
              WHERE t.workspace_id = ? AND t.assignee_user_id = ? AND t.status = 'open' AND t.deleted_at IS NULL
              ORDER BY t.due_at IS NULL ASC, t.due_at ASC, t.created_at DESC
              LIMIT " . $limit,
            [$workspaceId, $userId],
        );
    }

    public function setStatus(string $workspaceId, string $taskId, string $status): bool
    {
        $status = $status === 'done' ? 'done' : 'open';
        $now = gmdate('Y-m-d H:i:s');
        $affected = $this->connection->statement(
            'UPDATE tasks SET status = ?, completed_at = ?, updated_at = ? WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$status, $status === 'done' ? $now : null, $now, $taskId, $workspaceId],
        );

        return $affected > 0;
    }

    public function delete(string $workspaceId, string $taskId): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $affected = $this->connection->statement(
            'UPDATE tasks SET deleted_at = ?, updated_at = ? WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$now, $now, $taskId, $workspaceId],
        );

        return $affected > 0;
    }

    public function countOpenForUser(string $workspaceId, string $userId): int
    {
        $row = $this->connection->selectOne(
            "SELECT COUNT(*) AS c FROM tasks WHERE workspace_id = ? AND assignee_user_id = ? AND status = 'open' AND deleted_at IS NULL",
            [$workspaceId, $userId],
        );

        return (int) ($row['c'] ?? 0);
    }
}
