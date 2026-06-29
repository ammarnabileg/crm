<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Tasks\Application;

use HaHireAI\Core\Contracts\TaskWriter;

/**
 * Bridges the Core TaskWriter contract to the Tasks TaskService, so the Workflow
 * Engine can create tasks as an automation action without depending on Tasks
 * internals (ARCHITECTURE.md §4). The engine never touches TaskService directly.
 */
final class TaskWriterAdapter implements TaskWriter
{
    public function __construct(private readonly TaskService $tasks)
    {
    }

    public function createTask(string $workspaceId, string $title, ?string $createdByUserId = null, array $opts = []): string
    {
        return $this->tasks->create($workspaceId, $title, $createdByUserId, $opts);
    }
}
