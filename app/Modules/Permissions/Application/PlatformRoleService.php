<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Permissions\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Permissions\Domain\PermissionCatalog;
use HaHireAI\Shared\Ulid;

/**
 * Platform-level roles & permissions (System Owner): named roles built from the
 * system.* catalog and assigned to users, so the platform can have granular
 * "site managers" rather than an all-or-nothing System Owner flag. Permission
 * resolution for access lives in Authorizer::systemPermissionsForUser.
 */
final class PlatformRoleService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** The system.* permission catalog (key => description) for the role builder. */
    public function systemPermissions(): array
    {
        $out = [];
        foreach (PermissionCatalog::all() as $perm) {
            if (($perm['category'] ?? '') === 'system' || str_starts_with((string) ($perm['key'] ?? ''), 'system.')) {
                $out[(string) $perm['key']] = (string) ($perm['description'] ?? $perm['key']);
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> roles with their permission keys + holder count */
    public function allRoles(): array
    {
        $roles = $this->connection->select(
            'SELECT id, name, description, created_at FROM platform_roles WHERE deleted_at IS NULL ORDER BY name ASC',
        );
        foreach ($roles as &$role) {
            $role['permissions'] = $this->permissionKeys((string) $role['id']);
            $role['holders'] = $this->holders((string) $role['id']);
        }

        return $roles;
    }

    /** @return list<string> permission keys attached to a role */
    public function permissionKeys(string $roleId): array
    {
        $rows = $this->connection->select(
            'SELECT p.`key` FROM platform_role_permissions prp JOIN permissions p ON p.id = prp.permission_id WHERE prp.role_id = ? ORDER BY p.`key`',
            [$roleId],
        );

        return array_map(static fn (array $r): string => (string) $r['key'], $rows);
    }

    /** @return list<array<string, mixed>> users assigned this role */
    public function holders(string $roleId): array
    {
        return $this->connection->select(
            'SELECT u.id, u.name, u.email FROM platform_role_user pru JOIN users u ON u.id = pru.user_id
              WHERE pru.role_id = ? AND u.deleted_at IS NULL ORDER BY u.name',
            [$roleId],
        );
    }

    /** @return array<string, mixed>|null */
    public function find(string $roleId): ?array
    {
        return $this->connection->selectOne('SELECT id, name, description FROM platform_roles WHERE id = ? AND deleted_at IS NULL', [$roleId]);
    }

    /** @param list<string> $permissionKeys */
    public function create(string $name, ?string $description, array $permissionKeys): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO platform_roles (id, name, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$id, $name, $description, $now, $now],
        );
        $this->syncPermissions($id, $permissionKeys);

        return $id;
    }

    /** @param list<string> $permissionKeys */
    public function update(string $roleId, string $name, ?string $description, array $permissionKeys): void
    {
        $this->connection->statement(
            'UPDATE platform_roles SET name = ?, description = ?, updated_at = ? WHERE id = ?',
            [$name, $description, gmdate('Y-m-d H:i:s'), $roleId],
        );
        $this->syncPermissions($roleId, $permissionKeys);
    }

    public function delete(string $roleId): void
    {
        $this->connection->statement('UPDATE platform_roles SET deleted_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $roleId]);
        $this->connection->statement('DELETE FROM platform_role_permissions WHERE role_id = ?', [$roleId]);
        $this->connection->statement('DELETE FROM platform_role_user WHERE role_id = ?', [$roleId]);
    }

    public function assignUser(string $roleId, string $userId): void
    {
        if ($this->find($roleId) === null) {
            return;
        }
        $existing = $this->connection->selectOne('SELECT id FROM platform_role_user WHERE role_id = ? AND user_id = ?', [$roleId, $userId]);
        if ($existing !== null) {
            return;
        }
        $this->connection->statement(
            'INSERT INTO platform_role_user (id, role_id, user_id, created_at) VALUES (?, ?, ?, ?)',
            [Ulid::generate(), $roleId, $userId, gmdate('Y-m-d H:i:s')],
        );
    }

    public function unassignUser(string $roleId, string $userId): void
    {
        $this->connection->statement('DELETE FROM platform_role_user WHERE role_id = ? AND user_id = ?', [$roleId, $userId]);
    }

    /** @return list<array<string, mixed>> candidate users to assign (id, name, email) */
    public function assignableUsers(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        return $this->connection->select(
            "SELECT id, name, email FROM users WHERE deleted_at IS NULL AND status = 'active' ORDER BY name ASC LIMIT " . $limit,
        );
    }

    /** @param list<string> $permissionKeys */
    private function syncPermissions(string $roleId, array $permissionKeys): void
    {
        $this->connection->statement('DELETE FROM platform_role_permissions WHERE role_id = ?', [$roleId]);
        $keys = array_values(array_unique(array_filter($permissionKeys)));
        if ($keys === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $perms = $this->connection->select(
            "SELECT id, `key` FROM permissions WHERE is_system = 1 AND `key` IN ({$placeholders})",
            $keys,
        );
        $now = gmdate('Y-m-d H:i:s');
        foreach ($perms as $perm) {
            $this->connection->statement(
                'INSERT INTO platform_role_permissions (id, role_id, permission_id, created_at) VALUES (?, ?, ?, ?)',
                [Ulid::generate(), $roleId, (string) $perm['id'], $now],
            );
        }
    }
}
