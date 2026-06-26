<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * The one and only user entity. Everything a user "is" (candidate, HR, owner,
 * super admin, ...) derives from roles, not from a type column or a separate
 * table. Helper methods here delegate heavy permission resolution to the
 * AccessControl service to keep a single source of truth.
 */
final class User extends Model
{
    protected static string $table = 'users';
    protected static bool $tenantScoped = false;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'name', 'email', 'password', 'phone', 'avatar',
        'locale', 'timezone', 'status', 'email_verified_at',
        'last_login_at', 'last_login_ip', 'remember_token',
    ];

    protected static array $hidden = ['password', 'remember_token'];

    public function isActive(): bool
    {
        return ($this->attributes['status'] ?? 'active') === 'active';
    }

    /**
     * True when the user holds the platform-level super-admin role (a global
     * role with no workspace_id). Super admins bypass tenant scoping.
     */
    public function isSuperAdmin(): bool
    {
        $superSlug = (string) config('auth.super_admin_role', 'super-admin');

        return static::db()->table('user_role')
            ->join('roles', 'roles.id', '=', 'user_role.role_id')
            ->where('user_role.user_id', '=', $this->getKey())
            ->where('roles.slug', '=', $superSlug)
            ->whereNull('roles.workspace_id')
            ->exists();
    }

    /**
     * @return Membership[] All memberships for this user.
     */
    public function memberships(): array
    {
        return array_map(
            [Membership::class, 'hydrate'],
            Membership::withoutTenantScope()->where('user_id', '=', $this->getKey())->get()
        );
    }

    public function membershipFor(int $workspaceId): ?Membership
    {
        $row = Membership::withoutTenantScope()
            ->where('user_id', '=', $this->getKey())
            ->where('workspace_id', '=', $workspaceId)
            ->first();

        return $row ? Membership::hydrate($row) : null;
    }

    /**
     * Workspaces this user can access (active memberships), most-recent first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function workspaces(): array
    {
        return static::db()->table('workspaces')
            ->select('workspaces.*', 'memberships.status AS membership_status')
            ->join('memberships', 'memberships.workspace_id', '=', 'workspaces.id')
            ->where('memberships.user_id', '=', $this->getKey())
            ->where('memberships.status', '=', 'active')
            ->orderBy('workspaces.created_at', 'desc')
            ->get();
    }

    public function ownsWorkspace(int $workspaceId): bool
    {
        return static::db()->table('workspaces')
            ->where('id', '=', $workspaceId)
            ->where('owner_id', '=', $this->getKey())
            ->exists();
    }

    public function recordLogin(string $ip): void
    {
        static::db()->table('users')->where('id', '=', $this->getKey())->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) ($this->attributes['name'] ?? ''))) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_substr($part, 0, 1);
        }

        return mb_strtoupper($initials ?: 'U');
    }
}
