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

    /** @return list<array<string, mixed>> */
    public function forUser(string $workspaceId, string $userId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return $this->connection->select(
            'SELECT id, type, title, body, link, read_at, created_at FROM notifications
              WHERE workspace_id = ? AND user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . $limit,
            [$workspaceId, $userId],
        );
    }

    public function unreadCount(string $workspaceId, string $userId): int
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS c FROM notifications WHERE workspace_id = ? AND user_id = ? AND read_at IS NULL',
            [$workspaceId, $userId],
        );

        return (int) ($row['c'] ?? 0);
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
