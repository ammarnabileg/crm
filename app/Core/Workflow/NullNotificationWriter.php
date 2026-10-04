<?php

declare(strict_types=1);

namespace HaHireAI\Core\Workflow;

use HaHireAI\Core\Contracts\NotificationWriter;

/** No-op notification writer — active when the Notifications module is disabled. */
final class NullNotificationWriter implements NotificationWriter
{
    public function sendNotification(
        string $workspaceId,
        string $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
    ): string {
        return '';
    }
}
