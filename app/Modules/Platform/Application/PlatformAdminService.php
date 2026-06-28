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
    public function users(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        return $this->connection->select(
            'SELECT u.id, u.name, u.email, u.is_system_owner, u.created_at,
                    (SELECT COUNT(*) FROM memberships m WHERE m.user_id = u.id AND m.deleted_at IS NULL) AS workspaces
               FROM users u
              WHERE u.deleted_at IS NULL
              ORDER BY u.created_at DESC LIMIT ' . $limit,
        );
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
