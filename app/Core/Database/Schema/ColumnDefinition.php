<?php

declare(strict_types=1);

namespace HaHireAI\Core\Database\Schema;

/** A single column definition with fluent modifiers. */
final class ColumnDefinition
{
    private bool $nullable = false;
    private bool $hasDefault = false;
    private mixed $default = null;
    private bool $rawDefault = false;

    public function __construct(
        public readonly string $name,
        private readonly string $type,
    ) {
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->hasDefault = true;
        $this->default = $value;
        $this->rawDefault = false;

        return $this;
    }

    public function useCurrent(): self
    {
        $this->hasDefault = true;
        $this->default = 'CURRENT_TIMESTAMP';
        $this->rawDefault = true;

        return $this;
    }

    public function compile(): string
    {
        $sql = "`{$this->name}` {$this->type} " . ($this->nullable ? 'NULL' : 'NOT NULL');

        if ($this->hasDefault) {
            $sql .= ' DEFAULT ' . $this->compileDefault();
        }

        return $sql;
    }

    private function compileDefault(): string
    {
        if ($this->rawDefault) {
            return (string) $this->default;
        }

        return match (true) {
            is_null($this->default) => 'NULL',
            is_bool($this->default) => $this->default ? '1' : '0',
            is_int($this->default), is_float($this->default) => (string) $this->default,
            default => "'" . str_replace("'", "''", (string) $this->default) . "'",
        };
    }
}
