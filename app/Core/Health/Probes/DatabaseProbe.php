<?php

declare(strict_types=1);

namespace HaHireAI\Core\Health\Probes;

use HaHireAI\Core\Contracts\HealthProbe;
use HaHireAI\Core\Database\ConnectionManager;
use HaHireAI\Core\Health\HealthResult;
use Throwable;

/** Verifies the database is reachable. */
final class DatabaseProbe implements HealthProbe
{
    public function __construct(private readonly ConnectionManager $connections)
    {
    }

    public function name(): string
    {
        return 'database';
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function check(): HealthResult
    {
        try {
            $version = $this->connections->connection()->selectOne('SELECT VERSION() AS v');

            return HealthResult::ok('MySQL ' . (string) ($version['v'] ?? 'connected'));
        } catch (Throwable $e) {
            return HealthResult::unhealthy('Database unreachable: ' . $e->getMessage());
        }
    }
}
