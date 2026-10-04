<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Polymorphic comment threads for the Learning module — a thread can hang off any
 * program / section / item / todo. Supports replies (parent_id), @mentions, edit
 * and (soft) delete. Tenant-scoped by workspace_id. Mention resolution is by user
 * id supplied by the caller (the controller maps @handles → ids).
 */
final class CommentService
{
    private const ENTITY_TYPES = ['program', 'section', 'item', 'todo'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param  list<string>  $mentionUserIds
     */
    public function add(string $workspaceId, string $programId, string $entityType, string $entityId, string $authorId, string $body, ?string $parentId = null, array $mentionUserIds = []): ?string
    {
        $entityType = in_array($entityType, self::ENTITY_TYPES, true) ? $entityType : null;
        $body = trim($body);
        if ($entityType === null || $body === '') {
            return null;
        }
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO learning_comments (id, workspace_id, program_id, entity_type, entity_id, parent_id, author_user_id, body, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $programId, $entityType, $entityId, ($parentId ?? '') !== '' ? $parentId : null, $authorId, $body, $now],
        );
        foreach (array_unique($mentionUserIds) as $uid) {
            if ($uid === '') {
                continue;
            }
            $this->connection->statement(
                'INSERT INTO learning_comment_mentions (id, workspace_id, comment_id, mentioned_user_id, created_at)
                 VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE created_at = created_at',
                [Ulid::generate(), $workspaceId, $id, $uid, $now],
            );
        }

        return $id;
    }

    /**
     * The thread for an entity: top-level comments (oldest first), each with its
     * replies and mention names. Soft-deleted comments are tomb-stoned, not hidden,
     * so replies keep their context.
     *
     * @return list<array<string, mixed>>
     */
    public function thread(string $workspaceId, string $entityType, string $entityId): array
    {
        $rows = $this->connection->select(
            'SELECT c.*, u.name AS author_name FROM learning_comments c
               LEFT JOIN users u ON u.id = c.author_user_id
              WHERE c.workspace_id = ? AND c.entity_type = ? AND c.entity_id = ?
              ORDER BY c.created_at ASC',
            [$workspaceId, $entityType, $entityId],
        );

        $mentions = [];
        if ($rows !== []) {
            $ids = array_map(static fn (array $r): string => (string) $r['id'], $rows);
            $place = implode(',', array_fill(0, count($ids), '?'));
            $mrows = $this->connection->select(
                "SELECT m.comment_id, u.name FROM learning_comment_mentions m
                   LEFT JOIN users u ON u.id = m.mentioned_user_id
                  WHERE m.workspace_id = ? AND m.comment_id IN ({$place})",
                array_merge([$workspaceId], $ids),
            );
            foreach ($mrows as $m) {
                $mentions[(string) $m['comment_id']][] = (string) $m['name'];
            }
        }

        $byParent = [];
        foreach ($rows as $row) {
            $row['mentions'] = $mentions[(string) $row['id']] ?? [];
            $byParent[(string) ($row['parent_id'] ?? '')][] = $row;
        }
        $top = $byParent[''] ?? [];
        foreach ($top as &$c) {
            $c['replies'] = $byParent[(string) $c['id']] ?? [];
        }

        return $top;
    }

    public function edit(string $workspaceId, string $commentId, string $actorId, string $body): bool
    {
        $body = trim($body);
        if ($body === '') {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s');

        return $this->connection->statement(
            'UPDATE learning_comments SET body = ?, edited_at = ? WHERE id = ? AND workspace_id = ? AND author_user_id = ? AND deleted_at IS NULL',
            [$body, $now, $commentId, $workspaceId, $actorId],
        ) > 0;
    }

    /** Soft-delete: the author or a manager (hasManage) may remove a comment. */
    public function delete(string $workspaceId, string $commentId, string $actorId, bool $hasManage): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        if ($hasManage) {
            return $this->connection->statement(
                'UPDATE learning_comments SET deleted_at = ?, body = ? WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
                [$now, '[deleted]', $commentId, $workspaceId],
            ) > 0;
        }

        return $this->connection->statement(
            'UPDATE learning_comments SET deleted_at = ?, body = ? WHERE id = ? AND workspace_id = ? AND author_user_id = ? AND deleted_at IS NULL',
            [$now, '[deleted]', $commentId, $workspaceId, $actorId],
        ) > 0;
    }

    public function countForEntity(string $workspaceId, string $entityType, string $entityId): int
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS c FROM learning_comments WHERE workspace_id = ? AND entity_type = ? AND entity_id = ? AND deleted_at IS NULL',
            [$workspaceId, $entityType, $entityId],
        );

        return (int) ($row['c'] ?? 0);
    }
}
