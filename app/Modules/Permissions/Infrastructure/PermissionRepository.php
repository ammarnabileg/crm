<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Permissions\Infrastructure;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/** Reads/writes the global permission catalog (`permissions` table). */
final class PermissionRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Idempotently upsert the catalog.
     *
     * @param  list<array{key: string, category: string, system: bool, description: string}>  $catalog
     */
    public function seed(array $catalog): int
    {
        $existing = $this->keyToId();
        $now = gmdate('Y-m-d H:i:s');
        $count = 0;

        foreach ($catalog as $permission) {
            if (isset($existing[$permission['key']])) {
                continue;
            }

            $this->connection->statement(
                'INSERT INTO permissions (id, `key`, category, description, is_system, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $permission['key'], $permission['category'], $permission['description'], (int) $permission['system'], $now, $now],
            );
            $count++;
        }

        return $count;
    }

    /** @return array<string, string> key => id */
    public function keyToId(): array
    {
        $map = [];

        foreach ($this->connection->select('SELECT id, `key` FROM permissions') as $row) {
            $map[(string) $row['key']] = (string) $row['id'];
        }

        return $map;
    }

    /**
     * Resolve permission keys to ids (skips unknown keys).
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function idsForKeys(array $keys): array
    {
        $map = $this->keyToId();

        return array_values(array_filter(array_map(static fn (string $k): ?string => $map[$k] ?? null, $keys)));
    }

    public function count(): int
    {
        $row = $this->connection->selectOne('SELECT COUNT(*) AS c FROM permissions');

        return (int) ($row['c'] ?? 0);
    }
}
