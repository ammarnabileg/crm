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
use HaHireAI\Modules\Recruitment\Application\InterviewFeedbackService;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Candidate interview feedback (spec #17) on live MySQL 8. */
final class InterviewFeedbackTest extends TestCase
{
    private Connection $connection;
    private InterviewService $interviews;
    private InterviewFeedbackService $feedback;
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
        $registry = new ProviderRegistry();
        $registry->register(new EchoProvider());
        $prompts = new PromptEngine($this->connection);
        $prompts->seedDefaults();
        $ai = new AiEngine($this->connection, $registry, $prompts, new AiSettingsService($this->connection, new Encrypter('base64:' . base64_encode(str_repeat('k', 32)))));
        $this->interviews = new InterviewService($this->connection, $ai);
        $this->feedback = new InterviewFeedbackService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_record_is_single_and_summarised(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $interviewId = $this->interview($ws, $owner, $candidate);

        $this->assertFalse($this->feedback->exists($interviewId));

        $this->feedback->record($ws, $interviewId, 5, 'Smooth and fair');
        $this->feedback->record($ws, $interviewId, 1, 'duplicate ignored'); // one per interview

        $this->assertTrue($this->feedback->exists($interviewId));
        $this->assertSame(5, (int) $this->feedback->forInterview($interviewId)['rating']);

        $summary = $this->feedback->summary($ws);
        $this->assertSame(1, $summary['count']);
        $this->assertSame(5.0, $summary['average']);
    }

    public function test_rating_is_clamped(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $interviewId = $this->interview($ws, $owner, $candidate);

        $this->feedback->record($ws, $interviewId, 99, null); // clamped to 5
        $this->assertSame(5, (int) $this->feedback->forInterview($interviewId)['rating']);
    }

    private function interview(string $ws, string $owner, string $candidate): string
    {
        $jobId = $this->jobs->create($ws, $owner, 'PHP Engineer');
        $this->jobs->publish($ws, $jobId);
        $appId = $this->applications->apply($ws, $jobId, $candidate);

        return $this->interviews->schedule($ws, $appId, 'ai', ['created_by' => $owner]);
    }

    /** @return array{0:string,1:string,2:string} */
    private function workspace(): array
    {
        $owner = $this->user('o@x.co');
        $candidate = $this->user('c@x.co');
        $workspaceId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $owner, $now, $now],
        );

        return [$workspaceId, $owner, $candidate];
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
