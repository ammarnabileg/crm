<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Application;

use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Contracts\MemberDirectory;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Learning\Domain\ProgressCalculator;
use HaHireAI\Shared\Ulid;

/**
 * Assignment + enrollment + progress for the Learning module. A program can be
 * assigned to a user / role / department / team; assignment fans out to per-user
 * enrollments. Each learner's progress is computed from their per-item statuses
 * and the program's completion rule (pure {@see ProgressCalculator}). Publishes
 * learning.* events on the bus so the Workflow Engine can react. Tenant-scoped.
 *
 * Cross-module data (who holds a role) is resolved ONLY through the
 * MemberDirectory contract — never another module's tables (ARCHITECTURE.md §4).
 */
final class EnrollmentService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ProgramService $programs,
        private readonly MemberDirectory $members,
        private readonly EventDispatcher $events,
    ) {
    }

    /**
     * Assign a program to a user/role/department/team and fan out enrollments.
     *
     * @return int the number of enrollments created
     */
    public function assign(string $workspaceId, string $programId, string $assigneeType, string $assigneeId, string $assignedBy, ?string $dueDate = null): int
    {
        $assigneeType = in_array($assigneeType, ['user', 'role', 'department', 'team'], true) ? $assigneeType : 'user';
        $now = gmdate('Y-m-d H:i:s');
        $assignmentId = Ulid::generate();

        $this->connection->statement(
            'INSERT INTO learning_assignments (id, workspace_id, program_id, assignee_type, assignee_id, due_date, assigned_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE due_date = VALUES(due_date), assigned_by = VALUES(assigned_by)',
            [$assignmentId, $workspaceId, $programId, $assigneeType, $assigneeId, $this->date($dueDate), $assignedBy, $now],
        );

        $userIds = $this->resolveAssignees($workspaceId, $assigneeType, $assigneeId);
        $created = 0;
        foreach ($userIds as $uid) {
            if ($this->enroll($workspaceId, $programId, $uid, $assignmentId, $dueDate)) {
                $created++;
            }
        }

        $this->programs->activity($workspaceId, $programId, 'assignment', $assignmentId, $assignedBy, 'assigned', $assigneeType . ':' . $created . ' learner(s)');
        $this->events->dispatch('learning.program.assigned', [
            'workspace_id' => $workspaceId,
            'program_id' => $programId,
            'assignee_type' => $assigneeType,
            'assignee_id' => $assigneeId,
            'enrolled' => $created,
        ]);

        return $created;
    }

    /** Enroll a single user (idempotent). Returns true when a NEW enrollment was made. */
    public function enroll(string $workspaceId, string $programId, string $userId, ?string $assignmentId = null, ?string $dueDate = null): bool
    {
        if ($this->enrollment($workspaceId, $programId, $userId) !== null) {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO learning_enrollments (id, workspace_id, program_id, user_id, assignment_id, status, progress_percent, due_date, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE assignment_id = COALESCE(VALUES(assignment_id), assignment_id), due_date = COALESCE(VALUES(due_date), due_date)',
            [Ulid::generate(), $workspaceId, $programId, $userId, $assignmentId, 'not_started', 0, $this->date($dueDate), $now, $now],
        );
        $this->events->dispatch('learning.enrollment.created', [
            'workspace_id' => $workspaceId, 'program_id' => $programId, 'user_id' => $userId,
        ]);

        return true;
    }

    /**
     * Mark an item complete / not complete for a learner and recompute progress.
     */
    public function setItemStatus(string $workspaceId, string $programId, string $userId, string $itemId, string $status, ?string $actorId = null): bool
    {
        $enrollment = $this->enrollment($workspaceId, $programId, $userId);
        if ($enrollment === null) {
            // Auto-enroll on first interaction (e.g. self-directed learning).
            $this->enroll($workspaceId, $programId, $userId);
            $enrollment = $this->enrollment($workspaceId, $programId, $userId);
            if ($enrollment === null) {
                return false;
            }
        }
        $status = in_array($status, ['not_started', 'in_progress', 'completed'], true) ? $status : 'not_started';
        $now = gmdate('Y-m-d H:i:s');
        $enrollmentId = (string) $enrollment['id'];

        $this->connection->statement(
            'INSERT INTO learning_item_progress (id, workspace_id, enrollment_id, item_id, status, completed_at, completed_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), completed_at = VALUES(completed_at), completed_by = VALUES(completed_by), updated_at = VALUES(updated_at)',
            [Ulid::generate(), $workspaceId, $enrollmentId, $itemId, $status, $status === 'completed' ? $now : null, $status === 'completed' ? ($actorId ?? $userId) : null, $now, $now],
        );

        $this->recompute($workspaceId, $programId, $userId);

        return true;
    }

    /** Recompute an enrollment's percent + status from its item progress + the program rule. */
    public function recompute(string $workspaceId, string $programId, string $userId): void
    {
        $enrollment = $this->enrollment($workspaceId, $programId, $userId);
        $program = $this->programs->find($workspaceId, $programId);
        if ($enrollment === null || $program === null) {
            return;
        }
        $items = array_map(
            static fn (array $i): array => ['id' => (string) $i['id'], 'is_required' => (int) $i['is_required'] === 1],
            $this->programs->items($workspaceId, $programId),
        );
        $statuses = [];
        foreach ($this->itemProgress($workspaceId, (string) $enrollment['id']) as $p) {
            $statuses[(string) $p['item_id']] = (string) $p['status'];
        }
        $progress = ProgressCalculator::compute($items, $statuses);
        $status = ProgressCalculator::statusFor((string) $program['completion_rule'], $progress, (int) $program['completion_threshold']);

        $now = gmdate('Y-m-d H:i:s');
        $wasComplete = (string) $enrollment['status'] === 'completed';
        $this->connection->statement(
            'UPDATE learning_enrollments
                SET progress_percent = ?, status = ?,
                    started_at = COALESCE(started_at, ?),
                    completed_at = ?, updated_at = ?
              WHERE id = ? AND workspace_id = ?',
            [
                $progress['percent'], $status,
                $progress['completed'] > 0 ? $now : null,
                $status === 'completed' ? $now : null,
                $now, (string) $enrollment['id'], $workspaceId,
            ],
        );

        if ($status === 'completed' && ! $wasComplete) {
            $this->programs->activity($workspaceId, $programId, 'enrollment', (string) $enrollment['id'], $userId, 'completed', null);
            $this->events->dispatch('learning.program.completed', [
                'workspace_id' => $workspaceId, 'program_id' => $programId, 'user_id' => $userId,
            ]);
        }
    }

    /** @return array<string, mixed>|null */
    public function enrollment(string $workspaceId, string $programId, string $userId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM learning_enrollments WHERE program_id = ? AND user_id = ? AND workspace_id = ?',
            [$programId, $userId, $workspaceId],
        );
    }

    /** @return list<array<string,mixed>> the learner's per-item progress rows */
    public function itemProgress(string $workspaceId, string $enrollmentId): array
    {
        return $this->connection->select(
            'SELECT item_id, status, completed_at FROM learning_item_progress WHERE enrollment_id = ? AND workspace_id = ?',
            [$enrollmentId, $workspaceId],
        );
    }

    /**
     * The programs a user is enrolled in (for "My Learning").
     *
     * @return list<array<string,mixed>>
     */
    public function forUser(string $workspaceId, string $userId): array
    {
        return $this->connection->select(
            "SELECT e.*, p.title, p.summary, p.category, p.difficulty, p.estimated_minutes, p.status AS program_status
               FROM learning_enrollments e
               JOIN learning_programs p ON p.id = e.program_id AND p.deleted_at IS NULL
              WHERE e.workspace_id = ? AND e.user_id = ?
              ORDER BY (e.status = 'completed') ASC, e.due_date IS NULL ASC, e.due_date ASC, e.updated_at DESC",
            [$workspaceId, $userId],
        );
    }

    /**
     * Roster + stats for a program (the manager dashboard).
     *
     * @return array{enrollments: list<array<string,mixed>>, stats: array{total:int, completed:int, in_progress:int, not_started:int, avg_percent:int}}
     */
    public function roster(string $workspaceId, string $programId): array
    {
        $rows = $this->connection->select(
            'SELECT e.*, u.name AS user_name, u.email
               FROM learning_enrollments e
               JOIN users u ON u.id = e.user_id
              WHERE e.workspace_id = ? AND e.program_id = ?
              ORDER BY e.progress_percent DESC, u.name ASC',
            [$workspaceId, $programId],
        );
        $total = count($rows);
        $completed = $inProgress = $notStarted = $sumPct = 0;
        foreach ($rows as $r) {
            $sumPct += (int) $r['progress_percent'];
            match ((string) $r['status']) {
                'completed' => $completed++,
                'in_progress' => $inProgress++,
                default => $notStarted++,
            };
        }

        return [
            'enrollments' => $rows,
            'stats' => [
                'total' => $total,
                'completed' => $completed,
                'in_progress' => $inProgress,
                'not_started' => $notStarted,
                'avg_percent' => $total > 0 ? (int) round($sumPct / $total) : 0,
            ],
        ];
    }

    /** @return list<array<string,mixed>> the assignments recorded for a program */
    public function assignmentsFor(string $workspaceId, string $programId): array
    {
        return $this->connection->select(
            'SELECT * FROM learning_assignments WHERE program_id = ? AND workspace_id = ? ORDER BY created_at DESC',
            [$programId, $workspaceId],
        );
    }

    /**
     * @return list<string> user ids the assignment targets
     */
    private function resolveAssignees(string $workspaceId, string $type, string $assigneeId): array
    {
        return match ($type) {
            'user' => [$assigneeId],
            'role' => $this->members->membersWithRole($workspaceId, $assigneeId),
            // department / team are architecturally supported but resolve to no
            // direct members until those org structures exist — the assignment row
            // is still recorded so the link is preserved for when they do.
            default => [],
        };
    }

    private function date(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
