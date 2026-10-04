<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Tasks\Application\TaskService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Gap C — workspace tasks: create, assign, list/filter, complete/reopen, delete. */
final class TaskServiceTest extends TestCase
{
    private Connection $connection;
    private TaskService $tasks;
    private string $ws = '';
    private string $userA = '';
    private string $userB = '';

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
        $this->tasks = new TaskService($this->connection);
        $this->ws = $this->workspace();
        $this->userA = $this->user('a@x.co');
        $this->userB = $this->user('b@x.co');
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_create_assign_list_and_my_open_tasks(): void
    {
        $mine = $this->tasks->create($this->ws, 'Call candidate', $this->userA, ['assignee_user_id' => $this->userA, 'priority' => 'high', 'due_at' => '2026-07-01 09:00:00']);
        $this->tasks->create($this->ws, 'Review CVs', $this->userA, ['assignee_user_id' => $this->userB]);
        $this->tasks->create($this->ws, 'Unassigned chore', $this->userA, []);

        $this->assertCount(3, $this->tasks->listForWorkspace($this->ws));
        $this->assertCount(1, $this->tasks->listForWorkspace($this->ws, ['assignee_user_id' => $this->userB]));
        $this->assertCount(1, $this->tasks->listForWorkspace($this->ws, ['q' => 'CV']));

        $open = $this->tasks->openForUser($this->ws, $this->userA);
        $this->assertCount(1, $open);
        $this->assertSame($mine, (string) $open[0]['id']);
        $this->assertSame(1, $this->tasks->countOpenForUser($this->ws, $this->userA));
    }

    public function test_complete_reopen_and_delete(): void
    {
        $id = $this->tasks->create($this->ws, 'Send offer', $this->userA, ['assignee_user_id' => $this->userA]);

        $this->assertTrue($this->tasks->setStatus($this->ws, $id, 'done'));
        $this->assertSame('done', (string) $this->tasks->find($this->ws, $id)['status']);
        $this->assertNotNull($this->tasks->find($this->ws, $id)['completed_at']);
        $this->assertSame(0, $this->tasks->countOpenForUser($this->ws, $this->userA));

        $this->assertTrue($this->tasks->setStatus($this->ws, $id, 'open'));
        $this->assertSame('open', (string) $this->tasks->find($this->ws, $id)['status']);
        $this->assertNull($this->tasks->find($this->ws, $id)['completed_at']);

        // Tenant guard + delete.
        $this->assertFalse($this->tasks->setStatus(Ulid::generate(), $id, 'done'));
        $this->assertTrue($this->tasks->delete($this->ws, $id));
        $this->assertNull($this->tasks->find($this->ws, $id));
    }

    private function workspace(): string
    {
        $owner = $this->user('owner@x.co');
        $ws = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$ws, 'Acme', 'acme-' . substr($ws, -6), $owner, $now, $now]);

        return $ws;
    }

    private function user(string $email): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$id, 'U', substr($id, -4) . '.' . $email, 'x', $now, $now]);

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
