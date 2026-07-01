<?php

declare(strict_types=1);

namespace HaHireAI\Core\Database\Migrations;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * A bespoke, framework-free migration engine: create / rollback / history /
 * version tracking. Must run cleanly from zero. See docs/DATABASE_ARCHITECTURE.md.
 *
 * Note: MySQL DDL is auto-committing, so migrations are designed to be
 * individually safe and idempotent where practical.
 */
final class MigrationRunner
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaBuilder $schema,
    ) {
    }

    public function ensureMigrationsTable(): void
    {
        $this->schema->createIfNotExists('migrations', static function ($table): void {
            $table->string('migration')->nullable(false);
            $table->integer('batch')->nullable(false);
            $table->datetime('ran_at')->nullable();
            $table->unique('migration');
        });
    }

    /**
     * Run all pending migrations in a directory.
     *
     * @param  callable(string):void|null  $log  optional per-step logger
     * @return list<string>  names of migrations that were run
     */
    public function run(string $directory, ?callable $log = null): array
    {
        $this->ensureMigrationsTable();

        $ran = $this->ranMigrations();
        $batch = $this->nextBatch();
        $applied = [];

        foreach ($this->discover($directory) as $name => $file) {
            if (in_array($name, $ran, true)) {
                continue;
            }

            $log && $log("Migrating: {$name}");
            $migration = require $file;
            $migration->up($this->schema);
            $this->record($name, $batch);
            $applied[] = $name;
            $log && $log("Migrated:  {$name}");
        }

        return $applied;
    }

    /**
     * Roll back the most recent batch.
     *
     * @return list<string>  names rolled back
     */
    public function rollback(string $directory): array
    {
        $this->ensureMigrationsTable();

        $batch = $this->lastBatch();

        if ($batch === 0) {
            return [];
        }

        $files = $this->discover($directory);
        $rolledBack = [];

        $rows = $this->connection->select(
            'SELECT migration FROM migrations WHERE batch = ? ORDER BY migration DESC',
            [$batch],
        );

        foreach ($rows as $row) {
            $name = (string) $row['migration'];

            if (isset($files[$name])) {
                $migration = require $files[$name];
                $migration->down($this->schema);
            }

            $this->connection->statement('DELETE FROM migrations WHERE migration = ?', [$name]);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /** @return list<string> */
    public function ranMigrations(): array
    {
        $this->ensureMigrationsTable();

        return array_map(
            static fn (array $row): string => (string) $row['migration'],
            $this->connection->select('SELECT migration FROM migrations ORDER BY migration ASC'),
        );
    }

    /** @return array<string, string>  name => absolute path, sorted by name */
    private function discover(string $directory): array
    {
        $files = glob(rtrim($directory, '/') . '/*.php') ?: [];
        $map = [];

        foreach ($files as $file) {
            $map[basename($file, '.php')] = $file;
        }

        ksort($map);

        return $map;
    }

    private function record(string $name, int $batch): void
    {
        $this->connection->statement(
            'INSERT INTO migrations (migration, batch, ran_at) VALUES (?, ?, ?)',
            [$name, $batch, gmdate('Y-m-d H:i:s')],
        );
    }

    private function nextBatch(): int
    {
        return $this->lastBatch() + 1;
    }

    private function lastBatch(): int
    {
        $row = $this->connection->selectOne('SELECT MAX(batch) AS b FROM migrations');

        return (int) ($row['b'] ?? 0);
    }
}
