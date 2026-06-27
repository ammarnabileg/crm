<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Core\Database;
use App\Models\ActivityLog;

/**
 * Cross-tenant read/write service for the Super-Admin Platform Console.
 *
 * Every query here is INTENTIONALLY not tenant-scoped: a super admin operates
 * above the tenant boundary (docs/47 RBAC bible), so this service speaks to the
 * raw connection (app('db')) and joins workspaces/users/subscriptions directly.
 * It is the single place where the platform owner sees and mutates every
 * workspace and every user, so the rules — and the audit trail — live in one
 * spot.
 *
 * AUTHORIZATION IS THE CALLER'S JOB. This service performs no permission check;
 * PlatformController gates every entry point with abort_unless(can('system.manage'))
 * (the super-admin gate). Keeping the gate in the controller mirrors the other
 * System/* controllers and keeps this class a pure, testable data layer.
 *
 * Status resolution: workspace lifecycle lives in the `workspace_statuses`
 * status table (status_id('workspace_statuses', 'active'|'suspended'|...)) while
 * user lifecycle lives in the `user_status` lookup category
 * (lookup_id('user_status', 'active'|'suspended'|...)) — that asymmetry is the
 * real schema, so both resolvers are used as the data dictates.
 */
final class PlatformDirectory
{
    /**
     * Hard ceiling on list queries. The console is a control panel, not an
     * export tool: a generous cap keeps a single page bounded and the query
     * cheap even on a large platform. Filters narrow the set further.
     */
    public const LIST_CAP = 200;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? app('db');
    }

    /**
     * Every workspace on the platform (newest first, capped), each enriched with
     * its owner, member count, lifecycle status and current plan/subscription.
     *
     * @param array{keyword?:string} $filters Optional `keyword` matches name OR slug.
     * @return array<int, array<string, mixed>>
     */
    public function workspaces(array $filters = []): array
    {
        $activeMembership = (int) lookup_id('membership_status', 'active');

        $query = $this->db->table('workspaces')
            ->select(
                'workspaces.id',
                'workspaces.name',
                'workspaces.slug',
                'workspaces.created_at',
                'workspaces.owner_id',
                'owner.name AS owner_name',
                'owner.email AS owner_email',
                'workspace_statuses.key AS status_key',
            )
            ->leftJoin('users AS owner', 'owner.id', '=', 'workspaces.owner_id')
            ->leftJoin('workspace_statuses', 'workspace_statuses.id', '=', 'workspaces.workspace_status_id')
            ->whereNull('workspaces.deleted_at');

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->whereRaw('(workspaces.name LIKE ? OR workspaces.slug LIKE ?)', [$like, $like]);
        }

        $rows = $query->orderBy('workspaces.created_at', 'desc')
            ->orderBy('workspaces.id', 'desc')
            ->limit(self::LIST_CAP)
            ->get();

        if ($rows === []) {
            return [];
        }

        $workspaceIds = array_map(static fn (array $r): int => (int) $r['id'], $rows);

        $memberCounts = $this->memberCountsByWorkspace($workspaceIds, $activeMembership);
        $subscriptions = $this->latestSubscriptionByWorkspace($workspaceIds);

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $sub = $subscriptions[$id] ?? null;
            $out[] = [
                'id'                  => $id,
                'name'                => (string) $row['name'],
                'slug'                => (string) $row['slug'],
                'owner_name'          => (string) ($row['owner_name'] ?? ''),
                'owner_email'         => (string) ($row['owner_email'] ?? ''),
                'member_count'        => (int) ($memberCounts[$id] ?? 0),
                'status_key'          => (string) ($row['status_key'] ?? ''),
                'plan_name'           => $sub['plan_name'] ?? null,
                'subscription_status' => $sub['status_key'] ?? null,
                'created_at'          => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * One workspace with the detail the console needs: its members (user +
     * membership status + role names), current subscription, AI-key count, file
     * count and recent activity. Returns null when the id is unknown/soft-deleted.
     *
     * @return array<string, mixed>|null
     */
    public function workspace(int $id): ?array
    {
        $row = $this->db->table('workspaces')
            ->select(
                'workspaces.*',
                'owner.name AS owner_name',
                'owner.email AS owner_email',
                'workspace_statuses.key AS status_key',
            )
            ->leftJoin('users AS owner', 'owner.id', '=', 'workspaces.owner_id')
            ->leftJoin('workspace_statuses', 'workspace_statuses.id', '=', 'workspaces.workspace_status_id')
            ->where('workspaces.id', '=', $id)
            ->whereNull('workspaces.deleted_at')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id'              => (int) $row['id'],
            'name'            => (string) $row['name'],
            'slug'            => (string) $row['slug'],
            'owner_name'      => (string) ($row['owner_name'] ?? ''),
            'owner_email'     => (string) ($row['owner_email'] ?? ''),
            'status_key'      => (string) ($row['status_key'] ?? ''),
            'created_at'      => (string) ($row['created_at'] ?? ''),
            'members'         => $this->workspaceMembers($id),
            'subscription'    => $this->latestSubscriptionByWorkspace([$id])[$id] ?? null,
            'ai_key_count'    => $this->aiKeyCount($id),
            'file_count'      => $this->fileCount($id),
            'recent_activity' => $this->recentActivity($id),
        ];
    }

    /**
     * Every user on the platform (newest first, capped), each with status,
     * workspace count and last-login.
     *
     * @param array{keyword?:string} $filters Optional `keyword` matches name OR email.
     * @return array<int, array<string, mixed>>
     */
    public function users(array $filters = []): array
    {
        $query = $this->db->table('users')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.last_login_at',
                'users.created_at',
                'lv.key AS status_key',
            )
            ->leftJoin('lookup_values AS lv', 'lv.id', '=', 'users.user_status_id')
            ->whereNull('users.deleted_at');

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->whereRaw('(users.name LIKE ? OR users.email LIKE ?)', [$like, $like]);
        }

        $rows = $query->orderBy('users.created_at', 'desc')
            ->orderBy('users.id', 'desc')
            ->limit(self::LIST_CAP)
            ->get();

        if ($rows === []) {
            return [];
        }

        $userIds = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $workspaceCounts = $this->workspaceCountsByUser($userIds);

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $out[] = [
                'id'               => $id,
                'name'             => (string) $row['name'],
                'email'            => (string) $row['email'],
                'status_key'       => (string) ($row['status_key'] ?? ''),
                'workspaces_count' => (int) ($workspaceCounts[$id] ?? 0),
                'last_login_at'    => $row['last_login_at'] !== null ? (string) $row['last_login_at'] : null,
                'created_at'       => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Flip a workspace's lifecycle status (e.g. active <-> suspended) and write
     * an audit row. Throws if the status key is unknown so a bad request never
     * silently writes a null status_id.
     */
    public function setWorkspaceStatus(int $id, string $statusKey): void
    {
        $statusId = status_id('workspace_statuses', $statusKey);
        if ($statusId === null) {
            throw new \InvalidArgumentException("Unknown workspace status '{$statusKey}'.");
        }

        $workspace = $this->db->table('workspaces')
            ->where('id', '=', $id)
            ->whereNull('deleted_at')
            ->first();
        if ($workspace === null) {
            throw new \RuntimeException("Workspace #{$id} not found.");
        }

        $this->db->table('workspaces')->where('id', '=', $id)->update([
            'workspace_status_id' => $statusId,
            'updated_at'          => now(),
        ]);

        ActivityLog::record(
            action: 'platform.workspace.status_changed',
            workspaceId: $id,
            userId: auth()->id(),
            description: "Workspace status set to '{$statusKey}'.",
            properties: ['status' => $statusKey, 'workspace_id' => $id],
            subjectType: 'Workspace',
            subjectId: $id,
        );
    }

    /**
     * Flip a user's lifecycle status (e.g. active <-> suspended) and write an
     * audit row. User status lives in the `user_status` lookup category.
     */
    public function setUserStatus(int $id, string $statusKey): void
    {
        $statusId = lookup_id('user_status', $statusKey);
        if ($statusId === null) {
            throw new \InvalidArgumentException("Unknown user status '{$statusKey}'.");
        }

        $user = $this->db->table('users')
            ->where('id', '=', $id)
            ->whereNull('deleted_at')
            ->first();
        if ($user === null) {
            throw new \RuntimeException("User #{$id} not found.");
        }

        $this->db->table('users')->where('id', '=', $id)->update([
            'user_status_id' => $statusId,
            'updated_at'     => now(),
        ]);

        ActivityLog::record(
            action: 'platform.user.status_changed',
            workspaceId: null,
            userId: auth()->id(),
            description: "User #{$id} status set to '{$statusKey}'.",
            properties: ['status' => $statusKey, 'user_id' => $id],
            subjectType: 'User',
            subjectId: $id,
        );
    }

    /**
     * Headline counters for the console header.
     *
     * @return array{workspaces:int,active_workspaces:int,suspended_workspaces:int,users:int,active_users:int,active_subscriptions:int}
     */
    public function stats(): array
    {
        $activeWorkspace = (int) status_id('workspace_statuses', 'active');
        $trialWorkspace = (int) status_id('workspace_statuses', 'trial');
        $suspendedWorkspace = (int) status_id('workspace_statuses', 'suspended');
        $activeUser = (int) lookup_id('user_status', 'active');

        $subActive = array_values(array_filter([
            status_id('subscription_statuses', 'active'),
            status_id('subscription_statuses', 'trialing'),
        ], static fn ($v): bool => $v !== null));

        $activeSubscriptions = 0;
        if ($subActive !== []) {
            $activeSubscriptions = $this->db->table('subscriptions')
                ->whereNull('deleted_at')
                ->whereIn('subscription_status_id', $subActive)
                ->count();
        }

        return [
            'workspaces' => $this->db->table('workspaces')->whereNull('deleted_at')->count(),
            'active_workspaces' => $this->db->table('workspaces')->whereNull('deleted_at')
                ->whereIn('workspace_status_id', array_values(array_filter([$activeWorkspace, $trialWorkspace])))
                ->count(),
            'suspended_workspaces' => $suspendedWorkspace > 0
                ? $this->db->table('workspaces')->whereNull('deleted_at')->where('workspace_status_id', '=', $suspendedWorkspace)->count()
                : 0,
            'users' => $this->db->table('users')->whereNull('deleted_at')->count(),
            'active_users' => $activeUser > 0
                ? $this->db->table('users')->whereNull('deleted_at')->where('user_status_id', '=', $activeUser)->count()
                : 0,
            'active_subscriptions' => $activeSubscriptions,
        ];
    }

    // ---- internal helpers -------------------------------------------------

    /**
     * @param int[] $workspaceIds
     * @return array<int, int> workspace id => active member count
     */
    private function memberCountsByWorkspace(array $workspaceIds, int $activeMembership): array
    {
        $rows = $this->db->table('memberships')
            ->select('workspace_id')
            ->whereIn('workspace_id', $workspaceIds)
            ->where('membership_status_id', '=', $activeMembership)
            ->whereNull('deleted_at')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $wid = (int) $row['workspace_id'];
            $counts[$wid] = ($counts[$wid] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param int[] $userIds
     * @return array<int, int> user id => active workspace (membership) count
     */
    private function workspaceCountsByUser(array $userIds): array
    {
        $activeMembership = (int) lookup_id('membership_status', 'active');

        $rows = $this->db->table('memberships')
            ->select('user_id')
            ->whereIn('user_id', $userIds)
            ->where('membership_status_id', '=', $activeMembership)
            ->whereNull('deleted_at')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $uid = (int) $row['user_id'];
            $counts[$uid] = ($counts[$uid] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Latest subscription per workspace with its plan name + status key.
     *
     * @param int[] $workspaceIds
     * @return array<int, array{plan_name:?string,status_key:?string}>
     */
    private function latestSubscriptionByWorkspace(array $workspaceIds): array
    {
        $rows = $this->db->table('subscriptions')
            ->select(
                'subscriptions.id',
                'subscriptions.workspace_id',
                'subscriptions.created_at',
                'plans.name AS plan_name',
                'subscription_statuses.key AS status_key',
            )
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->leftJoin('subscription_statuses', 'subscription_statuses.id', '=', 'subscriptions.subscription_status_id')
            ->whereIn('subscriptions.workspace_id', $workspaceIds)
            ->whereNull('subscriptions.deleted_at')
            ->orderBy('subscriptions.workspace_id', 'asc')
            ->orderBy('subscriptions.created_at', 'desc')
            ->orderBy('subscriptions.id', 'desc')
            ->get();

        // First row seen per workspace wins (rows are ordered newest-first within
        // each workspace), so we keep the latest subscription only.
        $out = [];
        foreach ($rows as $row) {
            $wid = (int) $row['workspace_id'];
            if (isset($out[$wid])) {
                continue;
            }
            $out[$wid] = [
                'plan_name'  => $row['plan_name'] !== null ? (string) $row['plan_name'] : null,
                'status_key' => $row['status_key'] !== null ? (string) $row['status_key'] : null,
            ];
        }

        return $out;
    }

    /**
     * Members of a workspace: user identity + membership status + role names.
     *
     * @return array<int, array<string, mixed>>
     */
    private function workspaceMembers(int $workspaceId): array
    {
        $rows = $this->db->table('memberships')
            ->select(
                'memberships.id',
                'memberships.user_id',
                'memberships.title',
                'users.name AS user_name',
                'users.email AS user_email',
                'membership_status.key AS status_key',
            )
            ->leftJoin('users', 'users.id', '=', 'memberships.user_id')
            ->leftJoin('lookup_values AS membership_status', 'membership_status.id', '=', 'memberships.membership_status_id')
            ->where('memberships.workspace_id', '=', $workspaceId)
            ->whereNull('memberships.deleted_at')
            ->orderBy('memberships.created_at', 'asc')
            ->limit(self::LIST_CAP)
            ->get();

        if ($rows === []) {
            return [];
        }

        $membershipIds = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $rolesByMembership = $this->roleNamesByMembership($membershipIds);

        $out = [];
        foreach ($rows as $row) {
            $mid = (int) $row['id'];
            $out[] = [
                'membership_id' => $mid,
                'user_id'       => (int) $row['user_id'],
                'user_name'     => (string) ($row['user_name'] ?? ''),
                'user_email'    => (string) ($row['user_email'] ?? ''),
                'title'         => (string) ($row['title'] ?? ''),
                'status_key'    => (string) ($row['status_key'] ?? ''),
                'roles'         => $rolesByMembership[$mid] ?? [],
            ];
        }

        return $out;
    }

    /**
     * @param int[] $membershipIds
     * @return array<int, string[]> membership id => role names
     */
    private function roleNamesByMembership(array $membershipIds): array
    {
        $rows = $this->db->table('membership_roles')
            ->select('membership_roles.membership_id', 'roles.name AS role_name')
            ->leftJoin('roles', 'roles.id', '=', 'membership_roles.role_id')
            ->whereIn('membership_roles.membership_id', $membershipIds)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $mid = (int) $row['membership_id'];
            $name = (string) ($row['role_name'] ?? '');
            if ($name !== '') {
                $out[$mid][] = $name;
            }
        }

        return $out;
    }

    private function aiKeyCount(int $workspaceId): int
    {
        return $this->db->table('tenant_ai_keys')
            ->where('workspace_id', '=', $workspaceId)
            ->whereNull('deleted_at')
            ->count();
    }

    private function fileCount(int $workspaceId): int
    {
        return $this->db->table('files')
            ->where('workspace_id', '=', $workspaceId)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Most recent audit entries for a workspace.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(int $workspaceId, int $limit = 15): array
    {
        $rows = $this->db->table('activity_logs')
            ->select(
                'activity_logs.action',
                'activity_logs.description',
                'activity_logs.created_at',
                'users.name AS actor_name',
            )
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->where('activity_logs.workspace_id', '=', $workspaceId)
            ->orderBy('activity_logs.created_at', 'desc')
            ->orderBy('activity_logs.id', 'desc')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'action'      => (string) ($row['action'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'actor_name'  => (string) ($row['actor_name'] ?? ''),
                'created_at'  => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $out;
    }
}
