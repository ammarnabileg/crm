<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Memberships\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/** Links a User to a Workspace (docs/MEMBERSHIP_ENGINE.md). */
final class MembershipService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(string $workspaceId, string $userId, string $status = 'active', ?string $invitedBy = null): string
    {
        $existing = $this->find($workspaceId, $userId);

        if ($existing !== null) {
            return (string) $existing['id'];
        }

        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->statement(
            'INSERT INTO memberships (id, workspace_id, user_id, status, invited_by, joined_at, last_activity_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $userId, $status, $invitedBy, $now, $now, $now, $now],
        );

        return $id;
    }

    /** @return array<string, mixed>|null */
    public function find(string $workspaceId, string $userId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM memberships WHERE workspace_id = ? AND user_id = ? AND deleted_at IS NULL',
            [$workspaceId, $userId],
        );
    }

    public function countForWorkspace(string $workspaceId): int
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS c FROM memberships WHERE workspace_id = ? AND deleted_at IS NULL',
            [$workspaceId],
        );

        return (int) ($row['c'] ?? 0);
    }

    /** @return list<array<string, mixed>> workspaces a user belongs to */
    public function workspacesForUser(string $userId): array
    {
        return $this->connection->select(
            'SELECT w.* FROM memberships m
               JOIN workspaces w ON w.id = m.workspace_id
              WHERE m.user_id = ? AND m.deleted_at IS NULL AND w.deleted_at IS NULL
              ORDER BY w.created_at ASC',
            [$userId],
        );
    }
}
