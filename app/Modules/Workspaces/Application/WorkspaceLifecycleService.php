<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Contracts\MemberDirectory;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Modules\Permissions\Domain\PermissionCatalog;

/**
 * Workspace lifecycle (Sprint 2): archive / restore / suspend / resume /
 * transfer-ownership. A Workspace is a workspace, not a company — it has a full
 * lifecycle independent of any one user. Ownership transfer keeps the invariant
 * that the owner is a member with the full permission set (WORKSPACE_MODEL.md).
 */
final class WorkspaceLifecycleService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MemberDirectory $memberships,
        private readonly RoleService $roles,
    ) {
    }

    /** Owner/admin pauses the workspace (recoverable). */
    public function archive(string $workspaceId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            "UPDATE workspaces SET status = 'archived', archived_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL",
            [$now, $now, $workspaceId],
        );
    }

    public function restore(string $workspaceId): void
    {
        $this->connection->statement(
            "UPDATE workspaces SET status = 'active', archived_at = NULL, updated_at = ? WHERE id = ? AND deleted_at IS NULL",
            [gmdate('Y-m-d H:i:s'), $workspaceId],
        );
    }

    /** System Owner suspends a workspace (e.g. policy/billing) — blocks operation. */
    public function suspend(string $workspaceId): void
    {
        $this->connection->statement(
            "UPDATE workspaces SET status = 'suspended', updated_at = ? WHERE id = ? AND deleted_at IS NULL",
            [gmdate('Y-m-d H:i:s'), $workspaceId],
        );
    }

    public function resume(string $workspaceId): void
    {
        $this->connection->statement(
            "UPDATE workspaces SET status = 'active', updated_at = ? WHERE id = ? AND deleted_at IS NULL AND status = 'suspended'",
            [gmdate('Y-m-d H:i:s'), $workspaceId],
        );
    }

    /**
     * Transfer ownership to another user. The new owner becomes a member (if not
     * already) with the full workspace permission set; the workspace's owner_user_id
     * is updated. Returns true on success, false if validation fails.
     */
    public function transferOwnership(string $workspaceId, string $newOwnerUserId): bool
    {
        $ws = $this->connection->selectOne('SELECT id, owner_user_id FROM workspaces WHERE id = ? AND deleted_at IS NULL', [$workspaceId]);
        if ($ws === null || $newOwnerUserId === '' || (string) $ws['owner_user_id'] === $newOwnerUserId) {
            return false;
        }
        $user = $this->connection->selectOne('SELECT id FROM users WHERE id = ? AND deleted_at IS NULL', [$newOwnerUserId]);
        if ($user === null) {
            return false;
        }

        return $this->connection->transaction(function () use ($workspaceId, $newOwnerUserId): bool {
            $membership = $this->memberships->find($workspaceId, $newOwnerUserId);
            $membershipId = $membership !== null
                ? (string) $membership['id']
                : $this->memberships->create($workspaceId, $newOwnerUserId, status: 'active');

            // The new owner gets every workspace permission by direct grant.
            $this->roles->grantToMembership($membershipId, PermissionCatalog::workspaceKeys());

            $this->connection->statement(
                'UPDATE workspaces SET owner_user_id = ?, updated_at = ? WHERE id = ?',
                [$newOwnerUserId, gmdate('Y-m-d H:i:s'), $workspaceId],
            );

            return true;
        });
    }

    /** @return array<string,mixed>|null */
    public function find(string $workspaceId): ?array
    {
        return $this->connection->selectOne('SELECT * FROM workspaces WHERE id = ? AND deleted_at IS NULL', [$workspaceId]);
    }
}
