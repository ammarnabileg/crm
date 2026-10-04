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
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Recruitment\Domain\SkillCatalog;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** AI candidate assessment (11 skills, behaviour, red flags, bands) on MySQL 8. */
final class AssessmentTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private ApplicationService $applications;
    private InterviewService $interviews;
    private AssessmentService $assessments;

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
        $this->assessments = new AssessmentService($this->connection, $ai);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_recommendation_bands(): void
    {
        $this->assertSame('strong', SkillCatalog::band(85));
        $this->assertSame('suitable', SkillCatalog::band(70));
        $this->assertSame('maybe', SkillCatalog::band(55));
        $this->assertSame('unsuitable', SkillCatalog::band(40));
    }

    public function test_assessment_from_interview_scores_all_skills(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $interviewId = $this->aiInterview($ws, $owner, $candidate);

        $assessment = $this->assessments->assessFromInterview($ws, $interviewId, $owner);

        $this->assertGreaterThanOrEqual(0, (int) $assessment['fit_score']);
        $this->assertLessThanOrEqual(100, (int) $assessment['fit_score']);
        $this->assertContains($assessment['recommendation'], ['strong', 'suitable', 'maybe', 'unsuitable']);
        $this->assertCount(11, $assessment['skills']);                 // all weighted skills scored
        foreach (SkillCatalog::keys() as $key) {
            $this->assertArrayHasKey($key, $assessment['skills']);
        }
        $this->assertArrayHasKey('disc', $assessment['behavior']);
        $this->assertSame('echo', $assessment['ai_provider']);

        // It routed through the AI Engine (assess_candidate capability).
        $caps = array_map(static fn (array $r): string => (string) $r['capability'], $this->connection->select('SELECT capability FROM ai_sessions WHERE workspace_id = ?', [$ws]));
        $this->assertContains('assess_candidate', $caps);

        $latest = $this->assessments->latestForCandidate($ws, $candidate);
        $this->assertSame($assessment['id'], $latest['id']);
    }

    public function test_advanced_search_filters_by_score(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $interviewId = $this->aiInterview($ws, $owner, $candidate);
        $assessment = $this->assessments->assessFromInterview($ws, $interviewId, $owner);
        $score = (int) $assessment['fit_score'];

        $this->assertCount(1, $this->assessments->search($ws, ['min_score' => $score]));      // inclusive
        $this->assertCount(0, $this->assessments->search($ws, ['min_score' => $score + 1]));  // above the score
    }

    public function test_assessments_are_isolated_per_workspace(): void
    {
        [$wsA, $ownerA, $candidate] = $this->workspace();
        $interviewId = $this->aiInterview($wsA, $ownerA, $candidate);
        $this->assessments->assessFromInterview($wsA, $interviewId, $ownerA);

        [$wsB] = $this->workspace('b@example.com');
        $this->assertNull($this->assessments->latestForCandidate($wsB, $candidate));
        $this->assertCount(0, $this->assessments->search($wsB, ['min_score' => 0]));
    }

    private function aiInterview(string $ws, string $owner, string $candidate): string
    {
        $jobId = $this->jobs->create($ws, $owner, 'PHP Engineer');
        $this->jobs->publish($ws, $jobId);
        $appId = $this->applications->apply($ws, $jobId, $candidate);
        $id = $this->interviews->schedule($ws, $appId, 'ai', ['created_by' => $owner]);
        $this->interviews->runAi($ws, $id, $owner);   // produces a transcript

        return $id;
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
