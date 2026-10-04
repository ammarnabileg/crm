<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\TalentPoolService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Talent pools — saved candidate lists, workspace-scoped, on live MySQL 8. */
final class TalentPoolTest extends TestCase
{
    private Connection $connection;
    private TalentPoolService $pools;

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
        $this->pools = new TalentPoolService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_create_pool_save_and_remove_candidate(): void
    {
        [$ws, $owner] = $this->workspace();
        $candidate = $this->user('c@x.co');
        $poolId = $this->pools->createPool($ws, 'Senior PHP — future', 'strong rejects', $owner);

        $this->pools->addCandidate($ws, $poolId, $candidate, $owner, 'great culture fit');
        $this->pools->addCandidate($ws, $poolId, $candidate, $owner);   // idempotent

        $this->assertCount(1, $this->pools->members($ws, $poolId));
        $this->assertSame(1, (int) $this->pools->listPools($ws)[0]['members']);
        $this->assertCount(1, $this->pools->poolsForCandidate($ws, $candidate));

        $this->pools->removeCandidate($ws, $poolId, $candidate);
        $this->assertCount(0, $this->pools->members($ws, $poolId));
    }

    public function test_a_candidate_can_belong_to_multiple_pools(): void
    {
        [$ws, $owner] = $this->workspace();
        $candidate = $this->user('c@x.co');
        $a = $this->pools->createPool($ws, 'Pool A', null, $owner);
        $b = $this->pools->createPool($ws, 'Pool B', null, $owner);

        $this->pools->addCandidate($ws, $a, $candidate, $owner);
        $this->pools->addCandidate($ws, $b, $candidate, $owner);

        $this->assertCount(2, $this->pools->poolsForCandidate($ws, $candidate));
    }

    public function test_pools_are_isolated_per_workspace(): void
    {
        [$wsA, $ownerA] = $this->workspace();
        [$wsB] = $this->workspace('b@x.co');
        $candidate = $this->user('c@x.co');
        $poolA = $this->pools->createPool($wsA, 'A', null, $ownerA);
        $this->pools->addCandidate($wsA, $poolA, $candidate, $ownerA);

        $this->assertCount(0, $this->pools->listPools($wsB));
        $this->assertNull($this->pools->findPool($wsB, $poolA));        // cross-workspace = not found
        // Adding to A's pool from B is rejected (pool not in B).
        $this->pools->addCandidate($wsB, $poolA, $candidate, null);
        $this->assertCount(1, $this->pools->members($wsA, $poolA));     // unchanged
    }

    /** @return array{0:string,1:string} */
    private function workspace(string $ownerEmail = 'owner@x.co'): array
    {
        $ownerId = $this->user($ownerEmail);
        $workspaceId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $ownerId, $now, $now],
        );

        return [$workspaceId, $ownerId];
    }

    private function user(string $email): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, 'User', $email . '.' . substr($id, -4), 'x', $now, $now],
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
