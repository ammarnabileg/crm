<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/**
 * Notifications (docs/30) — read-side service over the existing `notifications`
 * table. This module only surfaces an existing feed (unread count, recent list,
 * mark-as-read); it never creates new notification types or channels.
 *
 * SECURITY BOUNDARY: a user may only ever see or mutate their OWN notifications.
 * Every query is fail-closed scoped by BOTH workspace_id AND user_id — the caller
 * passes the resolved tenant()->id() and auth()->id(); they are never trusted from
 * request input. A notification is unread when read_at IS NULL.
 */
final class NotificationService
{
    /** Number of unread notifications for the user in the workspace. */
    public function unreadCount(int $workspaceId, int $userId): int
    {
        return app('db')->table('notifications')
            ->where('workspace_id', '=', $workspaceId)
            ->where('user_id', '=', $userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * The most recent notifications (read and unread), newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function recent(int $workspaceId, int $userId, int $limit = 8): array
    {
        return app('db')->table('notifications')
            ->where('workspace_id', '=', $workspaceId)
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Mark a single notification read. Scoped UPDATE: a row that does not belong
     * to this workspace+user is silently a no-op (it never matches the WHERE), so
     * one user can never touch another's notification.
     */
    public function markRead(int $id, int $workspaceId, int $userId): void
    {
        app('db')->table('notifications')
            ->where('id', '=', $id)
            ->where('workspace_id', '=', $workspaceId)
            ->where('user_id', '=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }

    /** Mark every unread notification for the user in the workspace as read. */
    public function markAllRead(int $workspaceId, int $userId): void
    {
        app('db')->table('notifications')
            ->where('workspace_id', '=', $workspaceId)
            ->where('user_id', '=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }
}
