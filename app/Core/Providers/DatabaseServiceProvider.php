<?php

declare(strict_types=1);

namespace HaHireAI\Core\Providers;

use HaHireAI\Core\Config\Repository as Config;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\ConnectionManager;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Core\Health\HealthChecker;
use HaHireAI\Core\Health\Probes\DatabaseProbe;

/**
 * Registers the database layer: connection manager, default connection, schema
 * builder, and migration runner. See docs/DATABASE_ARCHITECTURE.md.
 */
final class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $c = $this->container;

        $c->singleton(ConnectionManager::class, static fn (): ConnectionManager => new ConnectionManager($c->make(Config::class)));
        $c->singleton(Connection::class, static fn (): Connection => $c->make(ConnectionManager::class)->connection());
        $c->singleton(SchemaBuilder::class, static fn (): SchemaBuilder => new SchemaBuilder($c->make(Connection::class)));
        $c->singleton(MigrationRunner::class, static fn (): MigrationRunner => new MigrationRunner(
            $c->make(Connection::class),
            $c->make(SchemaBuilder::class),
        ));
    }

    public function boot(): void
    {
        // Register the DB health probe (only runs when /health is hit).
        $this->container->make(HealthChecker::class)->register(
            new DatabaseProbe($this->container->make(ConnectionManager::class)),
        );
    }
}
