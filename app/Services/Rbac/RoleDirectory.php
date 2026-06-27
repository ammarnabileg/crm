<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Core\Database;
use App\Models\Role;

/**
 * Read helper for the Roles & Permissions module — keeps RoleController thin.
 *
 * Every query is EXPLICITLY scoped to a single workspace id (passed in) so the
 * directory never leaks roles across tenants. It only READS: all writes go
 * through RbacManager (the one source of truth for role_permissions), reused so
 * the same code path used at provisioning time backs the editor.
 *
 * The permission catalogue is grouped by its MODULE (system_modules — the module
 * IS the permission group, docs/database/12 R1-03), built from
 * system_modules -> permissions(module_id), never a hard-coded list.
 */
final class RoleDirectory
{
    private Database $db;

    public function __construct(private readonly int $workspaceId)
    {
        $this->db = app('db');
    }

    /**
     * The workspace's roles, priority-first, each with its member count (distinct
     * memberships that hold the role) and granted-permission count. One grouped
     * query loads the counts to avoid an N+1 over the list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function roles(): array
    {
        $rows = $this->db->table('roles')
            ->select('id', 'name', 'slug', 'description', 'priority', 'is_system')
            ->where('workspace_id', '=', $this->workspaceId)
            ->whereNull('deleted_at')
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->get();

        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $memberCounts = $this->memberCounts($ids);
        $permissionCounts = $this->permissionCounts($ids);

        foreach ($rows as &$row) {
            $id = (int) $row['id'];
            $row['is_system']        = (bool) $row['is_system'];
            $row['priority']         = (int) $row['priority'];
            $row['member_count']     = $memberCounts[$id] ?? 0;
            $row['permission_count'] = $permissionCounts[$id] ?? 0;
        }
        unset($row);

        return $rows;
    }

    /**
     * A single workspace role (tenant-scoped), or null when it does not belong to
     * this workspace — the guard that stops cross-tenant edits.
     */
    public function find(int $roleId): ?Role
    {
        $row = Role::withoutTenantScope()
            ->where('id', '=', $roleId)
            ->where('workspace_id', '=', $this->workspaceId)
            ->whereNull('deleted_at')
            ->first();

        return $row ? Role::hydrate($row) : null;
    }

    /**
     * The full permission catalogue grouped by system module for the matrix:
     * one entry per active module that owns at least one permission, in module
     * sort order, each permission name-ordered. Empty modules are omitted.
     *
     * @return array<int, array{key:string, label:string, permissions: array<int, array{id:int, key:string, name:string, description:?string}>}>
     */
    public function permissionCatalogue(): array
    {
        // Platform-scope modules (config rbac.platform_modules) are super-admin only
        // — their permissions (e.g. system.manage) are never shown in, or grantable
        // from, the tenant role matrix. Mirrors RbacManager::tenantGrantablePermissionIds.
        $platformModuleKeys = (array) config('rbac.platform_modules', []);

        $modules = [];
        $order = [];
        foreach ($this->db->table('system_modules')
            ->select('id', 'key', 'label')
            ->where('is_active', '=', 1)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get() as $i => $module) {
            if (in_array((string) $module['key'], $platformModuleKeys, true)) {
                continue; // platform-scope module — hidden from tenants
            }
            $id = (int) $module['id'];
            $modules[$id] = [
                'key'         => (string) $module['key'],
                'label'       => (string) $module['label'],
                'permissions' => [],
            ];
            $order[$id] = $i;
        }

        foreach ($this->db->table('permissions')
            ->select('id', 'key', 'module_id', 'name', 'description')
            ->orderBy('name')
            ->get() as $permission) {
            $moduleId = (int) $permission['module_id'];
            if (! isset($modules[$moduleId])) {
                continue; // permission for an inactive/unknown module — not shown
            }
            $modules[$moduleId]['permissions'][] = [
                'id'          => (int) $permission['id'],
                'key'         => (string) $permission['key'],
                'name'        => (string) $permission['name'],
                'description' => $permission['description'] !== null ? (string) $permission['description'] : null,
            ];
        }

        // Drop empty modules, then return as a list in module sort order.
        $grouped = array_filter($modules, static fn (array $m): bool => $m['permissions'] !== []);
        uksort($grouped, static fn (int $a, int $b): int => ($order[$a] ?? 0) <=> ($order[$b] ?? 0));

        return array_values($grouped);
    }

    /**
     * The permission ids granted to a role (its direct role_permissions rows).
     *
     * @return int[]
     */
    public function grantedPermissionIds(int $roleId): array
    {
        return array_map('intval', $this->db->table('role_permissions')
            ->where('role_id', '=', $roleId)
            ->pluck('permission_id'));
    }

    /**
     * The flat list of permission ids a tenant role may be granted — every
     * permission shown in the matrix (so platform-scope permissions, hidden from
     * the catalogue, are excluded). The controller intersects submitted ids with
     * this set so a hand-crafted POST can never grant a platform permission.
     *
     * @return int[]
     */
    public function grantablePermissionIds(): array
    {
        $ids = [];
        foreach ($this->permissionCatalogue() as $module) {
            foreach ($module['permissions'] as $permission) {
                $ids[] = (int) $permission['id'];
            }
        }

        return $ids;
    }

    /**
     * Total number of GRANTABLE (tenant-scope) permissions — used to render an
     * owner-style "all permissions" role (`*`) as fully granted without per-row
     * state. Excludes platform-scope permissions so the count matches the matrix.
     */
    public function totalPermissionCount(): int
    {
        return count($this->grantablePermissionIds());
    }

    /**
     * Distinct member count per role (memberships in THIS workspace that hold it).
     *
     * @param int[] $roleIds
     * @return array<int, int> role id => member count
     */
    private function memberCounts(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $rows = $this->db->table('membership_roles')
            ->select('membership_roles.role_id')
            ->join('memberships', 'memberships.id', '=', 'membership_roles.membership_id')
            ->where('memberships.workspace_id', '=', $this->workspaceId)
            ->whereIn('membership_roles.role_id', $roleIds)
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $id = (int) $row['role_id'];
            $counts[$id] = ($counts[$id] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Granted-permission count per role, in one grouped query.
     *
     * @param int[] $roleIds
     * @return array<int, int> role id => permission count
     */
    private function permissionCounts(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $rows = $this->db->table('role_permissions')
            ->select('role_id')
            ->whereIn('role_id', $roleIds)
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $id = (int) $row['role_id'];
            $counts[$id] = ($counts[$id] ?? 0) + 1;
        }

        return $counts;
    }
}
