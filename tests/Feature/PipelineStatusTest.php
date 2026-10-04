<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\Exceptions\ApplicationException;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Job enrichment + the 11-status decision workflow / board, on live MySQL 8. */
final class PipelineStatusTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private ApplicationService $applications;

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
        $this->applications = new ApplicationService($this->connection, $this->jobs, new CandidateProfileService($this->connection));
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_job_stores_seniority_and_salary_range(): void
    {
        [$ws, $owner] = $this->workspace();
        $jobId = $this->jobs->create($ws, $owner, 'Staff Engineer', 'desc', 'Remote', 'Full-time', [
            'seniority' => 'senior', 'salary_min' => 4000, 'salary_max' => 8000, 'currency' => 'USD',
        ]);

        $job = $this->jobs->find($ws, $jobId);
        $this->assertSame('senior', $job['seniority']);
        $this->assertSame(4000, (int) $job['salary_min']);
        $this->assertSame(8000, (int) $job['salary_max']);
        $this->assertSame('USD', $job['currency']);
    }

    public function test_set_status_moves_application_through_the_workflow(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $jobId = $this->jobs->create($ws, $owner, 'PHP Engineer');
        $this->jobs->publish($ws, $jobId);
        $appId = $this->applications->apply($ws, $jobId, $candidate);

        $this->applications->setStatus($ws, $appId, 'tech_interview', $owner);
        $this->assertSame('tech_interview', $this->applications->find($ws, $appId)['status']);

        $this->applications->setStatus($ws, $appId, 'offer', $owner);
        $this->assertSame('offer', $this->applications->find($ws, $appId)['status']);
    }

    public function test_invalid_status_is_rejected(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $jobId = $this->jobs->create($ws, $owner, 'PHP Engineer');
        $this->jobs->publish($ws, $jobId);
        $appId = $this->applications->apply($ws, $jobId, $candidate);

        $this->expectException(ApplicationException::class);
        $this->applications->setStatus($ws, $appId, 'not_a_status', $owner);
    }

    public function test_status_board_groups_by_decision_status_and_is_isolated(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $jobId = $this->jobs->create($ws, $owner, 'PHP Engineer');
        $this->jobs->publish($ws, $jobId);
        $appId = $this->applications->apply($ws, $jobId, $candidate);
        $this->applications->setStatus($ws, $appId, 'qualified', $owner);

        $board = $this->applications->statusBoard($ws);
        $this->assertArrayHasKey('qualified', $board);
        $this->assertCount(1, $board['qualified']);
        $this->assertSame([], $board['applied']);                 // moved out of applied

        [$wsB] = $this->workspace('b@example.com');
        $boardB = $this->applications->statusBoard($wsB);
        $this->assertSame([], $boardB['qualified']);              // isolated per workspace
    }

    /** @return array{0:string,1:string,2:string} */
    private function workspace(string $ownerEmail = 'owner@example.com'): array
    {
        $ownerId = $this->user('Owner', $ownerEmail);
        $candidateId = $this->user('Sara', 'sara-' . substr(Ulid::generate(), -6) . '@x.co');
        $workspaceId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $ownerId, $now, $now],
        );

        return [$workspaceId, $ownerId, $candidateId];
    }

    private function user(string $name, string $email): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $name, $email, 'x', $now, $now],
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
