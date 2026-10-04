<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Notifications\Application;

use HaHireAI\Core\Contracts\NotificationWriter;

/**
 * Bridges the Core NotificationWriter contract to the NotificationService, so the
 * Workflow Engine can send notifications as an automation action without depending
 * on Notifications internals (ARCHITECTURE.md §4).
 */
final class NotificationWriterAdapter implements NotificationWriter
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function sendNotification(
        string $workspaceId,
        string $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
    ): string {
        return $this->notifications->notify($workspaceId, $userId, $type, $title, $body, $link);
    }
}
