<?php

declare(strict_types=1);

namespace Database;

use App\Core\Database;

/**
 * Migration runner used by both the web installer and any future maintenance
 * screen. Discovers migration files (database/migrations/NNNN_*.php), tracks
 * applied ones in a `migrations` table, and runs pending migrations in order.
 *
 * Each run is reported step-by-step so the installer's live console can show
 * progress and surface failures precisely.
 */
final class Migrator
{
    public function __construct(
        private readonly Database $db,
        private readonly string $migrationsPath,
    ) {
    }

    public function ensureMigrationsTable(): void
    {
        $this->db->unprepared(
            'CREATE TABLE IF NOT EXISTS `migrations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(255) NOT NULL,
                `batch` INT NOT NULL,
                `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `migrations_migration_unique` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return string[] List of migration names that still need to run.
     */
    public function pending(): array
    {
        $this->ensureMigrationsTable();
        $applied = $this->applied();

        $pending = [];
        foreach ($this->files() as $name => $file) {
            if (! in_array($name, $applied, true)) {
                $pending[$name] = $file;
            }
        }

        return $pending;
    }

    /**
     * Run all pending migrations.
     *
     * @param callable(string,bool,?string):void|null $report Per-migration callback (name, ok, error).
     * @return array{ran:string[],failed:?string}
     */
    public function run(?callable $report = null): array
    {
        $pending = $this->pending();
        $batch = $this->nextBatch();
        $ran = [];

        foreach ($pending as $name => $file) {
            try {
                // NOTE: MySQL implicitly commits on DDL (CREATE/ALTER/DROP), so
                // migrations cannot run inside an explicit transaction. We run
                // the migration then record it; a failure aborts the remaining
                // migrations and is reported precisely to the installer console.
                $migration = require $file;
                $migration->up($this->db);
                $this->db->table('migrations')->insert([
                    'migration'   => $name,
                    'batch'       => $batch,
                    'executed_at' => date('Y-m-d H:i:s'),
                ]);
                $ran[] = $name;
                $report && $report($name, true, null);
            } catch (\Throwable $e) {
                $report && $report($name, false, $e->getMessage());

                return ['ran' => $ran, 'failed' => $name . ': ' . $e->getMessage()];
            }
        }

        return ['ran' => $ran, 'failed' => null];
    }

    /**
     * Roll back the most recent batch.
     */
    public function rollback(): array
    {
        $this->ensureMigrationsTable();
        $batch = (int) $this->db->scalar('SELECT MAX(batch) FROM `migrations`');
        if ($batch === 0) {
            return [];
        }

        $names = $this->db->table('migrations')->where('batch', '=', $batch)->orderBy('id', 'desc')->pluck('migration');
        $files = $this->files();
        $rolled = [];

        foreach ($names as $name) {
            if (! isset($files[$name])) {
                continue;
            }
            $migration = require $files[$name];
            $migration->down($this->db);
            $this->db->table('migrations')->where('migration', '=', $name)->delete();
            $rolled[] = $name;
        }

        return $rolled;
    }

    /**
     * @return array<string, string> name => absolute file path, ordered.
     */
    public function files(): array
    {
        $files = glob($this->migrationsPath . '/*.php') ?: [];
        sort($files);

        $result = [];
        foreach ($files as $file) {
            $result[basename($file, '.php')] = $file;
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function applied(): array
    {
        return $this->db->table('migrations')->orderBy('id')->pluck('migration');
    }

    private function nextBatch(): int
    {
        return ((int) $this->db->scalar('SELECT MAX(batch) FROM `migrations`')) + 1;
    }
}
