<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Fluent, parameterised SQL query builder for MySQL.
 *
 * Every value is bound, never interpolated, and identifiers are quoted with
 * backticks, so user input cannot break out of its slot. This is the single
 * choke point for application reads/writes and the layer the tenant scope is
 * applied through.
 */
final class QueryBuilder
{
    private array $columns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private array $joins = [];
    private array $orders = [];
    private array $groups = [];
    private array $havings = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private bool $distinct = false;

    private const OPERATORS = ['=', '!=', '<>', '>', '<', '>=', '<=', 'like', 'not like', 'in', 'not in'];

    public function __construct(
        private readonly Database $db,
        private readonly string $table,
    ) {
    }

    public function select(string ...$columns): self
    {
        $this->columns = $columns === [] ? ['*'] : $columns;

        return $this;
    }

    public function distinct(): self
    {
        $this->distinct = true;

        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        // where('col', 'value') shorthand
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $operator = strtolower((string) $operator);
        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Unsupported operator [{$operator}].");
        }

        $placeholder = $this->addBinding($value);
        $this->wheres[] = [
            'type'     => 'basic',
            'sql'      => $this->wrap($column) . ' ' . strtoupper($operator) . ' ' . $placeholder,
            'boolean'  => $boolean,
        ];

        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            return $this->where($column, '=', $operator, 'OR');
        }

        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        if ($values === []) {
            // IN () is invalid SQL; an empty set matches nothing (or everything for NOT IN).
            $this->wheres[] = ['type' => 'raw', 'sql' => $not ? '1 = 1' : '1 = 0', 'boolean' => $boolean];

            return $this;
        }

        $placeholders = [];
        foreach ($values as $value) {
            $placeholders[] = $this->addBinding($value);
        }

        $this->wheres[] = [
            'type'    => 'in',
            'sql'     => $this->wrap($column) . ($not ? ' NOT IN ' : ' IN ') . '(' . implode(', ', $placeholders) . ')',
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): self
    {
        $this->wheres[] = [
            'type'    => 'null',
            'sql'     => $this->wrap($column) . ($not ? ' IS NOT NULL' : ' IS NULL'),
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Raw WHERE fragment. Bindings MUST be passed separately — never
     * interpolate user input into $sql.
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        foreach ($bindings as $binding) {
            $this->bindings[] = $binding;
        }

        $this->wheres[] = ['type' => 'raw', 'sql' => $sql, 'boolean' => $boolean];

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = sprintf(
            '%s JOIN %s ON %s %s %s',
            strtoupper($type),
            $this->wrapTable($table),
            $this->wrap($first),
            $operator,
            $this->wrap($second)
        );

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $this->orders[] = $this->wrap($column) . ' ' . $direction;

        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'asc');
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groups[] = $this->wrap($column);
        }

        return $this;
    }

    public function having(string $column, string $operator, mixed $value): self
    {
        $placeholder = $this->addBinding($value);
        $this->havings[] = $this->wrap($column) . ' ' . $operator . ' ' . $placeholder;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    // --- Terminal operations ----------------------------------------------

    public function get(): array
    {
        return $this->db->select($this->toSql(), $this->bindings);
    }

    public function first(): ?array
    {
        $this->limit(1);

        return $this->db->selectOne($this->toSql(), $this->bindings);
    }

    public function find(int|string $id, string $column = 'id'): ?array
    {
        return $this->where($column, '=', $id)->first();
    }

    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();

        return $row[$this->unqualify($column)] ?? null;
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $columns = $key === null ? [$column] : [$column, $key];
        $this->select(...$columns);
        $rows = $this->get();

        $result = [];
        $col = $this->unqualify($column);
        $keyCol = $key !== null ? $this->unqualify($key) : null;

        foreach ($rows as $row) {
            if ($keyCol !== null) {
                $result[$row[$keyCol]] = $row[$col];
            } else {
                $result[] = $row[$col];
            }
        }

        return $result;
    }

    public function count(string $column = '*'): int
    {
        return (int) $this->aggregate('COUNT', $column);
    }

    public function sum(string $column): float
    {
        return (float) $this->aggregate('SUM', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    private function aggregate(string $function, string $column): mixed
    {
        $expression = $function . '(' . ($column === '*' ? '*' : $this->wrap($column)) . ')';
        $original = $this->columns;
        $this->columns = [$expression . ' AS aggregate'];
        $row = $this->db->selectOne($this->toSql(), $this->bindings);
        $this->columns = $original;

        return $row['aggregate'] ?? 0;
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $page = max(1, $page);
        $total = (clone $this)->count();

        $results = $this->limit($perPage)->offset(($page - 1) * $perPage)->get();
        $lastPage = (int) max(1, (int) ceil($total / max(1, $perPage)));

        return [
            'data'         => $results,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
            'from'         => $total === 0 ? 0 : ($page - 1) * $perPage + 1,
            'to'           => min($page * $perPage, $total),
            'has_more'     => $page < $lastPage,
        ];
    }

    public function insert(array $values): bool
    {
        if ($values === []) {
            return false;
        }

        $columns = array_keys($values);
        $placeholders = [];
        $bindings = [];
        foreach ($values as $value) {
            $placeholders[] = '?';
            $bindings[] = $value;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->wrapTable($this->table),
            implode(', ', array_map([$this, 'wrap'], $columns)),
            implode(', ', $placeholders)
        );

        return $this->db->statement($sql, $bindings);
    }

    public function insertGetId(array $values): int
    {
        $columns = array_keys($values);
        $bindings = array_values($values);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->wrapTable($this->table),
            implode(', ', array_map([$this, 'wrap'], $columns)),
            $placeholders
        );

        return (int) $this->db->insert($sql, $bindings);
    }

    public function update(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $sets = [];
        $bindings = [];
        foreach ($values as $column => $value) {
            $sets[] = $this->wrap($column) . ' = ?';
            $bindings[] = $value;
        }

        $sql = 'UPDATE ' . $this->wrapTable($this->table) . ' SET ' . implode(', ', $sets);
        [$whereSql, $whereBindings] = $this->compileWheres();
        $sql .= $whereSql;

        return $this->db->affectingStatement($sql, array_merge($bindings, $whereBindings));
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->wrapTable($this->table);
        [$whereSql, $whereBindings] = $this->compileWheres();

        return $this->db->affectingStatement($sql . $whereSql, $whereBindings);
    }

    // --- SQL compilation ---------------------------------------------------

    public function toSql(): string
    {
        $sql = 'SELECT ' . ($this->distinct ? 'DISTINCT ' : '')
            . implode(', ', array_map([$this, 'wrapColumn'], $this->columns))
            . ' FROM ' . $this->wrapTable($this->table);

        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        [$whereSql, $whereBindings] = $this->compileWheres();
        $sql .= $whereSql;
        // compileWheres returns bindings already present in $this->bindings.

        if ($this->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        if ($this->havings !== []) {
            $sql .= ' HAVING ' . implode(' AND ', $this->havings);
        }

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * @return array{0:string,1:array}
     */
    private function compileWheres(): array
    {
        if ($this->wheres === []) {
            return ['', $this->bindings];
        }

        $sql = '';
        foreach ($this->wheres as $i => $where) {
            $sql .= ($i === 0 ? ' WHERE ' : ' ' . $where['boolean'] . ' ') . $where['sql'];
        }

        return [$sql, $this->bindings];
    }

    private function addBinding(mixed $value): string
    {
        $this->bindings[] = $value;

        return '?';
    }

    private function wrap(string $column): string
    {
        if (str_contains($column, '(') || $column === '*') {
            return $column; // already an expression
        }

        return $this->wrapColumn($column);
    }

    private function wrapColumn(string $column): string
    {
        if ($column === '*' || str_contains($column, '(')) {
            return $column;
        }

        // Handle aliases: "col AS alias"
        if (stripos($column, ' as ') !== false) {
            [$col, $alias] = preg_split('/\s+as\s+/i', $column, 2);

            return $this->wrapColumn(trim($col)) . ' AS ' . $this->wrapSegment(trim($alias));
        }

        // Handle qualified names: table.column
        if (str_contains($column, '.')) {
            return implode('.', array_map([$this, 'wrapSegment'], explode('.', $column)));
        }

        return $this->wrapSegment($column);
    }

    private function wrapTable(string $table): string
    {
        if (stripos($table, ' as ') !== false) {
            [$name, $alias] = preg_split('/\s+as\s+/i', $table, 2);

            return $this->wrapSegment(trim($name)) . ' AS ' . $this->wrapSegment(trim($alias));
        }

        return $this->wrapSegment($table);
    }

    private function wrapSegment(string $segment): string
    {
        if ($segment === '*') {
            return $segment;
        }

        return '`' . str_replace('`', '``', $segment) . '`';
    }

    private function unqualify(string $column): string
    {
        if (stripos($column, ' as ') !== false) {
            [, $alias] = preg_split('/\s+as\s+/i', $column, 2);

            return trim($alias);
        }

        return str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;
    }
}
