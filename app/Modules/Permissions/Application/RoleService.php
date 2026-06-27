<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Permissions\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Permissions\Infrastructure\PermissionRepository;
use HaHireAI\Shared\Ulid;

/**
 * Roles as data (docs/ROLE_BUILDER.md). No reserved roles; the workspace owner
 * (or anyone with role.* permissions) builds roles and assigns permission keys.
 */
final class RoleService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PermissionRepository $permissions,
    ) {
    }

    /**
     * Create a workspace role and grant it the given permission keys.
     *
     * @param  list<string>  $permissionKeys
     */
    public function createRole(string $workspaceId, string $name, array $permissionKeys = [], ?string $description = null): string
    {
        $roleId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->statement(
            'INSERT INTO roles (id, workspace_id, name, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$roleId, $workspaceId, $name, $description, $now, $now],
        );

        $this->assignPermissions($roleId, $permissionKeys);

        return $roleId;
    }

    /** @param list<string> $permissionKeys */
    public function assignPermissions(string $roleId, array $permissionKeys): void
    {
        $now = gmdate('Y-m-d H:i:s');

        foreach ($this->permissions->idsForKeys($permissionKeys) as $permissionId) {
            $this->connection->statement(
                'INSERT IGNORE INTO role_permissions (id, role_id, permission_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                [Ulid::generate(), $roleId, $permissionId, $now, $now],
            );
        }
    }

    /** Clone a role (its name + its permissions) within the same workspace. */
    public function cloneRole(string $workspaceId, string $sourceRoleId, string $newName): string
    {
        $keys = $this->permissionKeysForRole($sourceRoleId);

        return $this->createRole($workspaceId, $newName, $keys);
    }

    public function assignRoleToMembership(string $membershipId, string $roleId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT IGNORE INTO membership_roles (id, membership_id, role_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [Ulid::generate(), $membershipId, $roleId, $now, $now],
        );
    }

    /**
     * Grant permissions directly to a membership (used for the workspace owner,
     * who receives full permissions by direct grant — not via a reserved role).
     *
     * @param  list<string>  $permissionKeys
     */
    public function grantToMembership(string $membershipId, array $permissionKeys): void
    {
        $now = gmdate('Y-m-d H:i:s');

        foreach ($this->permissions->idsForKeys($permissionKeys) as $permissionId) {
            $this->connection->statement(
                'INSERT IGNORE INTO membership_permissions (id, membership_id, permission_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                [Ulid::generate(), $membershipId, $permissionId, $now, $now],
            );
        }
    }

    /** @return list<string> */
    public function permissionKeysForRole(string $roleId): array
    {
        $rows = $this->connection->select(
            'SELECT p.`key` FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
            [$roleId],
        );

        return array_map(static fn (array $r): string => (string) $r['key'], $rows);
    }

    public function deleteRole(string $workspaceId, string $roleId): void
    {
        // Tenant guard: only delete a role that belongs to this workspace.
        $this->connection->statement('DELETE FROM roles WHERE id = ? AND workspace_id = ?', [$roleId, $workspaceId]);
    }
}
