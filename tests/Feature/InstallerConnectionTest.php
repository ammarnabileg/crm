<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Exceptions\QueryException;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Regression for the zero-touch installer (docs/INSTALLER_ARCHITECTURE.md): the
 * migration runner, schema builder and seeders are built holding the *shared*
 * Connection singleton BEFORE the buyer submits credentials (no .env yet, so it
 * defaults to root@localhost). The installer must point that shared instance at
 * the buyer's database — rebinding the container key alone left those already
 * built singletons stranded on root/no-password, which surfaced as
 * "Access denied for user 'root'@'localhost' (using password: NO)".
 *
 * This proves an object that captured the connection before reconfigure() uses
 * the new credentials afterwards.
 */
final class InstallerConnectionTest extends TestCase
{
    /** @return array<string,mixed> */
    private function validConfig(): array
    {
        return [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE') ?: 'hahireai_test',
            'username' => getenv('DB_USERNAME') ?: 'hahireai',
            'password' => getenv('DB_PASSWORD') ?: 'hahireai_pw',
            'charset' => 'utf8mb4',
        ];
    }

    private function assertDbReachable(Connection $probe): void
    {
        try {
            $probe->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database unavailable: ' . $e->getMessage());
        }
    }

    public function test_a_runner_built_on_the_default_connection_switches_after_reconfigure(): void
    {
        $this->assertDbReachable(new Connection($this->validConfig()));

        // The pre-install state: a single shared Connection on the wrong, default
        // credentials (no .env => root, empty password), with the migration runner
        // and schema builder already built around it.
        $shared = new Connection([
            'host' => '127.0.0.1', 'port' => 3306, 'database' => 'hahireai',
            'username' => 'root', 'password' => '', 'charset' => 'utf8mb4',
        ]);
        $schema = new SchemaBuilder($shared);
        $runner = new MigrationRunner($shared, $schema);

        // Before the fix the held connection is unusable.
        try {
            $shared->select('SELECT 1');
            $this->fail('Expected the default-credential connection to be refused.');
        } catch (QueryException) {
            // expected: access denied on the bogus default credentials
        }

        // The installer points the SAME instance at the buyer's database.
        $shared->reconfigure($this->validConfig());

        // The runner and schema builder captured $shared earlier, yet now operate
        // on the buyer's database — proving they switched together.
        $this->assertSame(1, (int) $shared->select('SELECT 1 AS ok')[0]['ok']);
        $runner->ensureMigrationsTable();
        $this->assertSame(
            1,
            (int) $shared->select(
                "SELECT COUNT(*) AS c FROM information_schema.tables
                  WHERE table_schema = DATABASE() AND table_name = 'migrations'"
            )[0]['c'],
        );

        $shared->unprepared('DROP TABLE IF EXISTS `migrations`');
    }
}
