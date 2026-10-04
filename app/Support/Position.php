<?php

declare(strict_types=1);

namespace HaHireAI\Support;

use HaHireAI\Core\Database\Connection;

/**
 * The next append position for an ordered list — `COALESCE(MAX(position), -1) + 1`
 * scoped by a set of equality columns. Previously copied across six services
 * (job questions/criteria, interview messages, learning todos/paths, quiz &
 * program trees). Uses the null-safe `<=>` operator so nullable scope columns
 * (e.g. a todo's optional item_id) match correctly.
 *
 * Table and column names are always internal constants, never user input.
 */
final class Position
{
    /**
     * @param  array<string, scalar|null>  $scope  column => value equality filters
     */
    public static function next(Connection $connection, string $table, array $scope): int
    {
        $where = [];
        $bindings = [];
        foreach ($scope as $column => $value) {
            $where[] = "{$column} <=> ?";
            $bindings[] = $value;
        }
        $clause = $where === [] ? '1=1' : implode(' AND ', $where);

        $row = $connection->selectOne(
            "SELECT COALESCE(MAX(position), -1) + 1 AS pos FROM {$table} WHERE {$clause}",
            $bindings,
        );

        return (int) ($row['pos'] ?? 0);
    }
}
