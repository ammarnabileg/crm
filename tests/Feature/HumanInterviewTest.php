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
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Human (panel) interviews — scheduling, the structured 1–5 evaluation, reschedule/archive (spec #12). */
final class HumanInterviewTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private ApplicationService $applications;
    private InterviewService $interviews;

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
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_schedule_human_interview_with_meeting_link(): void
    {
        [$ws, $owner] = $this->workspace();
        $appId = $this->application($ws, $owner);

        $id = $this->interviews->schedule($ws, $appId, 'human', [
            'mode' => 'online',
            'meeting_link' => 'https://meet.example.com/abc',
            'interviewer_user_id' => $owner,
            'created_by' => $owner,
        ]);

        $iv = $this->interviews->findDetailed($ws, $id);
        $this->assertNotNull($iv);
        $this->assertSame('human', $iv['type']);
        $this->assertSame('online', $iv['mode']);
        $this->assertSame('https://meet.example.com/abc', $iv['meeting_link']);
        $this->assertSame('scheduled', $iv['status']);
        $this->assertNotSame('', (string) $iv['candidate_name']);
    }

    public function test_list_filters_by_type(): void
    {
        [$ws, $owner] = $this->workspace();
        $appId = $this->application($ws, $owner);
        $this->interviews->schedule($ws, $appId, 'human', ['created_by' => $owner]);
        $this->interviews->schedule($ws, $appId, 'ai', ['created_by' => $owner]);

        $this->assertCount(1, $this->interviews->listForWorkspace($ws, 'human'));
        $this->assertCount(1, $this->interviews->listForWorkspace($ws, 'ai'));
        $this->assertCount(2, $this->interviews->listForWorkspace($ws));
    }

    public function test_structured_evaluation_is_stored_and_scored(): void
    {
        [$ws, $owner] = $this->workspace();
        $appId = $this->application($ws, $owner);
        $id = $this->interviews->schedule($ws, $appId, 'human', ['created_by' => $owner]);

        $this->interviews->submitHumanEvaluation(
            $ws,
            $id,
            $owner,
            ['technical_depth' => 5, 'problem_solving' => 4, 'communication' => 5, 'culture_fit' => 4, 'takes_ownership' => 5, 'seniority_fit' => 4],
            5,
            'advance',
            'Excellent system design.',
            'Light on testing.',
            'Strong hire.',
        );

        $iv = $this->interviews->findDetailed($ws, $id);
        $this->assertNotNull($iv);
        $this->assertSame('completed', $iv['status']);
        $this->assertSame(100, (int) $iv['score']);          // overall 5 * 20
        $this->assertSame('advance', $iv['recommendation']);
        $this->assertSame(5, $iv['details_decoded']['ratings']['technical_depth']);
        $this->assertSame('Excellent system design.', $iv['details_decoded']['strengths']);
        $this->assertSame($owner, $iv['interviewer_user_id']);
    }

    public function test_evaluation_clamps_out_of_range_ratings(): void
    {
        [$ws, $owner] = $this->workspace();
        $appId = $this->application($ws, $owner);
        $id = $this->interviews->schedule($ws, $appId, 'human', ['created_by' => $owner]);

        $this->interviews->submitHumanEvaluation($ws, $id, $owner, ['technical_depth' => 99], 9, 'nonsense', null, null, null);

        $iv = $this->interviews->findDetailed($ws, $id);
        $this->assertSame(100, (int) $iv['score']);          // overall clamped to 5 → 100
        $this->assertSame(5, $iv['details_decoded']['ratings']['technical_depth']);  // clamped to 5
        $this->assertSame('hold', $iv['recommendation']);    // invalid → hold
    }

    public function test_reschedule_and_archive(): void
    {
        [$ws, $owner] = $this->workspace();
        $appId = $this->application($ws, $owner);
        $id = $this->interviews->schedule($ws, $appId, 'human', ['mode' => 'online', 'meeting_link' => 'https://a', 'created_by' => $owner]);

        $this->interviews->reschedule($ws, $id, ['mode' => 'onsite', 'meeting_link' => null, 'scheduled_at' => '2026-07-01 10:00:00', 'interviewer_user_id' => $owner]);
        $iv = $this->interviews->find($ws, $id);
        $this->assertSame('onsite', $iv['mode']);
        $this->assertNull($iv['meeting_link']);
        $this->assertSame('2026-07-01 10:00:00', $iv['scheduled_at']);

        $this->interviews->archive($ws, $id);
        $this->assertNull($this->interviews->find($ws, $id));   // soft-deleted, hidden
        $this->assertCount(0, $this->interviews->listForWorkspace($ws, 'human'));
    }

    public function test_workspace_isolation(): void
    {
        [$wsA, $ownerA] = $this->workspace();
        $appId = $this->application($wsA, $ownerA);
        $id = $this->interviews->schedule($wsA, $appId, 'human', ['created_by' => $ownerA]);

        [$wsB] = $this->workspace('b@x.co');
        $this->assertNull($this->interviews->findDetailed($wsB, $id));
        $this->assertCount(0, $this->interviews->listForWorkspace($wsB, 'human'));
    }

    private function application(string $ws, string $owner): string
    {
        $candidate = $this->user('cand-' . substr(Ulid::generate(), -5) . '@x.co');
        $jobId = $this->jobs->create($ws, $owner, 'Senior Engineer');
        $this->jobs->publish($ws, $jobId);

        return $this->applications->apply($ws, $jobId, $candidate);
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
