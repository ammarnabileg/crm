<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Learning\Domain\ItemType;
use HaHireAI\Modules\Learning\Domain\ProgramStatus;
use HaHireAI\Shared\Ulid;

/**
 * The authoring core of the Learning module: programs and their structure
 * (sections → items), tags, lifecycle (draft/published/archived), version
 * snapshots and a per-entity activity timeline. Tenant-scoped by workspace_id on
 * every read and write. Pure persistence orchestration — no provider, no AI.
 */
final class ProgramService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    // --- Programs ----------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $workspaceId, string $createdBy, array $data): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $title = trim((string) ($data['title'] ?? 'Untitled program')) ?: 'Untitled program';

        $this->connection->statement(
            'INSERT INTO learning_programs
              (id, workspace_id, title, slug, summary, description, cover_path, category, difficulty,
               estimated_minutes, status, visibility, version, completion_rule, completion_threshold,
               created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $workspaceId, $title, $this->uniqueSlug($workspaceId, $title),
                $this->str($data['summary'] ?? null, 500),
                ($data['description'] ?? null) !== null ? (string) $data['description'] : null,
                $this->str($data['cover_path'] ?? null, 1024),
                $this->str($data['category'] ?? null, 80),
                ProgramStatus::isDifficulty((string) ($data['difficulty'] ?? '')) ? (string) $data['difficulty'] : 'beginner',
                max(0, (int) ($data['estimated_minutes'] ?? 0)),
                ProgramStatus::DRAFT,
                'workspace',
                1,
                ProgramStatus::isCompletionRule((string) ($data['completion_rule'] ?? '')) ? (string) $data['completion_rule'] : 'required_items',
                max(1, min(100, (int) ($data['completion_threshold'] ?? 100))),
                $createdBy, $now, $now,
            ],
        );

        // The creator is the program owner (collaboration).
        $this->connection->statement(
            'INSERT INTO learning_program_editors (id, workspace_id, program_id, user_id, role, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $id, $createdBy, 'owner', $now],
        );

        $this->setTags($workspaceId, $id, $this->parseTags($data['tags'] ?? ''));
        $this->activity($workspaceId, $id, 'program', $id, $createdBy, 'created', $title);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $workspaceId, string $programId, string $actorId, array $data): bool
    {
        $fields = [];
        $bindings = [];
        $map = [
            'title' => static fn ($v): string => trim((string) $v) ?: 'Untitled program',
            'summary' => fn ($v): ?string => $this->str($v, 500),
            'description' => static fn ($v): ?string => $v !== null ? (string) $v : null,
            'cover_path' => fn ($v): ?string => $this->str($v, 1024),
            'category' => fn ($v): ?string => $this->str($v, 80),
            'estimated_minutes' => static fn ($v): int => max(0, (int) $v),
            'completion_threshold' => static fn ($v): int => max(1, min(100, (int) $v)),
        ];
        foreach ($map as $col => $cast) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = ?";
                $bindings[] = $cast($data[$col]);
            }
        }
        if (isset($data['difficulty']) && ProgramStatus::isDifficulty((string) $data['difficulty'])) {
            $fields[] = 'difficulty = ?';
            $bindings[] = (string) $data['difficulty'];
        }
        if (isset($data['completion_rule']) && ProgramStatus::isCompletionRule((string) $data['completion_rule'])) {
            $fields[] = 'completion_rule = ?';
            $bindings[] = (string) $data['completion_rule'];
        }
        if ($fields === []) {
            if (array_key_exists('tags', $data)) {
                $this->setTags($workspaceId, $programId, $this->parseTags($data['tags']));
            }

            return true;
        }

        $fields[] = 'updated_at = ?';
        $bindings[] = gmdate('Y-m-d H:i:s');
        $bindings[] = $programId;
        $bindings[] = $workspaceId;

        $affected = $this->connection->statement(
            'UPDATE learning_programs SET ' . implode(', ', $fields) . ' WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            $bindings,
        );

        if (array_key_exists('tags', $data)) {
            $this->setTags($workspaceId, $programId, $this->parseTags($data['tags']));
        }
        $this->activity($workspaceId, $programId, 'program', $programId, $actorId, 'updated', null);

        return $affected >= 0;
    }

    public function setStatus(string $workspaceId, string $programId, string $status, string $actorId): bool
    {
        if (! ProgramStatus::isValid($status)) {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s');
        $publishedAt = $status === ProgramStatus::PUBLISHED ? $now : null;

        $affected = $this->connection->statement(
            'UPDATE learning_programs SET status = ?, published_at = COALESCE(?, published_at), updated_at = ? WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$status, $publishedAt, $now, $programId, $workspaceId],
        );
        $this->activity($workspaceId, $programId, 'program', $programId, $actorId, $status, null);

        return $affected > 0;
    }

    public function delete(string $workspaceId, string $programId, string $actorId): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $affected = $this->connection->statement(
            'UPDATE learning_programs SET deleted_at = ?, updated_at = ? WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$now, $now, $programId, $workspaceId],
        );
        $this->activity($workspaceId, $programId, 'program', $programId, $actorId, 'deleted', null);

        return $affected > 0;
    }

    /** Snapshot the current structure into the version history, then bump the version. */
    public function snapshotVersion(string $workspaceId, string $programId, string $actorId, ?string $label = null): bool
    {
        $program = $this->find($workspaceId, $programId);
        if ($program === null) {
            return false;
        }
        $structure = $this->structure($workspaceId, $programId);
        $now = gmdate('Y-m-d H:i:s');
        $version = (int) ($program['version'] ?? 1);

        $this->connection->statement(
            'INSERT INTO learning_program_versions (id, workspace_id, program_id, version, label, snapshot, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $programId, $version, $this->str($label, 200), json_encode($structure, JSON_UNESCAPED_UNICODE), $actorId, $now],
        );
        $this->connection->statement(
            'UPDATE learning_programs SET version = version + 1, updated_at = ? WHERE id = ? AND workspace_id = ?',
            [$now, $programId, $workspaceId],
        );
        $this->activity($workspaceId, $programId, 'program', $programId, $actorId, 'versioned', 'v' . $version);

        return true;
    }

    /** @return array<string, mixed>|null */
    public function find(string $workspaceId, string $programId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM learning_programs WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$programId, $workspaceId],
        );
    }

    /**
     * @param  array{status?: string, q?: string, category?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function listForWorkspace(string $workspaceId, array $filters = [], int $limit = 100): array
    {
        $limit = max(1, min(300, $limit));
        $where = 'p.workspace_id = ? AND p.deleted_at IS NULL';
        $bindings = [$workspaceId];

        $status = (string) ($filters['status'] ?? '');
        if (ProgramStatus::isValid($status)) {
            $where .= ' AND p.status = ?';
            $bindings[] = $status;
        }
        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $where .= ' AND p.category = ?';
            $bindings[] = $category;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (p.title LIKE ? OR p.summary LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bindings, $like, $like);
        }

        $rows = $this->connection->select(
            "SELECT p.*, u.name AS author_name,
                    (SELECT COUNT(*) FROM learning_items i WHERE i.program_id = p.id) AS items_count,
                    (SELECT COUNT(*) FROM learning_enrollments e WHERE e.program_id = p.id) AS enrolled_count
               FROM learning_programs p
               LEFT JOIN users u ON u.id = p.created_by
              WHERE {$where}
              ORDER BY p.updated_at DESC
              LIMIT " . $limit,
            $bindings,
        );

        foreach ($rows as &$row) {
            $row['tags'] = $this->tagsFor($workspaceId, (string) $row['id']);
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> published programs (the catalog read surface) */
    public function publishedPrograms(string $workspaceId, int $limit = 50): array
    {
        return $this->listForWorkspace($workspaceId, ['status' => ProgramStatus::PUBLISHED], $limit);
    }

    public function countPrograms(string $workspaceId): int
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS c FROM learning_programs WHERE workspace_id = ? AND deleted_at IS NULL',
            [$workspaceId],
        );

        return (int) ($row['c'] ?? 0);
    }

    // --- Sections ----------------------------------------------------------

    public function addSection(string $workspaceId, string $programId, string $actorId, string $title, ?string $description = null, bool $required = true): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO learning_sections (id, workspace_id, program_id, title, description, position, is_required, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $programId, trim($title) ?: 'Untitled section', $this->str($description, 1000), $this->nextPosition('learning_sections', 'program_id', $programId, $workspaceId), $required ? 1 : 0, $now, $now],
        );
        $this->touch($workspaceId, $programId);
        $this->activity($workspaceId, $programId, 'section', $id, $actorId, 'created', $title);

        return $id;
    }

    public function updateSection(string $workspaceId, string $sectionId, string $title, ?string $description, bool $required): bool
    {
        $now = gmdate('Y-m-d H:i:s');

        return $this->connection->statement(
            'UPDATE learning_sections SET title = ?, description = ?, is_required = ?, updated_at = ? WHERE id = ? AND workspace_id = ?',
            [trim($title) ?: 'Untitled section', $this->str($description, 1000), $required ? 1 : 0, $now, $sectionId, $workspaceId],
        ) > 0;
    }

    public function deleteSection(string $workspaceId, string $sectionId): bool
    {
        return $this->connection->statement(
            'DELETE FROM learning_sections WHERE id = ? AND workspace_id = ?',
            [$sectionId, $workspaceId],
        ) > 0;
    }

    // --- Items -------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     */
    public function addItem(string $workspaceId, string $programId, string $sectionId, string $actorId, array $data): string
    {
        $type = (string) ($data['type'] ?? ItemType::LESSON);
        if (! ItemType::isValid($type)) {
            $type = ItemType::LESSON;
        }
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO learning_items (id, workspace_id, program_id, section_id, type, title, body, url, file_id, duration_minutes, is_required, position, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $workspaceId, $programId, $sectionId, $type,
                trim((string) ($data['title'] ?? ItemType::label($type))) ?: ItemType::label($type),
                ($data['body'] ?? null) !== null ? (string) $data['body'] : null,
                $this->str($data['url'] ?? null, 1024),
                ($data['file_id'] ?? '') !== '' ? (string) $data['file_id'] : null,
                max(0, (int) ($data['duration_minutes'] ?? 0)),
                ($data['is_required'] ?? true) ? 1 : 0,
                $this->nextPosition('learning_items', 'section_id', $sectionId, $workspaceId),
                $now, $now,
            ],
        );
        $this->touch($workspaceId, $programId);
        $this->activity($workspaceId, $programId, 'item', $id, $actorId, 'created', (string) ($data['title'] ?? $type));

        return $id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateItem(string $workspaceId, string $itemId, array $data): bool
    {
        $fields = [];
        $bindings = [];
        if (array_key_exists('title', $data)) {
            $fields[] = 'title = ?';
            $bindings[] = trim((string) $data['title']) ?: 'Untitled';
        }
        if (array_key_exists('body', $data)) {
            $fields[] = 'body = ?';
            $bindings[] = $data['body'] !== null ? (string) $data['body'] : null;
        }
        if (array_key_exists('url', $data)) {
            $fields[] = 'url = ?';
            $bindings[] = $this->str($data['url'], 1024);
        }
        if (array_key_exists('duration_minutes', $data)) {
            $fields[] = 'duration_minutes = ?';
            $bindings[] = max(0, (int) $data['duration_minutes']);
        }
        if (array_key_exists('is_required', $data)) {
            $fields[] = 'is_required = ?';
            $bindings[] = $data['is_required'] ? 1 : 0;
        }
        if ($fields === []) {
            return true;
        }
        $fields[] = 'updated_at = ?';
        $bindings[] = gmdate('Y-m-d H:i:s');
        $bindings[] = $itemId;
        $bindings[] = $workspaceId;

        return $this->connection->statement(
            'UPDATE learning_items SET ' . implode(', ', $fields) . ' WHERE id = ? AND workspace_id = ?',
            $bindings,
        ) >= 0;
    }

    public function deleteItem(string $workspaceId, string $itemId): bool
    {
        return $this->connection->statement(
            'DELETE FROM learning_items WHERE id = ? AND workspace_id = ?',
            [$itemId, $workspaceId],
        ) > 0;
    }

    /**
     * The full program tree: sections (ordered) each with their items (ordered).
     *
     * @return list<array<string, mixed>>
     */
    public function structure(string $workspaceId, string $programId): array
    {
        $sections = $this->connection->select(
            'SELECT * FROM learning_sections WHERE program_id = ? AND workspace_id = ? ORDER BY position, created_at',
            [$programId, $workspaceId],
        );
        $items = $this->connection->select(
            'SELECT * FROM learning_items WHERE program_id = ? AND workspace_id = ? ORDER BY position, created_at',
            [$programId, $workspaceId],
        );
        $bySection = [];
        foreach ($items as $item) {
            $bySection[(string) $item['section_id']][] = $item;
        }
        foreach ($sections as &$section) {
            $section['items'] = $bySection[(string) $section['id']] ?? [];
        }

        return $sections;
    }

    /** Flat list of every item in a program (for progress + enrollment). @return list<array<string,mixed>> */
    public function items(string $workspaceId, string $programId): array
    {
        return $this->connection->select(
            'SELECT id, type, title, is_required, section_id FROM learning_items WHERE program_id = ? AND workspace_id = ? ORDER BY position',
            [$programId, $workspaceId],
        );
    }

    /** @return list<string> editors' user ids for a program */
    public function editorsFor(string $workspaceId, string $programId): array
    {
        $rows = $this->connection->select(
            'SELECT e.user_id, e.role, u.name FROM learning_program_editors e LEFT JOIN users u ON u.id = e.user_id WHERE e.program_id = ? AND e.workspace_id = ?',
            [$programId, $workspaceId],
        );

        return $rows;
    }

    public function addEditor(string $workspaceId, string $programId, string $userId, string $role = 'editor'): void
    {
        $role = in_array($role, ['owner', 'editor', 'viewer'], true) ? $role : 'editor';
        $this->connection->statement(
            'INSERT INTO learning_program_editors (id, workspace_id, program_id, user_id, role, created_at)
             VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role)',
            [Ulid::generate(), $workspaceId, $programId, $userId, $role, gmdate('Y-m-d H:i:s')],
        );
    }

    /** @return list<array<string,mixed>> recent activity for a program */
    public function recentActivity(string $workspaceId, string $programId, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));

        return $this->connection->select(
            'SELECT a.*, u.name AS actor_name FROM learning_activity a LEFT JOIN users u ON u.id = a.actor_user_id
              WHERE a.program_id = ? AND a.workspace_id = ? ORDER BY a.created_at DESC LIMIT ' . $limit,
            [$programId, $workspaceId],
        );
    }

    public function activity(string $workspaceId, ?string $programId, string $entityType, string $entityId, ?string $actorId, string $action, ?string $summary): void
    {
        $this->connection->statement(
            'INSERT INTO learning_activity (id, workspace_id, program_id, entity_type, entity_id, actor_user_id, action, summary, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $programId, $entityType, $entityId, $actorId, mb_substr($action, 0, 48), $this->str($summary, 500), gmdate('Y-m-d H:i:s')],
        );
    }

    // --- Tags --------------------------------------------------------------

    /** @param list<string> $tags */
    public function setTags(string $workspaceId, string $programId, array $tags): void
    {
        $this->connection->statement('DELETE FROM learning_program_tags WHERE program_id = ? AND workspace_id = ?', [$programId, $workspaceId]);
        $now = gmdate('Y-m-d H:i:s');
        $seen = [];
        foreach ($tags as $tag) {
            $tag = mb_substr(trim($tag), 0, 60);
            $key = mb_strtolower($tag);
            if ($tag === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $this->connection->statement(
                'INSERT INTO learning_program_tags (id, workspace_id, program_id, tag, created_at) VALUES (?, ?, ?, ?, ?)',
                [Ulid::generate(), $workspaceId, $programId, $tag, $now],
            );
        }
    }

    /** @return list<string> */
    public function tagsFor(string $workspaceId, string $programId): array
    {
        $rows = $this->connection->select(
            'SELECT tag FROM learning_program_tags WHERE program_id = ? AND workspace_id = ? ORDER BY tag',
            [$programId, $workspaceId],
        );

        return array_map(static fn (array $r): string => (string) $r['tag'], $rows);
    }

    // --- helpers -----------------------------------------------------------

    /** @return list<string> */
    private function parseTags(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_map('strval', $raw);
        }
        $raw = (string) $raw;
        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $raw) ?: [])));
    }

    private function uniqueSlug(string $workspaceId, string $title): string
    {
        $base = $this->slugify($title);
        $slug = $base;
        $i = 2;
        while ($this->slugTaken($workspaceId, $slug)) {
            $slug = mb_substr($base, 0, 150) . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function slugTaken(string $workspaceId, string $slug): bool
    {
        return $this->connection->selectOne(
            'SELECT id FROM learning_programs WHERE workspace_id = ? AND slug = ?',
            [$workspaceId, $slug],
        ) !== null;
    }

    private function slugify(string $title): string
    {
        $slug = \HaHireAI\Support\Slug::make($title, 150);

        return $slug !== '' ? $slug : 'program-' . substr(Ulid::generate(), -8);
    }

    private function nextPosition(string $table, string $column, string $value, string $workspaceId): int
    {
        return \HaHireAI\Support\Position::next($this->connection, $table, [$column => $value, 'workspace_id' => $workspaceId]);
    }

    private function touch(string $workspaceId, string $programId): void
    {
        $this->connection->statement(
            'UPDATE learning_programs SET updated_at = ? WHERE id = ? AND workspace_id = ?',
            [gmdate('Y-m-d H:i:s'), $programId, $workspaceId],
        );
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
