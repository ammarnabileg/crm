<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * An RBAC role. Not auto tenant-scoped because global roles (workspace_id NULL)
 * and tenant roles coexist in this table; scoping is applied explicitly by the
 * services that read roles so global role templates remain reachable.
 */
final class Role extends Model
{
    protected static string $table = 'roles';
    protected static bool $tenantScoped = false;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'workspace_id', 'parent_id', 'name', 'slug', 'description', 'is_system', 'priority',
    ];

    protected static array $casts = [
        'is_system' => 'bool',
        'priority'  => 'int',
    ];

    public function isGlobal(): bool
    {
        return ($this->attributes['workspace_id'] ?? null) === null;
    }

    /**
     * @return int[] Permission ids granted directly by this role.
     */
    public function permissionIds(): array
    {
        return array_map('intval', static::db()->table('role_permissions')
            ->where('role_id', '=', $this->getKey())
            ->pluck('permission_id'));
    }

    /**
     * @param int[] $permissionIds
     */
    public function syncPermissions(array $permissionIds): void
    {
        static::db()->table('role_permissions')->where('role_id', '=', $this->getKey())->delete();

        foreach (array_unique($permissionIds) as $permissionId) {
            static::db()->table('role_permissions')->insert([
                'role_id'       => $this->getKey(),
                'permission_id' => $permissionId,
            ]);
        }
    }

    public static function findBySlug(string $slug, ?int $workspaceId): ?self
    {
        $query = static::withoutTenantScope()->where('slug', '=', $slug);
        $workspaceId === null ? $query->whereNull('workspace_id') : $query->where('workspace_id', '=', $workspaceId);

        $row = $query->first();

        return $row ? static::hydrate($row) : null;
    }
}
