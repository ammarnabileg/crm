<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Sprint 3.2 — Decision Center: stage history + structured candidate details. */
final class DecisionCenterTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private ApplicationService $applications;
    private CandidateProfileService $profiles;

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

        $this->jobs = new JobService($this->connection);
        $this->profiles = new CandidateProfileService($this->connection);
        $this->applications = new ApplicationService($this->connection, $this->jobs, $this->profiles);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_status_changes_are_recorded_in_history(): void
    {
        [$ws, $owner] = $this->workspace();
        $cand = $this->user('c@x.co');
        $job = $this->jobs->create($ws, $owner, 'Engineer');
        $appId = $this->applications->apply($ws, $job, $cand);

        $this->applications->setStatus($ws, $appId, 'qualified', $owner);
        $this->applications->setStatus($ws, $appId, 'offer', $owner);
        $this->applications->setStatus($ws, $appId, 'offer', $owner);   // no-op (same status)

        $history = $this->applications->statusHistory($ws, $appId);
        $this->assertCount(2, $history);                                 // applied→qualified, qualified→offer
        $this->assertSame('offer', $history[0]['to_status']);            // newest first
        $this->assertSame('qualified', $history[0]['from_status']);
    }

    public function test_candidate_details_round_trip(): void
    {
        [$ws] = $this->workspace();
        $cand = $this->user('c@x.co');

        $this->profiles->saveDetails($ws, $cand, [
            'skills' => 'PHP, MySQL',
            'languages' => 'Arabic, English',
            'expected_salary' => 6000,
        ]);

        $d = $this->profiles->details($ws, $cand);
        $this->assertSame('PHP, MySQL', $d['skills']);
        $this->assertSame(6000, $d['expected_salary']);

        // Workspace-isolated.
        [$wsB] = $this->workspace('b@x.co');
        $this->assertSame([], $this->profiles->details($wsB, $cand));
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
