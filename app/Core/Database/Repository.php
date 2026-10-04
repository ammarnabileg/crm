<?php

declare(strict_types=1);

namespace HaHireAI\Core\Database;

use HaHireAI\Shared\Ulid;

/**
 * Base repository with ULID keys, timestamps, and the mandatory tenant guard.
 * Workspace-scoped repositories MUST set workspaceScoped() = true; every query
 * is then filtered by workspace_id. See docs/DATABASE_GUIDE.md §6,
 * docs/SECURITY_GUIDE.md (multi-tenant isolation).
 */
abstract class Repository
{
    public function __construct(
        protected readonly Connection $connection,
        protected readonly ?string $workspaceId = null,
    ) {
    }

    abstract protected function table(): string;

    /** Workspace-scoped tables override this to true. */
    protected function workspaceScoped(): bool
    {
        return false;
    }

    /** Return a copy bound to a specific workspace (the tenant guard). */
    public function forWorkspace(string $workspaceId): static
    {
        return new static($this->connection, $workspaceId);
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->connection->selectOne(
            "SELECT * FROM `{$this->table()}` WHERE `id` = ?" . $this->tenantClause(),
            $this->withTenant([$id]),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return string  the new row's id
     */
    public function create(array $attributes): string
    {
        $id = $attributes['id'] ?? Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');

        $row = array_merge($attributes, [
            'id' => $id,
            'created_at' => $attributes['created_at'] ?? $now,
            'updated_at' => $attributes['updated_at'] ?? $now,
        ]);

        if ($this->workspaceScoped()) {
            $row['workspace_id'] = $this->requireWorkspace();
        }

        $columns = array_keys($row);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $columns));

        $this->connection->statement(
            "INSERT INTO `{$this->table()}` ({$columnList}) VALUES ({$placeholders})",
            array_values($row),
        );

        return (string) $id;
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): int
    {
        $attributes['updated_at'] = gmdate('Y-m-d H:i:s');

        $assignments = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($attributes)));
        $bindings = [...array_values($attributes), $id];

        return $this->connection->statement(
            "UPDATE `{$this->table()}` SET {$assignments} WHERE `id` = ?" . $this->tenantClause(),
            $this->workspaceScoped() ? [...$bindings, $this->requireWorkspace()] : $bindings,
        );
    }

    public function delete(string $id): int
    {
        return $this->connection->statement(
            "DELETE FROM `{$this->table()}` WHERE `id` = ?" . $this->tenantClause(),
            $this->withTenant([$id]),
        );
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->connection->select(
            "SELECT * FROM `{$this->table()}` WHERE 1=1" . $this->tenantClause(),
            $this->workspaceScoped() ? [$this->requireWorkspace()] : [],
        );
    }

    /**
     * @param  array<string, mixed>  $criteria  column => value (equality)
     * @return array<string, mixed>|null
     */
    public function firstWhere(array $criteria): ?array
    {
        $clauses = array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($criteria));
        $sql = "SELECT * FROM `{$this->table()}` WHERE " . implode(' AND ', $clauses) . $this->tenantClause();

        return $this->connection->selectOne($sql, $this->withTenant(array_values($criteria)));
    }

    protected function tenantClause(): string
    {
        return $this->workspaceScoped() ? ' AND `workspace_id` = ?' : '';
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<mixed>
     */
    protected function withTenant(array $bindings): array
    {
        return $this->workspaceScoped() ? [...$bindings, $this->requireWorkspace()] : $bindings;
    }

    protected function requireWorkspace(): string
    {
        return $this->workspaceId
            ?? throw new \RuntimeException(static::class . ' is workspace-scoped but no workspace is bound (tenant guard).');
    }
}
