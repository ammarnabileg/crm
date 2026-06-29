<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Platform\Application\PlatformAdminService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Gap B — platform Users directory: search, status columns, activate/deactivate (System Owners protected). */
final class AdminUsersTest extends TestCase
{
    private Connection $connection;
    private PlatformAdminService $admin;

    protected function setUp(): void
    {
        $this->connection = new Connection([
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE') ?: 'hahireai_test',
            'username' => getenv('DB_USERNAME') ?: 'hahireai',
            'password' => getenv('DB_PASSWORD') ?: 'hahireai_pw',
            'charset' => 'utf8mb4',
        ]);

        try {
            $this->connection->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database unavailable: ' . $e->getMessage());
        }

        $this->wipe();
        (new MigrationRunner($this->connection, new SchemaBuilder($this->connection)))->run(dirname(__DIR__, 2) . '/database/migrations');
        $this->admin = new PlatformAdminService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_users_listing_carries_status_and_supports_search(): void
    {
        $this->user('Sara Hassan', 'sara@acme.com');
        $this->user('Omar Ali', 'omar@acme.com');

        $all = $this->admin->users();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('status', $all[0]);
        $this->assertArrayHasKey('last_login_at', $all[0]);

        $hit = $this->admin->users(200, 'sara');
        $this->assertCount(1, $hit);
        $this->assertSame('Sara Hassan', $hit[0]['name']);

        $byEmail = $this->admin->users(200, 'omar@acme');
        $this->assertCount(1, $byEmail);
    }

    public function test_activate_deactivate_and_system_owner_protection(): void
    {
        $user = $this->user('Normal User', 'normal@x.co');
        $owner = $this->user('Platform Owner', 'owner@x.co', true);

        $this->assertTrue($this->admin->setUserStatus($user, 'deactivated'));
        $this->assertSame('deactivated', (string) $this->admin->findUser($user)['status']);
        $this->assertTrue($this->admin->setUserStatus($user, 'active'));
        $this->assertSame('active', (string) $this->admin->findUser($user)['status']);

        // System Owners are protected — status never changes.
        $this->assertFalse($this->admin->setUserStatus($owner, 'deactivated'));
        $this->assertSame('active', (string) $this->admin->findUser($owner)['status']);
    }

    private function user(string $name, string $email, bool $isSystemOwner = false): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, status, is_system_owner, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $name, $email, 'x', 'active', $isSystemOwner ? 1 : 0, $now, $now],
        );

        return $id;
    }

    private function wipe(): void
    {
        $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->connection->select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()') as $row) {
            $this->connection->unprepared('DROP TABLE IF EXISTS `' . $row['t'] . '`');
        }
        $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
    }
}
