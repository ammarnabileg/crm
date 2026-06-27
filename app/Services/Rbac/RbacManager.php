<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Core\Database;
use App\Core\Model;

/**
 * Provisions and maintains the RBAC data defined in config/rbac.php.
 *
 * This is intentionally model-free and takes an explicit Database, so the SAME
 * code path runs during installation (against the installer's connection, before
 * any .env exists) and at runtime (when a new workspace is created). One source of
 * truth for "what roles/permissions exist", used everywhere.
 */
final class RbacManager
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Upsert the global permission catalogue from config. Idempotent.
     *
     * @return array<string, int> permission key => id
     */
    public function syncPermissions(): array
    {
        $now = now();
        $map = [];
        $moduleIds = $this->moduleKeyToId();

        foreach ((array) config('rbac.permissions', []) as [$key, $name, $moduleKey, $description]) {
            $moduleId = $moduleIds[$moduleKey] ?? null;
            if ($moduleId === null) {
                throw new \RuntimeException("RBAC: permission '{$key}' references unknown module '{$moduleKey}'.");
            }
            $action = $this->actionFromKey($key);

            $existing = $this->db->table('permissions')->where('key', '=', $key)->first();

            if ($existing === null) {
                $id = $this->db->table('permissions')->insertGetId([
                    'key'         => $key,
                    'module_id'   => $moduleId,
                    'action'      => $action,
                    'name'        => $name,
                    'description' => $description,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            } else {
                $id = (int) $existing['id'];
                $this->db->table('permissions')->where('id', '=', $id)->update([
                    'module_id'   => $moduleId,
                    'action'      => $action,
                    'name'        => $name,
                    'description' => $description,
                    'updated_at'  => $now,
                ]);
            }

            $map[$key] = (int) $id;
        }

        return $map;
    }

    /**
     * @return array<string, int> system module key => id
     */
    private function moduleKeyToId(): array
    {
        $map = [];
        foreach ($this->db->table('system_modules')->select('id', 'key')->get() as $row) {
            $map[$row['key']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * The enforced action is the key's last dot-segment (e.g. `jobs.create` →
     * `create`); permission keys without a dot use the whole key as the action.
     */
    private function actionFromKey(string $key): string
    {
        $pos = strrpos($key, '.');

        return $pos === false ? $key : substr($key, $pos + 1);
    }

    /**
     * Ensure the platform-level super-admin role exists and holds every
     * permission. Returns its id.
     */
    public function ensureSuperAdminRole(): int
    {
        $slug = (string) config('rbac.super_admin_role', 'super-admin');
        $now = now();

        $role = $this->db->table('roles')
            ->where('slug', '=', $slug)
            ->whereNull('workspace_id')
            ->first();

        if ($role === null) {
            $roleId = $this->db->table('roles')->insertGetId([
                'uuid'        => Model::generateUuid(),
                'workspace_id'  => null,
                'name'        => 'Super Admin',
                'slug'        => $slug,
                'description' => 'Platform-wide administrator with unrestricted access.',
                'is_system'   => 1,
                'priority'    => 1000,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        } else {
            $roleId = (int) $role['id'];
        }

        // Super admin always holds every permission.
        $permissionIds = array_map('intval', $this->db->table('permissions')->pluck('id'));
        $this->syncRolePermissions((int) $roleId, $permissionIds);

        return (int) $roleId;
    }

    /**
     * Create the default tenant roles for a newly provisioned workspace.
     *
     * @return array<string, int> role slug => id
     */
    public function provisionWorkspaceRoles(int $workspaceId): array
    {
        $now = now();
        $permissionMap = $this->permissionKeyToId();
        $allPermissionIds = array_values($permissionMap);
        $slugToId = [];

        foreach ((array) config('rbac.tenant_roles', []) as $slug => $definition) {
            $existing = $this->db->table('roles')
                ->where('slug', '=', $slug)
                ->where('workspace_id', '=', $workspaceId)
                ->first();

            if ($existing !== null) {
                $slugToId[$slug] = (int) $existing['id'];
                continue;
            }

            $roleId = $this->db->table('roles')->insertGetId([
                'uuid'        => Model::generateUuid(),
                'workspace_id'  => $workspaceId,
                'parent_id'   => null,
                'name'        => $definition['name'] ?? ucfirst($slug),
                'slug'        => $slug,
                'description' => $definition['description'] ?? null,
                'is_system'   => ! empty($definition['is_system']) ? 1 : 0,
                'priority'    => (int) ($definition['priority'] ?? 0),
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $permissions = $definition['permissions'] ?? [];
            $ids = $permissions === '*'
                ? $allPermissionIds
                : array_values(array_filter(array_map(
                    static fn (string $key): ?int => $permissionMap[$key] ?? null,
                    (array) $permissions
                )));

            $this->syncRolePermissions((int) $roleId, $ids);
            $slugToId[$slug] = (int) $roleId;
        }

        // Resolve parent inheritance once all roles for the workspace exist.
        foreach ((array) config('rbac.tenant_roles', []) as $slug => $definition) {
            if (! empty($definition['parent']) && isset($slugToId[$slug], $slugToId[$definition['parent']])) {
                $this->db->table('roles')->where('id', '=', $slugToId[$slug])
                    ->update(['parent_id' => $slugToId[$definition['parent']]]);
            }
        }

        return $slugToId;
    }

    /**
     * Re-sync the whole RBAC surface from config/rbac.php — the self-healing path
     * for permission drift after new permissions are added to config on an
     * already-installed system (no terminal). Idempotent: upserts the permission
     * catalogue, re-grants the super-admin every permission, and re-applies each
     * existing workspace system role's configured permission set (owner `*` = all).
     * Fresh installs already get this via DatabaseSeeder; re-running is harmless.
     */
    public function resyncSystemRolePermissions(): void
    {
        $this->syncPermissions();
        $this->ensureSuperAdminRole();

        $permissionMap = $this->permissionKeyToId();
        $allPermissionIds = array_values($permissionMap);
        $defs = (array) config('rbac.tenant_roles', []);

        foreach ($this->db->table('roles')->whereNotNull('workspace_id')->get() as $role) {
            $slug = (string) $role['slug'];
            if (! isset($defs[$slug])) {
                continue;
            }
            $permissions = $defs[$slug]['permissions'] ?? [];
            $ids = $permissions === '*'
                ? $allPermissionIds
                : array_values(array_filter(array_map(
                    static fn (string $key): ?int => $permissionMap[$key] ?? null,
                    (array) $permissions
                )));
            $this->syncRolePermissions((int) $role['id'], $ids);
        }
    }

    public function assignGlobalRole(int $userId, int $roleId): void
    {
        if (! $this->db->table('user_roles')->where('user_id', '=', $userId)->where('role_id', '=', $roleId)->exists()) {
            $this->db->table('user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);
        }
    }

    public function assignMembershipRole(int $membershipId, int $roleId): void
    {
        if (! $this->db->table('membership_roles')->where('membership_id', '=', $membershipId)->where('role_id', '=', $roleId)->exists()) {
            $this->db->table('membership_roles')->insert(['membership_id' => $membershipId, 'role_id' => $roleId]);
        }
    }

    /**
     * @param int[] $permissionIds
     */
    public function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        $this->db->table('role_permissions')->where('role_id', '=', $roleId)->delete();
        foreach (array_unique($permissionIds) as $permissionId) {
            $this->db->table('role_permissions')->insert([
                'role_id'       => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    /**
     * @return array<string, int>
     */
    private function permissionKeyToId(): array
    {
        $map = [];
        foreach ($this->db->table('permissions')->select('id', 'key')->get() as $row) {
            $map[$row['key']] = (int) $row['id'];
        }

        return $map;
    }
}
