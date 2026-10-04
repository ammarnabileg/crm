<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Core;

use HaHireAI\Core\Database\Connection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Connection::reconfigure() swaps the credentials of the *shared* connection in
 * place (docs/INSTALLER_ARCHITECTURE.md). The installer relies on this so the
 * migration runner / seeders / registrar — which all hold this one instance —
 * switch to the buyer's database together. No server needed: we assert the
 * in-memory state swap.
 */
final class ConnectionReconfigureTest extends TestCase
{
    public function test_reconfigure_replaces_the_config_and_keeps_no_open_handle(): void
    {
        $conn = new Connection([
            'host' => '127.0.0.1', 'port' => 3306, 'database' => 'old',
            'username' => 'root', 'password' => '', 'charset' => 'utf8mb4',
        ]);

        $ref = new ReflectionClass($conn);
        $this->assertNull($ref->getProperty('pdo')->getValue($conn), 'starts with no open handle');

        $conn->reconfigure([
            'host' => 'db.internal', 'port' => 3307, 'database' => 'wtd_db1',
            'username' => 'wtd_un1', 'password' => 'secret', 'charset' => 'utf8mb4',
        ]);

        $config = $ref->getProperty('config')->getValue($conn);
        $this->assertSame('wtd_un1', $config['username']);
        $this->assertSame('secret', $config['password']);
        $this->assertSame('wtd_db1', $config['database']);
        $this->assertSame(3307, $config['port']);
        // The lazy handle stays null so the next query connects with the new config.
        $this->assertNull($ref->getProperty('pdo')->getValue($conn));
    }
}
