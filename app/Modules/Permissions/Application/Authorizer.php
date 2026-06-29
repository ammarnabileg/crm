<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Permissions\Application;

use HaHireAI\Core\Contracts\AccessControl;
use HaHireAI\Core\Database\Connection;

/**
 * Computes effective permissions and answers authorization checks. Deny by
 * default; checks reference permission KEYS, never role names
 * (docs/PERMISSION_MODEL.md, docs/ACCESS_POLICIES.md).
 *
 * The shared authorization surface is the AccessControl contract (ARCHITECTURE.md §4).
 */
final class Authorizer implements AccessControl
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Effective workspace permission keys for a membership =
     * (permissions of all assigned roles) ∪ (direct membership grants).
     *
     * @return list<string>
     */
    public function permissionsForMembership(string $membershipId): array
    {
        $rows = $this->connection->select(
            'SELECT p.`key` AS k
               FROM membership_roles mr
               JOIN role_permissions rp ON rp.role_id = mr.role_id
               JOIN permissions p ON p.id = rp.permission_id
              WHERE mr.membership_id = ?
              UNION
             SELECT p.`key` AS k
               FROM membership_permissions mp
               JOIN permissions p ON p.id = mp.permission_id
              WHERE mp.membership_id = ?',
            [$membershipId, $membershipId],
        );

        return array_values(array_unique(array_map(static fn (array $r): string => (string) $r['k'], $rows)));
    }

    public function membershipCan(string $membershipId, string $permissionKey): bool
    {
        return in_array($permissionKey, $this->permissionsForMembership($membershipId), true);
    }

    /**
     * System (platform) permission keys held by a user. A System Owner holds
     * every `system.*` key; this returns them so the Platform Context renders.
     *
     * @return list<string>
     */
    public function systemPermissionsForUser(string $userId): array
    {
        $row = $this->connection->selectOne('SELECT is_system_owner FROM users WHERE id = ?', [$userId]);

        // System Owner = full platform access (every system.* key).
        if ((int) ($row['is_system_owner'] ?? 0) === 1) {
            $rows = $this->connection->select('SELECT `key` FROM permissions WHERE is_system = 1');

            return array_map(static fn (array $r): string => (string) $r['key'], $rows);
        }

        // Otherwise, a "site manager" gets the union of their platform-role
        // permissions — granular platform access without being a full owner.
        $rows = $this->connection->select(
            'SELECT DISTINCT p.`key`
               FROM platform_role_user pru
               JOIN platform_roles pr ON pr.id = pru.role_id AND pr.deleted_at IS NULL
               JOIN platform_role_permissions prp ON prp.role_id = pru.role_id
               JOIN permissions p ON p.id = prp.permission_id
              WHERE pru.user_id = ? AND p.is_system = 1',
            [$userId],
        );

        return array_map(static fn (array $r): string => (string) $r['key'], $rows);
    }

    public function userIsSystemOwner(string $userId): bool
    {
        $row = $this->connection->selectOne('SELECT is_system_owner FROM users WHERE id = ?', [$userId]);

        return (int) ($row['is_system_owner'] ?? 0) === 1;
    }
}
