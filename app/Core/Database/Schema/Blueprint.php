<?php

declare(strict_types=1);

namespace HaHireAI\Core\Database\Schema;

/**
 * Fluent table definition compiled to MySQL 8 DDL. Enforces project standards:
 * ULID CHAR(26) keys, snake_case, utf8mb4, explicit FKs. No MySQL ENUM.
 * See docs/DATABASE_GUIDE.md, docs/DATABASE_ARCHITECTURE.md.
 */
final class Blueprint
{
    /** @var list<ColumnDefinition> */
    private array $columns = [];

    private ?string $primaryKey = null;

    /** @var list<array{name: string, columns: list<string>}> */
    private array $uniques = [];

    /** @var list<array{name: string, columns: list<string>}> */
    private array $indexes = [];

    /** @var list<array{column: string, refTable: string, refColumn: string, onDelete: string, onUpdate: string}> */
    private array $foreigns = [];

    public function __construct(public readonly string $table)
    {
    }

    /** ULID primary key (the project standard). */
    public function ulidPrimary(string $name = 'id'): ColumnDefinition
    {
        $this->primaryKey = $name;

        return $this->addColumn($name, 'CHAR(26)');
    }

    /** A ULID foreign-key column (CHAR(26)); call ->nullable() if optional. */
    public function ulid(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'CHAR(26)');
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($name, "VARCHAR({$length})");
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'TEXT');
    }

    public function longText(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'LONGTEXT');
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'INT');
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'BIGINT');
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'TINYINT(1)');
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'JSON');
    }

    public function datetime(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'DATETIME');
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'TIMESTAMP');
    }

    /** Standard created_at / updated_at (nullable, app-managed). */
    public function timestamps(): void
    {
        $this->datetime('created_at')->nullable();
        $this->datetime('updated_at')->nullable();
    }

    /** Soft-delete column. */
    public function softDeletes(): void
    {
        $this->datetime('deleted_at')->nullable();
    }

    /** @param string|list<string> $columns */
    public function unique(string|array $columns, ?string $name = null): void
    {
        $columns = (array) $columns;
        $this->uniques[] = ['name' => $name ?? $this->indexName('uq', $columns), 'columns' => $columns];
    }

    /** @param string|list<string> $columns */
    public function index(string|array $columns, ?string $name = null): void
    {
        $columns = (array) $columns;
        $name ??= $this->indexName('idx', $columns);

        // Avoid duplicate-named indexes (e.g. an explicit index plus the
        // implicit index that foreign() adds for the same column).
        foreach ($this->indexes as $existing) {
            if ($existing['name'] === $name) {
                return;
            }
        }

        $this->indexes[] = ['name' => $name, 'columns' => $columns];
    }

    public function foreign(
        string $column,
        string $refTable,
        string $refColumn = 'id',
        string $onDelete = 'RESTRICT',
        string $onUpdate = 'CASCADE',
    ): void {
        $this->foreigns[] = [
            'column' => $column,
            'refTable' => $refTable,
            'refColumn' => $refColumn,
            'onDelete' => strtoupper($onDelete),
            'onUpdate' => strtoupper($onUpdate),
        ];
        $this->index($column);
    }

    /** Compile to a CREATE TABLE statement. */
    public function toCreateSql(): string
    {
        $lines = array_map(static fn (ColumnDefinition $c): string => '  ' . $c->compile(), $this->columns);

        if ($this->primaryKey !== null) {
            $lines[] = "  PRIMARY KEY (`{$this->primaryKey}`)";
        }

        foreach ($this->uniques as $u) {
            $lines[] = "  UNIQUE KEY `{$u['name']}` (" . $this->columnList($u['columns']) . ')';
        }

        foreach ($this->indexes as $i) {
            $lines[] = "  KEY `{$i['name']}` (" . $this->columnList($i['columns']) . ')';
        }

        foreach ($this->foreigns as $f) {
            $cname = $this->indexName('fk', [$f['column']]);
            $lines[] = "  CONSTRAINT `{$cname}` FOREIGN KEY (`{$f['column']}`) " .
                "REFERENCES `{$f['refTable']}` (`{$f['refColumn']}`) " .
                "ON DELETE {$f['onDelete']} ON UPDATE {$f['onUpdate']}";
        }

        // utf8mb4_unicode_ci is portable across MySQL 5.7/8.0 and MariaDB; the
        // MySQL-8-only utf8mb4_0900_ai_ci breaks installs on older/MariaDB hosts.
        return "CREATE TABLE `{$this->table}` (\n" . implode(",\n", $lines) .
            "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    private function addColumn(string $name, string $type): ColumnDefinition
    {
        return $this->columns[] = new ColumnDefinition($name, $type);
    }

    /** @param list<string> $columns */
    private function columnList(array $columns): string
    {
        return implode(', ', array_map(static fn (string $c): string => "`{$c}`", $columns));
    }

    /** @param list<string> $columns */
    private function indexName(string $prefix, array $columns): string
    {
        $base = $this->table . '_' . implode('_', $columns) . '_' . $prefix;

        return strlen($base) <= 64 ? $base : substr($prefix . '_' . md5($base), 0, 64);
    }
}
