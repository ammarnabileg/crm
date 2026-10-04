<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * The read surface of workspace tasks that other modules (e.g. the dashboard's
 * "My tasks") depend on — never the concrete `Tasks\Application\TaskService`
 * (ARCHITECTURE.md §4). Bound to TaskService at boot.
 */
interface TaskBoard
{
    /** @return list<array<string, mixed>> open tasks assigned to a user, soonest-due first */
    public function openForUser(string $workspaceId, string $userId, int $limit = 8): array;

    public function countOpenForUser(string $workspaceId, string $userId): int;
}
