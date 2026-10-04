<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Search\Application;

use HaHireAI\Core\Database\Connection;

/**
 * Unified, workspace-scoped search. Results never cross the workspace boundary
 * (docs/SEARCH_ENGINE.md). Recruitment entities are added in Phase 10.
 */
final class SearchService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{members: list<array<string,mixed>>, roles: list<array<string,mixed>>}
     */
    public function search(string $workspaceId, string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return ['members' => [], 'roles' => []];
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';

        $members = $this->connection->select(
            'SELECT u.name, u.email
               FROM memberships m JOIN users u ON u.id = m.user_id
              WHERE m.workspace_id = ? AND m.deleted_at IS NULL
                AND (u.name LIKE ? OR u.email LIKE ?)
              ORDER BY u.name LIMIT 10',
            [$workspaceId, $like, $like],
        );

        $roles = $this->connection->select(
            'SELECT name, description FROM roles
              WHERE workspace_id = ? AND deleted_at IS NULL AND name LIKE ?
              ORDER BY name LIMIT 10',
            [$workspaceId, $like],
        );

        return ['members' => $members, 'roles' => $roles];
    }
}
