<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Memberships\Application;

use HaHireAI\Core\Contracts\MemberDirectory;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Links a User to a Workspace (docs/MEMBERSHIP_ENGINE.md). The shared membership
 * surface is the MemberDirectory contract (ARCHITECTURE.md §4).
 */
final class MembershipService implements MemberDirectory
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

        // last_activity_at stays null until the member actually works in the
        // workspace (stamped on the request hot path) — "joined" ≠ "active".
        $this->connection->statement(
            'INSERT INTO memberships (id, workspace_id, user_id, status, invited_by, joined_at, last_activity_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $userId, $status, $invitedBy, $now, null, $now, $now],
        );

        return $id;
    }

    /** @return array<string, mixed>|null */
    public function findById(string $membershipId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM memberships WHERE id = ? AND deleted_at IS NULL',
            [$membershipId],
        );
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

    /**
     * Workspaces a user belongs to, enriched for the "My Workspaces" page
     * (owner, member count, plan, subscription status).
     *
     * @return list<array<string, mixed>>
     */
    public function workspacesForUserDetailed(string $userId): array
    {
        return $this->connection->select(
            'SELECT w.id, w.name, w.status, w.created_at, ow.name AS owner_name,
                    (SELECT COUNT(*) FROM memberships m2 WHERE m2.workspace_id = w.id AND m2.deleted_at IS NULL) AS members,
                    (SELECT p.name FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.workspace_id = w.id LIMIT 1) AS plan_name,
                    (SELECT s.status FROM subscriptions s WHERE s.workspace_id = w.id LIMIT 1) AS sub_status
               FROM memberships m
               JOIN workspaces w ON w.id = m.workspace_id
               LEFT JOIN users ow ON ow.id = w.owner_user_id
              WHERE m.user_id = ? AND m.deleted_at IS NULL AND w.deleted_at IS NULL
              ORDER BY w.created_at DESC',
            [$userId],
        );
    }

    /** @return list<array<string, mixed>> members of a workspace with their role names + activity */
    public function membersForWorkspace(string $workspaceId): array
    {
        return $this->connection->select(
            "SELECT m.id AS membership_id, m.user_id, m.status, m.joined_at, m.last_activity_at, m.created_at,
                    u.name, u.email, u.last_login_at,
                    inv.name AS invited_by_name,
                    (SELECT GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ')
                       FROM membership_roles mr JOIN roles r ON r.id = mr.role_id
                      WHERE mr.membership_id = m.id) AS roles
               FROM memberships m
               JOIN users u ON u.id = m.user_id
               LEFT JOIN users inv ON inv.id = m.invited_by
              WHERE m.workspace_id = ? AND m.deleted_at IS NULL
              ORDER BY m.created_at ASC",
            [$workspaceId],
        );
    }

    /**
     * Change a member's status (active|suspended|invited). Tenant-guarded: the
     * membership must belong to the given workspace.
     */
    public function setStatus(string $workspaceId, string $membershipId, string $status): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $affected = $this->connection->statement(
            'UPDATE memberships SET status = ?, updated_at = ? WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$status, $now, $membershipId, $workspaceId],
        );

        return $affected > 0;
    }

    /**
     * Remove a member from the workspace (soft delete + status flag). Their
     * role/permission grants cascade away with the membership row. Tenant-guarded.
     */
    public function remove(string $workspaceId, string $membershipId): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $affected = $this->connection->statement(
            "UPDATE memberships SET status = 'removed', deleted_at = ?, updated_at = ? WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL",
            [$now, $now, $membershipId, $workspaceId],
        );

        return $affected > 0;
    }

    /** Stamp the member's last activity (called on the request hot path, throttled by the caller). */
    public function touchActivity(string $membershipId): void
    {
        $this->connection->statement(
            'UPDATE memberships SET last_activity_at = ? WHERE id = ?',
            [gmdate('Y-m-d H:i:s'), $membershipId],
        );
    }
}
