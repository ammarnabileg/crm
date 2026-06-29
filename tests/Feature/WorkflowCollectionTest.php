<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Workflow\Application\WorkflowCollectionService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Dynamic Collections — custom workspace data for the no-code Database nodes. */
final class WorkflowCollectionTest extends TestCase
{
    private Connection $connection;
    private WorkflowCollectionService $collections;
    private string $ws = '';

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
        $this->collections = new WorkflowCollectionService($this->connection);
        $this->ws = $this->workspace();
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_create_collection_and_records_with_crud(): void
    {
        $cid = $this->collections->createCollection($this->ws, 'Background Checks', [
            ['key' => 'candidate', 'label' => 'Candidate', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
        ]);

        $list = $this->collections->listCollections($this->ws);
        $this->assertCount(1, $list);
        $this->assertSame('background-checks', $list[0]['key']);
        $this->assertSame($cid, (string) ($this->collections->findByKey($this->ws, 'background-checks')['id'] ?? ''));

        $r1 = $this->collections->createRecord($this->ws, $cid, ['candidate' => 'Pat', 'status' => 'pending']);
        $this->collections->createRecord($this->ws, $cid, ['candidate' => 'Sam', 'status' => 'clear']);
        $this->assertSame(2, $this->collections->countRecords($this->ws, $cid));

        $found = $this->collections->findRecord($this->ws, $cid, ['candidate' => 'Sam']);
        $this->assertNotNull($found);
        $this->assertSame('clear', $found['data']['status']);

        $this->collections->updateRecord($this->ws, $r1, ['candidate' => 'Pat', 'status' => 'clear']);
        $this->assertSame('clear', $this->collections->findRecord($this->ws, $cid, ['candidate' => 'Pat'])['data']['status']);

        $this->collections->deleteRecord($this->ws, $r1);
        $this->assertSame(1, $this->collections->countRecords($this->ws, $cid));
        $this->collections->restoreRecord($this->ws, $r1);
        $this->assertSame(2, $this->collections->countRecords($this->ws, $cid));
    }

    public function test_collections_are_workspace_isolated(): void
    {
        $other = $this->workspace();
        $this->collections->createCollection($this->ws, 'References');
        $this->assertCount(1, $this->collections->listCollections($this->ws));
        $this->assertCount(0, $this->collections->listCollections($other));
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
