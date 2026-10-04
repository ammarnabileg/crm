<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * Read access to a user's notifications for the header bell. The shell depends on
 * this contract, never on Notifications\Application\NotificationService
 * (ARCHITECTURE.md §4). Bound at boot.
 */
interface NotificationFeed
{
    /**
     * Most recent active (non-archived) notifications for the header dropdown.
     *
     * @return list<array<string, mixed>>
     */
    public function recentForUser(string $workspaceId, string $userId, int $limit = 6): array;

    /** Unread, non-archived count for the bell badge. */
    public function unreadCount(string $workspaceId, string $userId): int;
}
