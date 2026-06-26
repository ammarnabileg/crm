<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * An RBAC role. Not auto tenant-scoped because global roles (company_id NULL)
 * and tenant roles coexist in this table; scoping is applied explicitly by the
 * services that read roles so global role templates remain reachable.
 */
final class Role extends Model
{
    protected static string $table = 'roles';
    protected static bool $tenantScoped = false;

    protected static array $fillable = [
        'company_id', 'parent_id', 'name', 'slug', 'description', 'is_system', 'priority',
    ];

    protected static array $casts = [
        'is_system' => 'bool',
        'priority'  => 'int',
    ];

    public function isGlobal(): bool
    {
        return ($this->attributes['company_id'] ?? null) === null;
    }

    /**
     * @return int[] Permission ids granted directly by this role.
     */
    public function permissionIds(): array
    {
        return array_map('intval', static::db()->table('permission_role')
            ->where('role_id', '=', $this->getKey())
            ->pluck('permission_id'));
    }

    /**
     * @param int[] $permissionIds
     */
    public function syncPermissions(array $permissionIds): void
    {
        static::db()->table('permission_role')->where('role_id', '=', $this->getKey())->delete();

        foreach (array_unique($permissionIds) as $permissionId) {
            static::db()->table('permission_role')->insert([
                'role_id'       => $this->getKey(),
                'permission_id' => $permissionId,
            ]);
        }
    }

    public static function findBySlug(string $slug, ?int $companyId): ?self
    {
        $query = static::withoutTenantScope()->where('slug', '=', $slug);
        $companyId === null ? $query->whereNull('company_id') : $query->where('company_id', '=', $companyId);

        $row = $query->first();

        return $row ? static::hydrate($row) : null;
    }
}
