<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform\Application;

use HaHireAI\Core\Database\Connection;

/**
 * Cross-cutting read models for the System Owner (Platform Context): all
 * workspaces/companies, all users, all subscriptions, and the platform audit
 * trail. A platform-level reader by design (like MetricsService) — only the
 * System Owner reaches it (docs/PERMISSION_MODEL.md §6).
 */
final class PlatformAdminService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> every workspace with owner, members, plan */
    public function workspaces(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        return $this->connection->select(
            'SELECT w.id, w.name, w.slug, w.status, w.created_at, u.name AS owner_name,
                    (SELECT COUNT(*) FROM memberships m WHERE m.workspace_id = w.id AND m.deleted_at IS NULL) AS members,
                    (SELECT s.status FROM subscriptions s WHERE s.workspace_id = w.id LIMIT 1) AS sub_status,
                    (SELECT p.name FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.workspace_id = w.id LIMIT 1) AS plan_name
               FROM workspaces w
               LEFT JOIN users u ON u.id = w.owner_user_id
              WHERE w.deleted_at IS NULL
              ORDER BY w.created_at DESC LIMIT ' . $limit,
        );
    }

    /** @return list<array<string, mixed>> every user (one identity; contexts derived) */
    public function users(int $limit = 200, string $q = ''): array
    {
        $limit = max(1, min(500, $limit));
        $where = 'u.deleted_at IS NULL';
        $bindings = [];
        $q = trim($q);
        if ($q !== '') {
            $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bindings, $like, $like);
        }

        return $this->connection->select(
            "SELECT u.id, u.name, u.email, u.is_system_owner, u.status, u.last_login_at, u.created_at,
                    u.can_create_workspaces,
                    (SELECT COUNT(*) FROM memberships m WHERE m.user_id = u.id AND m.deleted_at IS NULL) AS workspaces,
                    (SELECT COUNT(*) FROM workspaces w WHERE w.owner_user_id = u.id AND w.status = 'active' AND w.deleted_at IS NULL) AS owned_active,
                    ap.plan_id, ap.expires_at, ap.status AS plan_status, p.name AS plan_name, p.limits AS plan_limits
               FROM users u
               LEFT JOIN account_plans ap ON ap.user_id = u.id
               LEFT JOIN plans p ON p.id = ap.plan_id
              WHERE " . $where . '
              ORDER BY u.created_at DESC LIMIT ' . $limit,
            $bindings,
        );
    }

    /** System Owner blocks/allows an account from creating workspaces. */
    public function setCanCreateWorkspaces(string $userId, bool $allowed): bool
    {
        $affected = $this->connection->statement(
            'UPDATE users SET can_create_workspaces = ?, updated_at = ? WHERE id = ? AND is_system_owner = 0 AND deleted_at IS NULL',
            [$allowed ? 1 : 0, gmdate('Y-m-d H:i:s'), $userId],
        );

        return $affected > 0;
    }

    /** @return array<string, mixed>|null */
    public function findUser(string $userId): ?array
    {
        return $this->connection->selectOne('SELECT id, name, email, status, is_system_owner FROM users WHERE id = ? AND deleted_at IS NULL', [$userId]);
    }

    /**
     * Set a platform user's account status (active|suspended|deactivated). System
     * Owners are protected and never changed. Returns true if a row was updated.
     */
    public function setUserStatus(string $userId, string $status): bool
    {
        $affected = $this->connection->statement(
            'UPDATE users SET status = ?, updated_at = ? WHERE id = ? AND is_system_owner = 0 AND deleted_at IS NULL',
            [$status, gmdate('Y-m-d H:i:s'), $userId],
        );

        return $affected > 0;
    }

    /** @return list<array<string, mixed>> every subscription with workspace + plan */
    public function subscriptions(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        return $this->connection->select(
            'SELECT s.status, s.trial_ends_at, s.current_period_end, s.updated_at,
                    w.name AS workspace, p.name AS plan, p.price_cents
               FROM subscriptions s
               JOIN workspaces w ON w.id = s.workspace_id
               JOIN plans p ON p.id = s.plan_id
              ORDER BY s.updated_at DESC LIMIT ' . $limit,
        );
    }

    /** @return list<array<string, mixed>> the platform-wide audit trail */
    public function audit(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return $this->connection->select(
            'SELECT a.action, a.entity_type, a.created_at, w.name AS workspace, u.name AS actor
               FROM audit_logs a
               LEFT JOIN workspaces w ON w.id = a.workspace_id
               LEFT JOIN users u ON u.id = a.actor_user_id
              ORDER BY a.created_at DESC LIMIT ' . $limit,
        );
    }
}
