<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * The write surface of notifications that the Workflow Engine uses to SEND a
 * notification as an automation action — never the concrete
 * `Notifications\Application\NotificationService` (ARCHITECTURE.md §4). Bound to a
 * thin adapter at boot; Core ships a no-op default so workflows degrade gracefully
 * when Notifications is disabled.
 */
interface NotificationWriter
{
    /**
     * Send a notification to a user. Returns the new notification id (empty string
     * if the Notifications module is not installed).
     */
    public function sendNotification(
        string $workspaceId,
        string $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
    ): string;
}
