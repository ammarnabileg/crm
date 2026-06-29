<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * The write surface of workspace tasks that the Workflow Engine uses to CREATE
 * tasks as an automation action — never the concrete `Tasks\Application\TaskService`
 * (ARCHITECTURE.md §4). Bound to a thin adapter over TaskService at boot; Core
 * ships a no-op default so workflows degrade gracefully when Tasks is disabled.
 */
interface TaskWriter
{
    /**
     * Create a task in a workspace. Returns the new task id (empty string if the
     * Tasks module is not installed).
     *
     * @param  array<string, mixed>  $opts  optional: description, assignee_id, due_at, priority
     */
    public function createTask(string $workspaceId, string $title, ?string $createdByUserId = null, array $opts = []): string;
}
