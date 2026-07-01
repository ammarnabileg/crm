<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\AvatarService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** AI interviewer avatars — workspace-scoped CRUD on live MySQL 8. */
final class AvatarTest extends TestCase
{
    private Connection $connection;
    private AvatarService $avatars;

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
        $this->avatars = new AvatarService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_create_update_delete_and_isolation(): void
    {
        $a = $this->workspace();
        $b = $this->workspace();

        $id = $this->avatars->create($a, 'Sara', ['persona' => 'friendly', 'language' => 'ar'], null);
        $this->assertCount(1, $this->avatars->listForWorkspace($a));
        $this->assertSame('friendly', $this->avatars->find($a, $id)['persona']);

        $this->avatars->update($a, $id, ['name' => 'Sara T', 'persona' => 'formal', 'language' => 'en']);
        $this->assertSame('formal', $this->avatars->find($a, $id)['persona']);
        $this->assertSame('Sara T', $this->avatars->find($a, $id)['name']);

        // Isolation: B can't see or mutate A's avatar.
        $this->assertNull($this->avatars->find($b, $id));
        $this->assertCount(0, $this->avatars->listForWorkspace($b));

        $this->avatars->delete($a, $id);
        $this->assertCount(0, $this->avatars->listForWorkspace($a));
    }

    private function workspace(): string
    {
        $userId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, 'Owner', 'o-' . substr($userId, -6) . '@x.co', 'x', $now, $now],
        );
        $workspaceId = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $userId, $now, $now],
        );

        return $workspaceId;
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
