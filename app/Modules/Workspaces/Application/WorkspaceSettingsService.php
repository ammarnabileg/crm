<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Application;

use HaHireAI\Core\Database\Connection;

/**
 * Reads/updates a workspace's own settings. Each workspace is independent and
 * never inherits another's settings (docs/WORKSPACE_SETTINGS.md).
 */
final class WorkspaceSettingsService
{
    private const EDITABLE = ['name', 'timezone', 'locale', 'currency'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function get(string $workspaceId): ?array
    {
        return $this->connection->selectOne('SELECT * FROM workspaces WHERE id = ? AND deleted_at IS NULL', [$workspaceId]);
    }

    /**
     * Update editable workspace fields (tenant-guarded by id).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>  the changed fields (for auditing)
     */
    public function update(string $workspaceId, array $attributes): array
    {
        $changes = [];
        foreach (self::EDITABLE as $field) {
            if (isset($attributes[$field]) && trim((string) $attributes[$field]) !== '') {
                $changes[$field] = trim((string) $attributes[$field]);
            }
        }

        if ($changes === []) {
            return [];
        }

        $assignments = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($changes)));
        $bindings = [...array_values($changes), gmdate('Y-m-d H:i:s'), $workspaceId];

        $this->connection->statement(
            "UPDATE workspaces SET {$assignments}, updated_at = ? WHERE id = ?",
            $bindings,
        );

        return $changes;
    }
}
