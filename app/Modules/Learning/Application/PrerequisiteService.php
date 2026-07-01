<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Program prerequisites: a program can require other programs be completed first.
 * Used to gate enrollment/start. Pure persistence; the "is it satisfied" check
 * reads the learner's completed enrollments. Tenant-scoped.
 */
final class PrerequisiteService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function add(string $workspaceId, string $programId, string $prerequisiteProgramId): bool
    {
        if ($programId === $prerequisiteProgramId) {
            return false; // a program cannot require itself
        }
        $this->connection->statement(
            'INSERT INTO learning_prerequisites (id, workspace_id, program_id, prerequisite_program_id, created_at)
             VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE created_at = created_at',
            [Ulid::generate(), $workspaceId, $programId, $prerequisiteProgramId, gmdate('Y-m-d H:i:s')],
        );

        return true;
    }

    public function remove(string $workspaceId, string $programId, string $prerequisiteProgramId): bool
    {
        return $this->connection->statement(
            'DELETE FROM learning_prerequisites WHERE workspace_id = ? AND program_id = ? AND prerequisite_program_id = ?',
            [$workspaceId, $programId, $prerequisiteProgramId],
        ) > 0;
    }

    /** @return list<array<string,mixed>> the prerequisite programs (with titles) for a program */
    public function forProgram(string $workspaceId, string $programId): array
    {
        return $this->connection->select(
            'SELECT pr.prerequisite_program_id AS program_id, p.title, p.status
               FROM learning_prerequisites pr
               JOIN learning_programs p ON p.id = pr.prerequisite_program_id AND p.deleted_at IS NULL
              WHERE pr.workspace_id = ? AND pr.program_id = ?',
            [$workspaceId, $programId],
        );
    }

    /**
     * Which prerequisites a learner has NOT yet completed (empty = all met / none).
     *
     * @return list<array<string,mixed>>
     */
    public function unmetFor(string $workspaceId, string $programId, string $userId): array
    {
        $prereqs = $this->forProgram($workspaceId, $programId);
        if ($prereqs === []) {
            return [];
        }
        $unmet = [];
        foreach ($prereqs as $pre) {
            $row = $this->connection->selectOne(
                "SELECT status FROM learning_enrollments WHERE workspace_id = ? AND program_id = ? AND user_id = ?",
                [$workspaceId, (string) $pre['program_id'], $userId],
            );
            if ($row === null || (string) $row['status'] !== 'completed') {
                $unmet[] = $pre;
            }
        }

        return $unmet;
    }

    public function isSatisfied(string $workspaceId, string $programId, string $userId): bool
    {
        return $this->unmetFor($workspaceId, $programId, $userId) === [];
    }
}
