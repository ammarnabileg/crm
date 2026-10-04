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

/** Interviews (AI + human) — workspace-scoped & advisory, on live MySQL 8. */
final class InterviewTest extends TestCase
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
        $candidates = new CandidateProfileService($this->connection);
        $this->applications = new ApplicationService($this->connection, $this->jobs, $candidates);

        $registry = new ProviderRegistry();
        $registry->register(new EchoProvider());
        $prompts = new PromptEngine($this->connection);
        $prompts->seedDefaults();
        $ai = new AiEngine(
            $this->connection,
            $registry,
            $prompts,
            new AiSettingsService($this->connection, new Encrypter('base64:' . base64_encode(str_repeat('k', 32)))),
        );
        $this->interviews = new InterviewService($this->connection, $ai);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_schedule_then_human_evaluation_scores_the_candidate(): void
    {
        [$ws, $owner, $candidate, $appId] = $this->applied();

        $id = $this->interviews->schedule($ws, $appId, 'human', ['interviewer_user_id' => $owner, 'created_by' => $owner]);
        $this->assertSame('scheduled', $this->interviews->find($ws, $id)['status']);

        $this->interviews->submitEvaluation($ws, $id, $owner, 82, 'advance', 'Strong communicator');

        $interview = $this->interviews->find($ws, $id);
        $this->assertSame('completed', $interview['status']);
        $this->assertSame(82, (int) $interview['score']);
        $this->assertSame('advance', $interview['recommendation']);
        $this->assertSame(82, $this->interviews->averageScore($ws, $candidate));
    }

    public function test_ai_interview_runs_through_the_central_engine(): void
    {
        [$ws, , $candidate, $appId] = $this->applied();

        $id = $this->interviews->schedule($ws, $appId, 'ai');
        $result = $this->interviews->runAi($ws, $id, null);

        $this->assertSame('completed', $result['status']);
        $this->assertSame('ai', $result['type']);
        $this->assertNotSame('', (string) $result['transcript']);
        $this->assertSame('echo', $result['ai_provider']);
        $this->assertGreaterThanOrEqual(0, (int) $result['score']);
        $this->assertLessThanOrEqual(100, (int) $result['score']);
        $this->assertContains($result['recommendation'], ['advance', 'hold', 'reject']);

        // It routed through the AI Engine, leaving an ai_session.
        $session = $this->connection->selectOne('SELECT capability FROM ai_sessions WHERE workspace_id = ?', [$ws]);
        $this->assertSame('ai_interview', $session['capability']);
    }

    public function test_human_evaluation_overrides_the_ai_suggestion(): void
    {
        [$ws, $owner, , $appId] = $this->applied();

        $id = $this->interviews->schedule($ws, $appId, 'ai');
        $this->interviews->runAi($ws, $id, null);           // AI suggests a score…
        $this->interviews->submitEvaluation($ws, $id, $owner, 40, 'reject', 'Not a fit'); // …human overrides

        $interview = $this->interviews->find($ws, $id);
        $this->assertSame(40, (int) $interview['score']);
        $this->assertSame('reject', $interview['recommendation']);
    }

    public function test_average_score_aggregates_completed_interviews(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $app1 = $this->applyToNewJob($ws, $owner, $candidate, 'Backend');
        $app2 = $this->applyToNewJob($ws, $owner, $candidate, 'Frontend');

        $i1 = $this->interviews->schedule($ws, $app1, 'human', ['created_by' => $owner]);
        $i2 = $this->interviews->schedule($ws, $app2, 'human', ['created_by' => $owner]);
        $this->interviews->submitEvaluation($ws, $i1, $owner, 80, 'advance', 'good');
        $this->interviews->submitEvaluation($ws, $i2, $owner, 60, 'hold', 'ok');

        $this->assertSame(70, $this->interviews->averageScore($ws, $candidate)); // (80+60)/2
        $this->assertCount(2, $this->interviews->forCandidate($ws, $candidate));
    }

    public function test_interviews_are_isolated_per_workspace(): void
    {
        [$wsA, $ownerA, $candidate, $appId] = $this->applied();
        $id = $this->interviews->schedule($wsA, $appId, 'human', ['created_by' => $ownerA]);
        $this->interviews->submitEvaluation($wsA, $id, $ownerA, 90, 'advance', 'great');

        [$wsB] = $this->workspace('b@example.com');
        $this->assertCount(0, $this->interviews->forCandidate($wsB, $candidate)); // B sees nothing
        $this->assertNull($this->interviews->averageScore($wsB, $candidate));
        $this->assertNull($this->interviews->find($wsB, $id));                    // cross-workspace = not found
    }

    /** @return array{0:string,1:string,2:string,3:string} [ws, owner, candidate, applicationId] */
    private function applied(): array
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $appId = $this->applyToNewJob($ws, $owner, $candidate, 'PHP Engineer');

        return [$ws, $owner, $candidate, $appId];
    }

    private function applyToNewJob(string $ws, string $owner, string $candidate, string $title): string
    {
        $jobId = $this->jobs->create($ws, $owner, $title);
        $this->jobs->publish($ws, $jobId);

        return $this->applications->apply($ws, $jobId, $candidate);
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
