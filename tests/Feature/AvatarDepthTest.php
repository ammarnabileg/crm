<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\AvatarService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Sprint 3.3e — avatars carry prompt/greeting/voice/knowledge/status (not just CRUD). */
final class AvatarDepthTest extends TestCase
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

    public function test_create_with_depth_fields_and_toggle_status(): void
    {
        $ws = $this->workspace();
        $id = $this->avatars->create($ws, 'Sara', [
            'persona' => 'friendly',
            'voice' => 'en-US-Aria',
            'greeting' => 'Hi there!',
            'prompt' => 'Be warm and probing.',
            'knowledge' => 'Acme hires engineers.',
        ]);

        $a = $this->avatars->find($ws, $id);
        $this->assertSame('en-US-Aria', $a['voice']);
        $this->assertSame('Hi there!', $a['greeting']);
        $this->assertSame('Be warm and probing.', $a['prompt']);
        $this->assertSame('active', $a['status']);

        $this->avatars->setStatus($ws, $id, 'inactive');
        $this->assertSame('inactive', $this->avatars->find($ws, $id)['status']);

        // update preserves the depth fields
        $this->avatars->update($ws, $id, ['name' => 'Sara 2', 'voice' => 'en-GB-Libby', 'greeting' => 'Hello!']);
        $a = $this->avatars->find($ws, $id);
        $this->assertSame('Sara 2', $a['name']);
        $this->assertSame('en-GB-Libby', $a['voice']);
    }

    private function workspace(): string
    {
        $owner = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$owner, 'U', 'o' . substr($owner, -5) . '@x.co', 'x', $now, $now]);
        $ws = Ulid::generate();
        $this->connection->statement('INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$ws, 'Acme', 'acme-' . substr($ws, -6), $owner, $now, $now]);

        return $ws;
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
