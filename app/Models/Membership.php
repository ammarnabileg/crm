<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A user's membership within a company, carrying the roles that user holds in
 * that specific tenant. Tenant-scoped, so listing "the members of the current
 * company" is automatically isolated; cross-company lookups use
 * withoutTenantScope().
 */
final class Membership extends Model
{
    protected static string $table = 'memberships';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'company_id', 'user_id', 'status', 'title', 'invited_by', 'invited_at', 'joined_at',
    ];

    public function user(): ?User
    {
        $id = $this->attributes['user_id'] ?? null;

        return $id ? User::find((int) $id) : null;
    }

    /**
     * @return Role[] Roles attached to this membership (tenant roles).
     */
    public function roles(): array
    {
        $rows = static::db()->table('roles')
            ->select('roles.*')
            ->join('membership_role', 'membership_role.role_id', '=', 'roles.id')
            ->where('membership_role.membership_id', '=', $this->getKey())
            ->get();

        return array_map([Role::class, 'hydrate'], $rows);
    }

    /**
     * @param int[] $roleIds
     */
    public function syncRoles(array $roleIds): void
    {
        static::db()->table('membership_role')->where('membership_id', '=', $this->getKey())->delete();

        foreach (array_unique($roleIds) as $roleId) {
            static::db()->table('membership_role')->insert([
                'membership_id' => $this->getKey(),
                'role_id'       => $roleId,
            ]);
        }
    }
}
