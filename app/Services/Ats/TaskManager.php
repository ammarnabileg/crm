<?php

declare(strict_types=1);

namespace App\Services\Ats;

use App\Models\Task;
use InvalidArgumentException;

/**
 * Recruiter Task Management (docs/53 ATS Task Management).
 *
 * Thin lifecycle service over the `tasks` model: create a to-do (optionally linked
 * polymorphically to a job/candidate/interview via related_type/related_id),
 * assign it, complete it, and list by relation / assignee / open status. Status and
 * priority are code-validated VARCHARs (no ENUMs): the allowed sets live here as the
 * single source of truth. All operations are tenant-scoped through the Task model.
 */
final class TaskManager
{
    /** @var string[] */
    public const STATUSES = ['open', 'in_progress', 'done', 'canceled'];

    /** @var string[] */
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    /**
     * Create a task. `title` is required. `status` defaults to open, `priority` to
     * normal; both are validated against the allowed sets.
     *
     * @param array<string,mixed> $attrs title, description, status, priority, due_at,
     *                                    assignee_id, related_type, related_id, created_by.
     */
    public function create(array $attrs): Task
    {
        $title = trim((string) ($attrs['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Task title is required.');
        }

        $status = (string) ($attrs['status'] ?? 'open');
        $priority = (string) ($attrs['priority'] ?? 'normal');
        $this->assertStatus($status);
        $this->assertPriority($priority);

        return Task::create([
            'title'        => $title,
            'description'  => $attrs['description'] ?? null,
            'status'       => $status,
            'priority'     => $priority,
            'due_at'       => $attrs['due_at'] ?? null,
            'assignee_id'  => $attrs['assignee_id'] ?? null,
            'related_type' => $attrs['related_type'] ?? null,
            'related_id'   => isset($attrs['related_id']) ? (int) $attrs['related_id'] : null,
            'created_by'   => $attrs['created_by'] ?? null,
            'completed_at' => $status === 'done' ? now() : null,
        ]);
    }

    /**
     * Mark a task done and stamp completed_at. Returns the updated task, or null if
     * the id is not in the current tenant.
     */
    public function complete(int $taskId): ?Task
    {
        $task = Task::find($taskId);
        if ($task === null) {
            return null;
        }

        $task->update(['status' => 'done', 'completed_at' => now()]);

        return $task;
    }

    /**
     * Assign (or reassign) a task to a user. Returns the updated task, or null if
     * the id is not in the current tenant.
     */
    public function assign(int $taskId, int $userId): ?Task
    {
        $task = Task::find($taskId);
        if ($task === null) {
            return null;
        }

        $task->update(['assignee_id' => $userId]);

        return $task;
    }

    /**
     * Tasks linked to a given related subject, newest first.
     *
     * @return Task[]
     */
    public function forRelated(string $type, int $id): array
    {
        return array_map(
            [Task::class, 'hydrate'],
            Task::query()
                ->where('related_type', '=', $type)
                ->where('related_id', '=', $id)
                ->orderBy('id', 'desc')
                ->get()
        );
    }

    /**
     * Tasks assigned to a user, newest first.
     *
     * @return Task[]
     */
    public function forAssignee(int $userId): array
    {
        return array_map(
            [Task::class, 'hydrate'],
            Task::query()
                ->where('assignee_id', '=', $userId)
                ->orderBy('id', 'desc')
                ->get()
        );
    }

    /**
     * Open (not done, not canceled) tasks in the current tenant. Soonest due first;
     * tasks with no due date sort after dated ones.
     *
     * @return Task[]
     */
    public function open(): array
    {
        return array_map(
            [Task::class, 'hydrate'],
            Task::query()
                ->whereIn('status', ['open', 'in_progress'])
                ->orderBy('due_at', 'asc')
                ->orderBy('id', 'asc')
                ->get()
        );
    }

    private function assertStatus(string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid task status [{$status}]. Allowed: " . implode(', ', self::STATUSES) . '.'
            );
        }
    }

    private function assertPriority(string $priority): void
    {
        if (! in_array($priority, self::PRIORITIES, true)) {
            throw new InvalidArgumentException(
                "Invalid task priority [{$priority}]. Allowed: " . implode(', ', self::PRIORITIES) . '.'
            );
        }
    }
}
