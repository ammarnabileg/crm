<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Learning\Domain\TodoStatus;
use HaHireAI\Shared\Ulid;

/**
 * To-dos inside a learning program. Two completion modes:
 *   - self    : the assignee can close it.
 *   - manager : only a supervisor (learning.todo.manage) can close it.
 *
 * Every status change is appended to learning_todo_status_history (audit trail).
 * Tenant-scoped by workspace_id throughout.
 */
final class TodoService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $workspaceId, string $programId, string $createdBy, array $data): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $mode = (string) ($data['completion_mode'] ?? TodoStatus::MODE_SELF);
        $priority = (string) ($data['priority'] ?? 'normal');

        $this->connection->statement(
            'INSERT INTO learning_todos
              (id, workspace_id, program_id, section_id, item_id, title, description, completion_mode, priority, status,
               due_date, assignee_user_id, created_by, position, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $workspaceId, $programId,
                ($data['section_id'] ?? '') !== '' ? (string) $data['section_id'] : null,
                ($data['item_id'] ?? '') !== '' ? (string) $data['item_id'] : null,
                trim((string) ($data['title'] ?? 'To-do')) ?: 'To-do',
                $this->str($data['description'] ?? null, 2000),
                TodoStatus::isMode($mode) ? $mode : TodoStatus::MODE_SELF,
                TodoStatus::isPriority($priority) ? $priority : 'normal',
                TodoStatus::OPEN,
                $this->date($data['due_date'] ?? null),
                ($data['assignee_user_id'] ?? '') !== '' ? (string) $data['assignee_user_id'] : null,
                $createdBy,
                $this->nextPosition($workspaceId, $programId, ($data['item_id'] ?? '') !== '' ? (string) $data['item_id'] : null),
                $now, $now,
            ],
        );
        $this->history($workspaceId, $id, null, TodoStatus::OPEN, $createdBy, 'created');

        return $id;
    }

    /** @return array<string, mixed>|null */
    public function find(string $workspaceId, string $todoId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM learning_todos WHERE id = ? AND workspace_id = ?',
            [$todoId, $workspaceId],
        );
    }

    /**
     * Change a to-do's status, enforcing the completion mode.
     *
     * @return array{ok: bool, error?: string}
     */
    public function changeStatus(string $workspaceId, string $todoId, string $status, string $actorId, bool $hasManage, ?string $note = null): array
    {
        if (! TodoStatus::isValid($status)) {
            return ['ok' => false, 'error' => 'Invalid status.'];
        }
        $todo = $this->find($workspaceId, $todoId);
        if ($todo === null) {
            return ['ok' => false, 'error' => 'To-do not found.'];
        }

        $isAssignee = ($todo['assignee_user_id'] ?? null) === $actorId;
        if ($status === TodoStatus::DONE && ! TodoStatus::canComplete((string) $todo['completion_mode'], $isAssignee, $hasManage)) {
            return ['ok' => false, 'error' => 'Only a supervisor can complete this manager-controlled to-do.'];
        }

        $from = (string) $todo['status'];
        if ($from === $status) {
            return ['ok' => true];
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'UPDATE learning_todos SET status = ?, completed_at = ?, completed_by = ?, updated_at = ? WHERE id = ? AND workspace_id = ?',
            [
                $status,
                $status === TodoStatus::DONE ? $now : null,
                $status === TodoStatus::DONE ? $actorId : null,
                $now, $todoId, $workspaceId,
            ],
        );
        $this->history($workspaceId, $todoId, $from, $status, $actorId, $note);

        return ['ok' => true];
    }

    public function delete(string $workspaceId, string $todoId): bool
    {
        return $this->connection->statement(
            'DELETE FROM learning_todos WHERE id = ? AND workspace_id = ?',
            [$todoId, $workspaceId],
        ) > 0;
    }

    /** @return list<array<string,mixed>> the to-dos under a todo_list item */
    public function forItem(string $workspaceId, string $itemId): array
    {
        return $this->connection->select(
            'SELECT t.*, u.name AS assignee_name FROM learning_todos t
               LEFT JOIN users u ON u.id = t.assignee_user_id
              WHERE t.item_id = ? AND t.workspace_id = ?
              ORDER BY t.position, t.created_at',
            [$itemId, $workspaceId],
        );
    }

    /** @return list<array<string,mixed>> all to-dos in a program */
    public function forProgram(string $workspaceId, string $programId): array
    {
        return $this->connection->select(
            'SELECT t.*, u.name AS assignee_name FROM learning_todos t
               LEFT JOIN users u ON u.id = t.assignee_user_id
              WHERE t.program_id = ? AND t.workspace_id = ?
              ORDER BY (t.status = \'done\') ASC, t.due_date IS NULL ASC, t.due_date ASC, t.position',
            [$programId, $workspaceId],
        );
    }

    /** Open to-dos assigned to a user (across programs) — for "My Learning". @return list<array<string,mixed>> */
    public function openForUser(string $workspaceId, string $userId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return $this->connection->select(
            "SELECT t.*, p.title AS program_title FROM learning_todos t
               JOIN learning_programs p ON p.id = t.program_id AND p.deleted_at IS NULL
              WHERE t.workspace_id = ? AND t.assignee_user_id = ? AND t.status <> 'done'
              ORDER BY t.due_date IS NULL ASC, t.due_date ASC, t.created_at DESC
              LIMIT " . $limit,
            [$workspaceId, $userId],
        );
    }

    /** @return list<array<string,mixed>> the status timeline for a to-do */
    public function statusHistory(string $workspaceId, string $todoId): array
    {
        return $this->connection->select(
            'SELECT h.*, u.name AS changed_by_name FROM learning_todo_status_history h
               LEFT JOIN users u ON u.id = h.changed_by
              WHERE h.todo_id = ? AND h.workspace_id = ?
              ORDER BY h.created_at ASC',
            [$todoId, $workspaceId],
        );
    }

    private function history(string $workspaceId, string $todoId, ?string $from, string $to, ?string $changedBy, ?string $note): void
    {
        $this->connection->statement(
            'INSERT INTO learning_todo_status_history (id, workspace_id, todo_id, from_status, to_status, note, changed_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $todoId, $from, $to, $this->str($note, 500), $changedBy, gmdate('Y-m-d H:i:s')],
        );
    }

    private function nextPosition(string $workspaceId, string $programId, ?string $itemId): int
    {
        return \HaHireAI\Support\Position::next($this->connection, 'learning_todos', [
            'workspace_id' => $workspaceId,
            'program_id' => $programId,
            'item_id' => $itemId,
        ]);
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function str(mixed $v, int $max): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : mb_substr($s, 0, $max);
    }
}
