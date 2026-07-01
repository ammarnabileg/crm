<?php

declare(strict_types=1);

namespace HaHireAI\Core\Workflow;

use HaHireAI\Core\Contracts\TaskWriter;

/** No-op task writer — active when the Tasks module is disabled. */
final class NullTaskWriter implements TaskWriter
{
    public function createTask(string $workspaceId, string $title, ?string $createdByUserId = null, array $opts = []): string
    {
        return '';
    }
}
