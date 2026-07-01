<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Learning paths — an ordered sequence of programs (a track / curriculum). A path
 * groups programs so a learner progresses through them in order; path progress is
 * derived from the learner's per-program enrollments. Tenant-scoped.
 */
final class LearningPathService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(string $workspaceId, string $createdBy, string $title, ?string $description = null): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO learning_paths (id, workspace_id, title, slug, description, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, trim($title) ?: 'Untitled path', $this->uniqueSlug($workspaceId, $title), $description !== null ? mb_substr($description, 0, 1000) : null, 'draft', $createdBy, $now, $now],
        );

        return $id;
    }

    public function setStatus(string $workspaceId, string $pathId, string $status): bool
    {
        $status = in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft';

        return $this->connection->statement(
            'UPDATE learning_paths SET status = ?, updated_at = ? WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$status, gmdate('Y-m-d H:i:s'), $pathId, $workspaceId],
        ) > 0;
    }

    public function delete(string $workspaceId, string $pathId): bool
    {
        return $this->connection->statement(
            'UPDATE learning_paths SET deleted_at = ?, updated_at = ? WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'), $pathId, $workspaceId],
        ) > 0;
    }

    public function addProgram(string $workspaceId, string $pathId, string $programId): void
    {
        $this->connection->statement(
            'INSERT INTO learning_path_programs (id, workspace_id, path_id, program_id, position, created_at)
             VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE position = position',
            [Ulid::generate(), $workspaceId, $pathId, $programId, $this->nextPosition($workspaceId, $pathId), gmdate('Y-m-d H:i:s')],
        );
    }

    public function removeProgram(string $workspaceId, string $pathId, string $programId): bool
    {
        return $this->connection->statement(
            'DELETE FROM learning_path_programs WHERE workspace_id = ? AND path_id = ? AND program_id = ?',
            [$workspaceId, $pathId, $programId],
        ) > 0;
    }

    /** @return array<string,mixed>|null */
    public function find(string $workspaceId, string $pathId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM learning_paths WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$pathId, $workspaceId],
        );
    }

    /** @return list<array<string,mixed>> */
    public function listForWorkspace(string $workspaceId, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));

        return $this->connection->select(
            'SELECT lp.*, (SELECT COUNT(*) FROM learning_path_programs pp WHERE pp.path_id = lp.id) AS programs_count
               FROM learning_paths lp
              WHERE lp.workspace_id = ? AND lp.deleted_at IS NULL
              ORDER BY lp.updated_at DESC LIMIT ' . $limit,
            [$workspaceId],
        );
    }

    /** @return list<array<string,mixed>> the ordered programs in a path (with titles + status) */
    public function programsFor(string $workspaceId, string $pathId): array
    {
        return $this->connection->select(
            'SELECT pp.program_id, pp.position, p.title, p.status, p.summary, p.difficulty
               FROM learning_path_programs pp
               JOIN learning_programs p ON p.id = pp.program_id AND p.deleted_at IS NULL
              WHERE pp.workspace_id = ? AND pp.path_id = ?
              ORDER BY pp.position',
            [$workspaceId, $pathId],
        );
    }

    /**
     * A learner's progress across a path: how many of its programs they've completed.
     *
     * @return array{total:int, completed:int, percent:int}
     */
    public function progressFor(string $workspaceId, string $pathId, string $userId): array
    {
        $programs = $this->programsFor($workspaceId, $pathId);
        $total = count($programs);
        if ($total === 0) {
            return ['total' => 0, 'completed' => 0, 'percent' => 0];
        }
        $ids = array_map(static fn (array $p): string => (string) $p['program_id'], $programs);
        $place = implode(',', array_fill(0, count($ids), '?'));
        $row = $this->connection->selectOne(
            "SELECT COUNT(*) AS c FROM learning_enrollments
              WHERE workspace_id = ? AND user_id = ? AND status = 'completed' AND program_id IN ({$place})",
            array_merge([$workspaceId, $userId], $ids),
        );
        $completed = (int) ($row['c'] ?? 0);

        return ['total' => $total, 'completed' => $completed, 'percent' => (int) round($completed / $total * 100)];
    }

    private function nextPosition(string $workspaceId, string $pathId): int
    {
        return \HaHireAI\Support\Position::next($this->connection, 'learning_path_programs', [
            'workspace_id' => $workspaceId,
            'path_id' => $pathId,
        ]);
    }

    private function uniqueSlug(string $workspaceId, string $title): string
    {
        $base = \HaHireAI\Support\Slug::make($title, 150) ?: 'path-' . substr(Ulid::generate(), -8);
        $slug = $base;
        $i = 2;
        while ($this->connection->selectOne('SELECT id FROM learning_paths WHERE workspace_id = ? AND slug = ?', [$workspaceId, $slug]) !== null) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
