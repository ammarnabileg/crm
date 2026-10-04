<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\DashboardService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Recruitment\Application\OfferService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Sprint 3 — Executive Dashboard KPIs computed from real data (no placeholders). */
final class DashboardServiceTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private ApplicationService $applications;
    private OfferService $offers;
    private DashboardService $dashboard;

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
        $this->offers = new OfferService($this->connection);
        $this->dashboard = new DashboardService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_dashboard_reports_real_counts_and_funnel(): void
    {
        [$ws, $owner] = $this->workspace();
        $jobA = $this->jobs->create($ws, $owner, 'Engineer');
        $this->jobs->publish($ws, $jobA);
        $this->jobs->create($ws, $owner, 'Draft role');                 // unpublished

        $c1 = $this->user('c1@x.co');
        $c2 = $this->user('c2@x.co');
        $appId = $this->applications->apply($ws, $jobA, $c1);
        $this->applications->apply($ws, $jobA, $c2);

        $d = $this->dashboard->dashboard($ws);

        $this->assertSame(2, $d['counts']['jobs_total']);
        $this->assertSame(1, $d['counts']['jobs_open']);
        $this->assertSame(2, $d['counts']['applicants']);
        $this->assertSame(2, $d['funnel']['applications']);
        $this->assertArrayHasKey('applied', $d['pipeline']);
        $this->assertSame(2, $d['pipeline']['applied']);
        $this->assertIsArray($d['health']['signals']);
        $this->assertCount(2, $d['recent_activity']);
    }

    public function test_dashboard_is_workspace_isolated(): void
    {
        [$wsA, $ownerA] = $this->workspace();
        $jobA = $this->jobs->create($wsA, $ownerA, 'Engineer');
        $this->jobs->publish($wsA, $jobA);
        $this->applications->apply($wsA, $jobA, $this->user('c@x.co'));

        [$wsB] = $this->workspace('b@x.co');
        $d = $this->dashboard->dashboard($wsB);
        $this->assertSame(0, $d['counts']['applicants']);
        $this->assertSame(0, $d['counts']['jobs_total']);
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
