<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Simple per-workspace key/value preferences (e.g. AI interview mode). A shared
 * service so other modules read workspace preferences without touching the
 * Workspaces tables directly (docs/ARCHITECTURE.md §4).
 */
final class WorkspacePreferences
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function get(string $workspaceId, string $key, ?string $default = null): ?string
    {
        $row = $this->connection->selectOne(
            'SELECT `value` FROM workspace_settings WHERE workspace_id = ? AND `key` = ?',
            [$workspaceId, $key],
        );

        return $row !== null ? (string) $row['value'] : $default;
    }

    public function bool(string $workspaceId, string $key, bool $default = false): bool
    {
        $value = $this->get($workspaceId, $key);

        return $value === null ? $default : in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public function set(string $workspaceId, string $key, string $value): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->connection->selectOne(
            'SELECT id FROM workspace_settings WHERE workspace_id = ? AND `key` = ?',
            [$workspaceId, $key],
        );

        if ($existing !== null) {
            $this->connection->statement(
                'UPDATE workspace_settings SET `value` = ?, updated_at = ? WHERE id = ?',
                [$value, $now, (string) $existing['id']],
            );

            return;
        }

        $this->connection->statement(
            'INSERT INTO workspace_settings (id, workspace_id, `key`, `value`, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $key, $value, $now, $now],
        );
    }
}
