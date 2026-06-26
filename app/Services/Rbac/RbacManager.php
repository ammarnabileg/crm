<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Core\Database;

/**
 * Provisions and maintains the RBAC data defined in config/rbac.php.
 *
 * This is intentionally model-free and takes an explicit Database, so the SAME
 * code path runs during installation (against the installer's connection, before
 * any .env exists) and at runtime (when a new company is created). One source of
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

        foreach ((array) config('rbac.permissions', []) as [$key, $name, $group, $description]) {
            $existing = $this->db->table('permissions')->where('key', '=', $key)->first();

            if ($existing === null) {
                $id = $this->db->table('permissions')->insertGetId([
                    'key'         => $key,
                    'name'        => $name,
                    'group'       => $group,
                    'description' => $description,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            } else {
                $id = (int) $existing['id'];
                $this->db->table('permissions')->where('id', '=', $id)->update([
                    'name'        => $name,
                    'group'       => $group,
                    'description' => $description,
                    'updated_at'  => $now,
                ]);
            }

            $map[$key] = (int) $id;
        }

        return $map;
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
            ->whereNull('company_id')
            ->first();

        if ($role === null) {
            $roleId = $this->db->table('roles')->insertGetId([
                'company_id'  => null,
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
     * Create the default tenant roles for a newly provisioned company.
     *
     * @return array<string, int> role slug => id
     */
    public function provisionCompanyRoles(int $companyId): array
    {
        $now = now();
        $permissionMap = $this->permissionKeyToId();
        $allPermissionIds = array_values($permissionMap);
        $slugToId = [];

        foreach ((array) config('rbac.tenant_roles', []) as $slug => $definition) {
            $existing = $this->db->table('roles')
                ->where('slug', '=', $slug)
                ->where('company_id', '=', $companyId)
                ->first();

            if ($existing !== null) {
                $slugToId[$slug] = (int) $existing['id'];
                continue;
            }

            $roleId = $this->db->table('roles')->insertGetId([
                'company_id'  => $companyId,
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

        // Resolve parent inheritance once all roles for the company exist.
        foreach ((array) config('rbac.tenant_roles', []) as $slug => $definition) {
            if (! empty($definition['parent']) && isset($slugToId[$slug], $slugToId[$definition['parent']])) {
                $this->db->table('roles')->where('id', '=', $slugToId[$slug])
                    ->update(['parent_id' => $slugToId[$definition['parent']]]);
            }
        }

        return $slugToId;
    }

    public function assignGlobalRole(int $userId, int $roleId): void
    {
        if (! $this->db->table('user_role')->where('user_id', '=', $userId)->where('role_id', '=', $roleId)->exists()) {
            $this->db->table('user_role')->insert(['user_id' => $userId, 'role_id' => $roleId]);
        }
    }

    public function assignMembershipRole(int $membershipId, int $roleId): void
    {
        if (! $this->db->table('membership_role')->where('membership_id', '=', $membershipId)->where('role_id', '=', $roleId)->exists()) {
            $this->db->table('membership_role')->insert(['membership_id' => $membershipId, 'role_id' => $roleId]);
        }
    }

    /**
     * @param int[] $permissionIds
     */
    public function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        $this->db->table('permission_role')->where('role_id', '=', $roleId)->delete();
        foreach (array_unique($permissionIds) as $permissionId) {
            $this->db->table('permission_role')->insert([
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
