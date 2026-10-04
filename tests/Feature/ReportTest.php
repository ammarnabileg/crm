<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\AiEngine\Application\AiEngine;
use HaHireAI\Modules\AiEngine\Application\AiSettingsService;
use HaHireAI\Modules\AiEngine\Application\PromptEngine;
use HaHireAI\Modules\AiEngine\Application\ProviderRegistry;
use HaHireAI\Modules\AiEngine\Infrastructure\Providers\EchoProvider;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Recruitment\Application\OfferService;
use HaHireAI\Modules\Recruitment\Application\ReportService;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Recruitment analytics aggregation, on live MySQL 8. */
final class ReportTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private ApplicationService $applications;
    private InterviewService $interviews;
    private OfferService $offers;
    private ReportService $reports;

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
        $candidates = new CandidateProfileService($this->connection);
        $this->applications = new ApplicationService($this->connection, $this->jobs, $candidates);
        $this->offers = new OfferService($this->connection);

        $registry = new ProviderRegistry();
        $registry->register(new EchoProvider());
        $prompts = new PromptEngine($this->connection);
        $prompts->seedDefaults();
        $ai = new AiEngine($this->connection, $registry, $prompts, new AiSettingsService($this->connection, new Encrypter('base64:' . base64_encode(str_repeat('k', 32)))));
        $this->interviews = new InterviewService($this->connection, $ai);
        $this->reports = new ReportService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_report_aggregates_the_hiring_funnel(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $this->jobs->publish($ws, $this->jobs->create($ws, $owner, 'Extra role')); // a second published job
        $jobId = $this->jobs->create($ws, $owner, 'PHP Engineer');
        $this->jobs->publish($ws, $jobId);
        $appId = $this->applications->apply($ws, $jobId, $candidate);

        $iv = $this->interviews->schedule($ws, $appId, 'human', ['created_by' => $owner]);
        $this->interviews->submitEvaluation($ws, $iv, $owner, 88, 'advance', 'great');

        $offerId = $this->offers->create($ws, $appId, 'Senior PHP', 5000, 'USD', $owner);
        $this->offers->send($ws, $offerId);
        $this->offers->accept($ws, $offerId);

        $report = $this->reports->workspaceReport($ws);

        $this->assertSame(2, $report['jobs']['published']);
        $this->assertSame(1, $report['funnel']['applications']);
        $this->assertSame(1, $report['funnel']['interviews']);
        $this->assertSame(1, $report['funnel']['offers']);
        $this->assertSame(1, $report['funnel']['hires']);
        $this->assertSame(88, $report['interviews']['avg_score']);
        $this->assertSame(1, $report['offers']['accepted']);
    }

    public function test_report_is_isolated_per_workspace(): void
    {
        [$wsA, $ownerA, $candidate] = $this->workspace();
        $jobId = $this->jobs->create($wsA, $ownerA, 'Role A');
        $this->jobs->publish($wsA, $jobId);
        $this->applications->apply($wsA, $jobId, $candidate);

        [$wsB] = $this->workspace('b@example.com');
        $report = $this->reports->workspaceReport($wsB);

        $this->assertSame(0, $report['jobs']['published']);
        $this->assertSame(0, $report['funnel']['applications']);
        $this->assertSame(0, $report['funnel']['hires']);
    }

    /** @return array{0:string,1:string,2:string} [workspaceId, ownerId, candidateId] */
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
