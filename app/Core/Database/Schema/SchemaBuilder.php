<?php

declare(strict_types=1);

namespace HaHireAI\Core\Database\Schema;

use HaHireAI\Core\Database\Connection;

/**
 * Runs schema operations against a connection. Used by migrations. No ORM.
 * See docs/DATABASE_ARCHITECTURE.md.
 */
final class SchemaBuilder
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** Define and create a table. */
    public function create(string $table, callable $definition): void
    {
        $blueprint = new Blueprint($table);
        $definition($blueprint);
        $this->connection->unprepared($blueprint->toCreateSql());
    }

    public function createIfNotExists(string $table, callable $definition): void
    {
        if (! $this->hasTable($table)) {
            $this->create($table, $definition);
        }
    }

    public function drop(string $table): void
    {
        $this->connection->unprepared("DROP TABLE `{$table}`");
    }

    public function dropIfExists(string $table): void
    {
        $this->connection->unprepared("DROP TABLE IF EXISTS `{$table}`");
    }

    public function hasTable(string $table): bool
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table],
        );

        return (int) ($row['c'] ?? 0) > 0;
    }

    public function hasColumn(string $table, string $column): bool
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column],
        );

        return (int) ($row['c'] ?? 0) > 0;
    }

    /** Escape hatch for ALTER statements not covered by the builder. */
    public function raw(string $sql): void
    {
        $this->connection->unprepared($sql);
    }

    public function connection(): Connection
    {
        return $this->connection;
    }
}
