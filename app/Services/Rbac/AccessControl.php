<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\User;
use App\Services\Auth\AuthManager;
use App\Services\Tenancy\TenantManager;
use Closure;

/**
 * The RBAC decision engine.
 *
 * Authorization is expressed purely in terms of permissions, roles, role
 * inheritance and policy gates — never "if user type == admin". A check
 * resolves a user's EFFECTIVE permission set for the active tenant by unioning:
 *   1. permissions of global roles assigned to the user (e.g. super-admin), and
 *   2. permissions of the tenant roles attached to the user's membership,
 * with each role expanded up its parent chain (inheritance). Super admins are
 * granted everything. Policy gates allow context-aware ("own record") rules
 * that pure permission flags cannot express — this is the "dynamic permissions"
 * layer.
 */
final class AccessControl
{
    /** @var array<string, Closure> ability => gate callback */
    private array $gates = [];

    /** @var array<string, array<string, bool>> per-request permission cache: "userId:workspaceId" => [key => true] */
    private array $cache = [];

    public function __construct(
        private readonly AuthManager $auth,
        private readonly TenantManager $tenant,
    ) {
    }

    /**
     * Register a policy gate for an ability. The callback receives
     * (User $user, mixed $context) and returns bool.
     */
    public function define(string $ability, Closure $callback): void
    {
        $this->gates[$ability] = $callback;
    }

    public function allows(string $ability, mixed $context = null): bool
    {
        $user = $this->auth->user();
        if ($user === null) {
            return false;
        }

        // Super admins bypass every check.
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Policy gates take precedence ONLY when a concrete object is supplied —
        // they express context-aware rules (ownership, "own record"). Without a
        // context (e.g. a plain `permission:` middleware check), fall through to
        // the permission lookup so the two usages never collide (docs/47).
        if ($context !== null && isset($this->gates[$ability])) {
            return (bool) ($this->gates[$ability])($user, $context);
        }

        return $this->hasPermission($user, $ability);
    }

    public function denies(string $ability, mixed $context = null): bool
    {
        return ! $this->allows($ability, $context);
    }

    public function hasPermission(User $user, string $permissionKey): bool
    {
        return $this->effectivePermissions($user)[$permissionKey] ?? false;
    }

    public function hasAnyPermission(User $user, array $keys): bool
    {
        $permissions = $this->effectivePermissions($user);
        foreach ($keys as $key) {
            if ($permissions[$key] ?? false) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(string $slug): bool
    {
        $user = $this->auth->user();
        if ($user === null) {
            return false;
        }

        return in_array($slug, $this->roleSlugs($user), true);
    }

    /**
     * @return string[] Effective permission keys for the current user/tenant.
     */
    public function permissionKeys(): array
    {
        $user = $this->auth->user();
        if ($user === null) {
            return [];
        }

        return array_keys($this->effectivePermissions($user));
    }

    /**
     * @return array<string, bool> permission key => true
     */
    private function effectivePermissions(User $user): array
    {
        $workspaceId = $this->tenant->id();
        $cacheKey = $user->getKey() . ':' . ($workspaceId ?? '0');

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $roleIds = $this->resolveRoleIds($user, $workspaceId);
        if ($roleIds === []) {
            return $this->cache[$cacheKey] = [];
        }

        $keys = app('db')->table('permissions')
            ->select('permissions.key')
            ->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->whereIn('role_permissions.role_id', $roleIds)
            ->distinct()
            ->pluck('key');

        $map = [];
        foreach ($keys as $key) {
            $map[$key] = true;
        }

        return $this->cache[$cacheKey] = $map;
    }

    /**
     * Resolve all role ids that apply to the user in the given tenant,
     * expanded up the inheritance chain.
     *
     * @return int[]
     */
    private function resolveRoleIds(User $user, ?int $workspaceId): array
    {
        $db = app('db');

        // 1. Global roles assigned directly to the user.
        $directIds = array_map('intval', $db->table('user_roles')
            ->where('user_id', '=', $user->getKey())
            ->pluck('role_id'));

        // 2. Tenant roles via the user's membership in the active workspace.
        if ($workspaceId !== null) {
            $membershipId = $db->table('memberships')
                ->where('user_id', '=', $user->getKey())
                ->where('workspace_id', '=', $workspaceId)
                ->where('status', '=', 'active')
                ->value('id');

            if ($membershipId !== null) {
                $tenantRoleIds = array_map('intval', $db->table('membership_roles')
                    ->where('membership_id', '=', (int) $membershipId)
                    ->pluck('role_id'));
                $directIds = array_merge($directIds, $tenantRoleIds);
            }
        }

        return $this->expandWithAncestors(array_unique($directIds));
    }

    /**
     * Walk parent_id chains so a child role inherits its ancestors' permissions.
     *
     * @param int[] $roleIds
     * @return int[]
     */
    private function expandWithAncestors(array $roleIds): array
    {
        $db = app('db');
        $resolved = [];
        $queue = $roleIds;

        while ($queue !== []) {
            $id = array_shift($queue);
            if ($id === 0 || isset($resolved[$id])) {
                continue;
            }
            $resolved[$id] = true;

            $parentId = $db->table('roles')->where('id', '=', $id)->value('parent_id');
            if ($parentId !== null && ! isset($resolved[(int) $parentId])) {
                $queue[] = (int) $parentId;
            }
        }

        return array_keys($resolved);
    }

    /**
     * @return string[] Slugs of the user's effective roles (incl. inherited).
     */
    public function roleSlugs(User $user): array
    {
        $ids = $this->resolveRoleIds($user, $this->tenant->id());
        if ($ids === []) {
            return [];
        }

        return app('db')->table('roles')->whereIn('id', $ids)->pluck('slug');
    }

    public function flushCache(): void
    {
        $this->cache = [];
    }
}
