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
use HaHireAI\Modules\Recruitment\Application\AssessmentService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\ComparisonService;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Candidate comparison + AI Q&A (spec #15) on live MySQL 8. */
final class ComparisonTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private ApplicationService $applications;
    private InterviewService $interviews;
    private AssessmentService $assessmentSvc;
    private ComparisonService $comparison;

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
        $registry = new ProviderRegistry();
        $registry->register(new EchoProvider());
        $prompts = new PromptEngine($this->connection);
        $prompts->seedDefaults();
        $ai = new AiEngine($this->connection, $registry, $prompts, new AiSettingsService($this->connection, new Encrypter('base64:' . base64_encode(str_repeat('k', 32)))));
        $this->interviews = new InterviewService($this->connection, $ai);
        $this->assessmentSvc = new AssessmentService($this->connection, $ai);
        $this->comparison = new ComparisonService($this->connection, $this->assessmentSvc, $ai);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_gather_and_ask_across_candidates(): void
    {
        [$ws, $owner] = $this->workspace();
        $c1 = $this->assessedCandidate($ws, $owner, 'Backend');
        $c2 = $this->assessedCandidate($ws, $owner, 'Frontend');

        $gathered = $this->comparison->gather($ws, [$c1, $c2]);
        $this->assertCount(2, $gathered);
        $this->assertNotNull($gathered[0]['assessment']);

        $answer = $this->comparison->ask($ws, [$c1, $c2], 'Who is the best fit for a people-facing role?', $owner);
        $this->assertNotNull($answer);
        $this->assertSame('echo', $answer['provider']);
        $this->assertNotSame('', $answer['answer']);

        // Routed through the AI Engine.
        $caps = array_map(static fn (array $r): string => (string) $r['capability'], $this->connection->select('SELECT capability FROM ai_sessions WHERE workspace_id = ?', [$ws]));
        $this->assertContains('compare_candidates', $caps);
    }

    public function test_gather_is_workspace_isolated(): void
    {
        [$wsA, $ownerA] = $this->workspace();
        $c1 = $this->assessedCandidate($wsA, $ownerA, 'Backend');

        [$wsB] = $this->workspace('b@x.co');
        $this->assertCount(0, $this->comparison->gather($wsB, [$c1]));   // B can't compare A's candidate
        $this->assertNull($this->comparison->ask($wsB, [$c1], 'best?', null));
    }

    private function assessedCandidate(string $ws, string $owner, string $jobTitle): string
    {
        $candidate = $this->user('cand-' . substr(Ulid::generate(), -5) . '@x.co');
        $jobId = $this->jobs->create($ws, $owner, $jobTitle);
        $this->jobs->publish($ws, $jobId);
        $appId = $this->applications->apply($ws, $jobId, $candidate);
        $iv = $this->interviews->schedule($ws, $appId, 'ai', ['created_by' => $owner]);
        $this->interviews->runAi($ws, $iv, $owner);
        $this->assessmentSvc->assessFromInterview($ws, $iv, $owner);

        return $candidate;
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
