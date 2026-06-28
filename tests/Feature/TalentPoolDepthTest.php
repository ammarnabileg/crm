<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\TalentPoolService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Sprint 3.3d — Talent Pool smart lists + bulk add. */
final class TalentPoolDepthTest extends TestCase
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

    public function test_bulk_add_to_pool(): void
    {
        [$ws, $owner] = $this->workspace();
        $pool = $this->pools->createPool($ws, 'Future', null, $owner);
        $c1 = $this->user('c1@x.co');
        $c2 = $this->user('c2@x.co');

        $n = $this->pools->addCandidates($ws, $pool, [$c1, $c2, $c1], $owner);  // dup ignored
        $this->assertSame(2, $n);
        $this->assertCount(2, $this->pools->members($ws, $pool));
    }

    public function test_smart_list_strong_not_hired(): void
    {
        [$ws] = $this->workspace();
        $strong = $this->user('strong@x.co');
        $this->assessment($ws, $strong, 82);
        $weak = $this->user('weak@x.co');
        $this->assessment($ws, $weak, 40);

        $lists = $this->pools->smartLists($ws);
        $strongList = array_values(array_filter($lists, static fn (array $l): bool => $l['key'] === 'strong'))[0];
        $ids = array_map(static fn (array $c): string => (string) $c['user_id'], $strongList['candidates']);

        $this->assertContains($strong, $ids);
        $this->assertNotContains($weak, $ids);
    }

    private function assessment(string $ws, string $userId, int $fit): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO candidate_assessments (id, workspace_id, candidate_user_id, application_id, interview_id, source, fit_score, recommendation, summary, strengths, weaknesses, skills, behavior, red_flags, cv, ai_provider, created_by, created_at)
             VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)',
            [Ulid::generate(), $ws, $userId, 'interview', $fit, 'maybe', 'summary', '[]', '[]', '{}', '{}', '[]', 'null', 'echo', $now],
        );
    }

    /** @return array{0:string,1:string} */
    private function workspace(string $ownerEmail = 'owner@x.co'): array
    {
        $owner = $this->user($ownerEmail);
        $workspaceId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $owner, $now, $now],
        );

        return [$workspaceId, $owner];
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
