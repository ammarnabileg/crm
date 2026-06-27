<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Core\Database;
use App\Core\Hash;
use App\Core\Model;
use App\Models\ActivityLog;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;

/**
 * Read/write helper for the Members module — keeps MemberController thin.
 *
 * Everything here is EXPLICITLY scoped to a single workspace id (passed in), so
 * the directory never leaks across tenants. Role assignment is delegated to
 * RbacManager (the one source of truth for membership_roles) so the same code
 * path used at provisioning time is reused here.
 */
final class MemberDirectory
{
    private Database $db;

    public function __construct(private readonly int $workspaceId)
    {
        $this->db = app('db');
    }

    /**
     * List the workspace's members (memberships joined to users), each with its
     * status key and attached tenant roles, name-ordered. One grouped query loads
     * every membership's roles to avoid an N+1 over the list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function members(): array
    {
        $rows = $this->db->table('memberships')
            ->select(
                'memberships.id',
                'memberships.user_id',
                'memberships.title',
                'memberships.membership_status_id',
                'memberships.joined_at',
                'memberships.invited_at',
                'users.name AS name',
                'users.email AS email',
                'membership_status.key AS status',
            )
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->leftJoin('lookup_values AS membership_status', 'membership_status.id', '=', 'memberships.membership_status_id')
            ->where('memberships.workspace_id', '=', $this->workspaceId)
            ->orderBy('users.name')
            ->get();

        $rolesByMembership = $this->rolesByMembership(array_map(
            static fn (array $r): int => (int) $r['id'],
            $rows
        ));

        foreach ($rows as &$row) {
            $row['roles'] = $rolesByMembership[(int) $row['id']] ?? [];
        }
        unset($row);

        return $rows;
    }

    /**
     * Roles attached to every given membership, in ONE query (no N+1).
     *
     * @param int[] $membershipIds
     * @return array<int, array<int, array<string, mixed>>> membership id => roles
     */
    private function rolesByMembership(array $membershipIds): array
    {
        if ($membershipIds === []) {
            return [];
        }

        $rows = $this->db->table('membership_roles')
            ->select('membership_roles.membership_id', 'roles.id', 'roles.name', 'roles.slug')
            ->join('roles', 'roles.id', '=', 'membership_roles.role_id')
            ->whereIn('membership_roles.membership_id', $membershipIds)
            ->orderBy('roles.priority', 'desc')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['membership_id']][] = [
                'id'   => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
            ];
        }

        return $map;
    }

    /**
     * The tenant roles available for assignment in this workspace, priority-first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function assignableRoles(): array
    {
        return Role::withoutTenantScope()
            ->select('id', 'name', 'slug')
            ->where('workspace_id', '=', $this->workspaceId)
            ->orderBy('priority', 'desc')
            ->get();
    }

    public function find(int $membershipId): ?Membership
    {
        $row = Membership::withoutTenantScope()
            ->where('id', '=', $membershipId)
            ->where('workspace_id', '=', $this->workspaceId)
            ->first();

        return $row ? Membership::hydrate($row) : null;
    }

    /**
     * Invite a member: find or create the user by email, then create (or revive)
     * an active membership in this workspace and assign the initial role. Returns
     * the membership id. Runs in a transaction so a half-created member can never
     * persist.
     */
    public function invite(string $name, string $email, ?int $roleId, ?string $title, int $invitedBy): int
    {
        return $this->db->transaction(function (Database $db) use ($name, $email, $roleId, $title, $invitedBy): int {
            $now = now();
            $userId = $this->resolveUserId($db, $name, $email);

            $existing = $db->table('memberships')
                ->where('workspace_id', '=', $this->workspaceId)
                ->where('user_id', '=', $userId)
                ->first();

            if ($existing !== null) {
                $membershipId = (int) $existing['id'];
                $db->table('memberships')->where('id', '=', $membershipId)->update([
                    'membership_status_id' => lookup_id('membership_status', 'active'),
                    'title'                => $title,
                    'updated_at'           => $now,
                ]);
            } else {
                $membershipId = (int) $db->table('memberships')->insertGetId([
                    'uuid'                 => Model::generateUuid(),
                    'workspace_id'         => $this->workspaceId,
                    'user_id'              => $userId,
                    'membership_status_id' => lookup_id('membership_status', 'active'),
                    'title'                => $title,
                    'invited_by'           => $invitedBy,
                    'invited_at'           => $now,
                    'joined_at'            => $now,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);
            }

            if ($roleId !== null && $this->roleBelongsToWorkspace($db, $roleId)) {
                (new RbacManager($db))->assignMembershipRole($membershipId, $roleId);
            }

            return $membershipId;
        });
    }

    /**
     * Replace the member's tenant roles with the given set (only roles that belong
     * to this workspace are honoured). Mirrors Membership::syncRoles but filtered.
     *
     * @param int[] $roleIds
     */
    public function syncRoles(int $membershipId, array $roleIds): void
    {
        $valid = $this->filterWorkspaceRoleIds($roleIds);

        $this->db->table('membership_roles')->where('membership_id', '=', $membershipId)->delete();
        $rbac = new RbacManager($this->db);
        foreach ($valid as $roleId) {
            $rbac->assignMembershipRole($membershipId, $roleId);
        }
    }

    public function setStatus(int $membershipId, string $statusKey): void
    {
        $this->db->table('memberships')
            ->where('id', '=', $membershipId)
            ->where('workspace_id', '=', $this->workspaceId)
            ->update([
                'membership_status_id' => lookup_id('membership_status', $statusKey),
                'updated_at'           => now(),
            ]);
    }

    /**
     * Recent activity for a member's user within THIS workspace.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activity(int $userId, int $limit = 15): array
    {
        return ActivityLog::withoutTenantScope()
            ->where('workspace_id', '=', $this->workspaceId)
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    private function resolveUserId(Database $db, string $name, string $email): int
    {
        $existing = $db->table('users')->where('email', '=', $email)->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return (int) $db->table('users')->insertGetId([
            'uuid'           => Model::generateUuid(),
            'name'           => $name,
            'email'          => $email,
            // A random password: invited users set their own via the reset flow.
            'password'       => Hash::make(bin2hex(random_bytes(16))),
            'locale'         => (string) (tenant()->workspace()?->getAttribute('locale') ?: 'en'),
            'user_status_id' => lookup_id('user_status', 'active'),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function roleBelongsToWorkspace(Database $db, int $roleId): bool
    {
        return $db->table('roles')
            ->where('id', '=', $roleId)
            ->where('workspace_id', '=', $this->workspaceId)
            ->exists();
    }

    /**
     * @param int[] $roleIds
     * @return int[]
     */
    private function filterWorkspaceRoleIds(array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if ($roleIds === []) {
            return [];
        }

        return array_map('intval', $this->db->table('roles')
            ->where('workspace_id', '=', $this->workspaceId)
            ->whereIn('id', $roleIds)
            ->pluck('id'));
    }

    /**
     * Helper for callers/tests: this user is a User model (for name on invite).
     */
    public function userById(int $userId): ?User
    {
        return User::find($userId);
    }
}
