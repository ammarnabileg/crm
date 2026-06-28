<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Notifications\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Personal notifications, scoped to (workspace, user). A user who belongs to
 * several workspaces sees each workspace's notifications only within that
 * workspace's context (docs/WORKSPACE_MODEL.md tenant isolation).
 */
final class NotificationService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function notify(string $workspaceId, string $userId, string $type, string $title, ?string $body = null, ?string $link = null): string
    {
        $id = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO notifications (id, workspace_id, user_id, type, title, body, link, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $userId, $type, $title, $body, $link, gmdate('Y-m-d H:i:s')],
        );

        return $id;
    }

    /**
     * Notifications for a user, with optional state/category/search filters.
     *
     * @param  array{state?: string, category?: string, q?: string}  $filters
     *         state: 'all' (active) | 'unread' | 'archived'
     * @return list<array<string, mixed>>
     */
    public function forUser(string $workspaceId, string $userId, array $filters = [], int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $where = 'workspace_id = ? AND user_id = ?';
        $bindings = [$workspaceId, $userId];

        $state = (string) ($filters['state'] ?? 'all');
        if ($state === 'archived') {
            $where .= ' AND archived_at IS NOT NULL';
        } else {
            $where .= ' AND archived_at IS NULL';
            if ($state === 'unread') {
                $where .= ' AND read_at IS NULL';
            }
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $where .= ' AND type = ?';
            $bindings[] = $category;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (title LIKE ? OR body LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bindings, $like, $like);
        }

        return $this->connection->select(
            'SELECT id, type, title, body, link, read_at, archived_at, created_at FROM notifications
              WHERE ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit,
            $bindings,
        );
    }

    /** Unread excludes archived — archiving a notification clears it from the unread count. */
    public function unreadCount(string $workspaceId, string $userId): int
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS c FROM notifications WHERE workspace_id = ? AND user_id = ? AND read_at IS NULL AND archived_at IS NULL',
            [$workspaceId, $userId],
        );

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Tab counts for the notification center.
     *
     * @return array{all: int, unread: int, archived: int}
     */
    public function counts(string $workspaceId, string $userId): array
    {
        $row = $this->connection->selectOne(
            "SELECT
                SUM(CASE WHEN archived_at IS NULL THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN archived_at IS NULL AND read_at IS NULL THEN 1 ELSE 0 END) AS unread,
                SUM(CASE WHEN archived_at IS NOT NULL THEN 1 ELSE 0 END) AS archived
             FROM notifications WHERE workspace_id = ? AND user_id = ?",
            [$workspaceId, $userId],
        ) ?? [];

        return [
            'all' => (int) ($row['active'] ?? 0),
            'unread' => (int) ($row['unread'] ?? 0),
            'archived' => (int) ($row['archived'] ?? 0),
        ];
    }

    /** @return list<string> distinct categories (types) the user has received */
    public function categories(string $workspaceId, string $userId): array
    {
        $rows = $this->connection->select(
            'SELECT DISTINCT type FROM notifications WHERE workspace_id = ? AND user_id = ? ORDER BY type ASC',
            [$workspaceId, $userId],
        );

        return array_map(static fn (array $r): string => (string) $r['type'], $rows);
    }

    public function archive(string $workspaceId, string $userId, string $notificationId): void
    {
        $this->connection->statement(
            'UPDATE notifications SET archived_at = ? WHERE id = ? AND workspace_id = ? AND user_id = ? AND archived_at IS NULL',
            [gmdate('Y-m-d H:i:s'), $notificationId, $workspaceId, $userId],
        );
    }

    public function unarchive(string $workspaceId, string $userId, string $notificationId): void
    {
        $this->connection->statement(
            'UPDATE notifications SET archived_at = NULL WHERE id = ? AND workspace_id = ? AND user_id = ?',
            [$notificationId, $workspaceId, $userId],
        );
    }

    public function markRead(string $workspaceId, string $userId, string $notificationId): void
    {
        $this->connection->statement(
            'UPDATE notifications SET read_at = ? WHERE id = ? AND workspace_id = ? AND user_id = ? AND read_at IS NULL',
            [gmdate('Y-m-d H:i:s'), $notificationId, $workspaceId, $userId],
        );
    }

    public function markAllRead(string $workspaceId, string $userId): void
    {
        $this->connection->statement(
            'UPDATE notifications SET read_at = ? WHERE workspace_id = ? AND user_id = ? AND read_at IS NULL',
            [gmdate('Y-m-d H:i:s'), $workspaceId, $userId],
        );
    }
}
