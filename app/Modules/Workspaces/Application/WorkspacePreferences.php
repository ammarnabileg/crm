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

        if ($row === null || $row['value'] === null) {
            return $default;
        }

        $raw = (string) $row['value'];
        // `value` is a JSON column; decode scalars back to strings. Tolerate any
        // legacy raw (non-JSON) value by returning it verbatim.
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return is_scalar($decoded) ? (string) $decoded : $default;
        }

        return $raw;
    }

    public function bool(string $workspaceId, string $key, bool $default = false): bool
    {
        $value = $this->get($workspaceId, $key);

        return $value === null ? $default : in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public function set(string $workspaceId, string $key, string $value): void
    {
        $now = gmdate('Y-m-d H:i:s');
        // `value` is a JSON column — store a valid JSON scalar so any string
        // (URLs, hex colours, ULIDs, free text) round-trips safely.
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $existing = $this->connection->selectOne(
            'SELECT id FROM workspace_settings WHERE workspace_id = ? AND `key` = ?',
            [$workspaceId, $key],
        );

        if ($existing !== null) {
            $this->connection->statement(
                'UPDATE workspace_settings SET `value` = ?, updated_at = ? WHERE id = ?',
                [$encoded, $now, (string) $existing['id']],
            );

            return;
        }

        $this->connection->statement(
            'INSERT INTO workspace_settings (id, workspace_id, `key`, `value`, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $key, $encoded, $now, $now],
        );
    }
}
