<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Observability\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;
use Throwable;

/**
 * Records and performs logical backups. By default it writes a verifiable
 * manifest (every table + row count) to storage; a full SQL dump is delegated to
 * `mysqldump` / managed snapshots in production (docs/OBSERVABILITY.md §6).
 * Each run is recorded so restores have an operational source of truth.
 */
final class BackupService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $storageDir,
    ) {
    }

    /**
     * Run a logical backup. `$nowTs` is injectable so filenames/records are
     * deterministic in tests.
     *
     * @return array{id: string, status: string, path: ?string, tables: int}
     */
    public function run(?int $nowTs = null): array
    {
        $nowTs ??= time();
        $id = Ulid::generate();
        $startedAt = gmdate('Y-m-d H:i:s', $nowTs);

        $this->connection->statement(
            'INSERT INTO backups (id, kind, status, started_at, created_at) VALUES (?, ?, ?, ?, ?)',
            [$id, 'logical', 'running', $startedAt, $startedAt],
        );

        try {
            if (! is_dir($this->storageDir)) {
                @mkdir($this->storageDir, 0775, true);
            }

            $manifest = $this->buildManifest();
            $path = rtrim($this->storageDir, '/') . '/backup-' . gmdate('Ymd-His', $nowTs) . '-' . substr($id, -6) . '.json';
            file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->connection->statement(
                "UPDATE backups SET status = 'completed', path = ?, size_bytes = ?, tables_count = ?, finished_at = ? WHERE id = ?",
                [$path, (int) (filesize($path) ?: 0), count($manifest['tables']), gmdate('Y-m-d H:i:s'), $id],
            );

            return ['id' => $id, 'status' => 'completed', 'path' => $path, 'tables' => count($manifest['tables'])];
        } catch (Throwable $e) {
            $this->connection->statement(
                "UPDATE backups SET status = 'failed', error = ?, finished_at = ? WHERE id = ?",
                [mb_substr($e->getMessage(), 0, 1000), gmdate('Y-m-d H:i:s'), $id],
            );

            return ['id' => $id, 'status' => 'failed', 'path' => null, 'tables' => 0];
        }
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 25): array
    {
        $limit = max(1, min(200, $limit));

        return $this->connection->select(
            'SELECT id, kind, status, path, size_bytes, tables_count, started_at, finished_at FROM backups ORDER BY created_at DESC LIMIT ' . $limit,
        );
    }

    /** @return array{generated_at: string, tables: array<string,int>} */
    private function buildManifest(): array
    {
        $tables = [];
        foreach ($this->connection->select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()') as $row) {
            $name = (string) $row['t'];
            $count = $this->connection->selectOne('SELECT COUNT(*) AS c FROM `' . $name . '`');
            $tables[$name] = (int) ($count['c'] ?? 0);
        }

        return ['generated_at' => gmdate('c'), 'tables' => $tables];
    }
}
