<?php

declare(strict_types=1);

namespace HaHireAI\Core\Database;

use HaHireAI\Core\Config\Repository;
use HaHireAI\Core\Database\Exceptions\QueryException;

/**
 * Creates and caches database connections from configuration. See
 * docs/DATABASE_ARCHITECTURE.md, docs/CONFIGURATION_GUIDE.md.
 */
final class ConnectionManager
{
    /** @var array<string, Connection> */
    private array $connections = [];

    public function __construct(private readonly Repository $config)
    {
    }

    public function connection(?string $name = null): Connection
    {
        $name ??= (string) $this->config->get('database.default', 'mysql');

        return $this->connections[$name] ??= $this->resolve($name);
    }

    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->connections = [];

            return;
        }

        unset($this->connections[$name]);
    }

    private function resolve(string $name): Connection
    {
        /** @var array<string, mixed>|null $config */
        $config = $this->config->get("database.connections.{$name}");

        if (! is_array($config)) {
            throw new QueryException("Database connection [{$name}] is not configured.");
        }

        return new Connection($config);
    }
}
