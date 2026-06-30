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
use HaHireAI\Modules\Recruitment\Application\InterviewRoomService;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** The conversational AI interview room — turn-by-turn, resumable, auto-completing. */
final class InterviewRoomTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private ApplicationService $applications;
    private InterviewService $interviews;
    private InterviewRoomService $room;

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
        $assessments = new AssessmentService($this->connection, $ai);
        $this->interviews = new InterviewService($this->connection, $ai);
        $this->room = new InterviewRoomService($this->connection, $ai, $assessments);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_begin_greets_and_asks_the_first_question(): void
    {
        [$ws, $iv] = $this->scheduled();
        $state = $this->room->begin($ws, $iv);

        $this->assertCount(2, $state['messages']);            // greeting + Q1
        $this->assertSame('ai', $state['messages'][0]['role']);
        $this->assertSame(0, $state['asked']);                // progress counts ANSWERED questions; none yet
        $this->assertFalse($state['done']);
        $this->assertGreaterThan(0, $state['seconds_remaining']);
    }

    public function test_begin_is_idempotent_resume_does_not_re_greet(): void
    {
        [$ws, $iv] = $this->scheduled();
        $this->room->begin($ws, $iv);
        $state = $this->room->begin($ws, $iv);            // resume

        $this->assertCount(2, $state['messages']);            // not doubled
    }

    public function test_answering_advances_through_questions(): void
    {
        [$ws, $iv] = $this->scheduled();
        $this->room->begin($ws, $iv);

        $state = $this->room->answer($ws, $iv, 'My first answer.');
        $this->assertSame(1, $state['asked']);                // one question answered
        $this->assertFalse($state['done']);
        // greeting + Q1 + answer + Q2 = 4
        $this->assertCount(4, $state['messages']);
    }

    public function test_interview_completes_after_the_question_budget(): void
    {
        [$ws, $iv] = $this->scheduled();
        $this->room->begin($ws, $iv);

        $state = ['done' => false];
        for ($i = 0; $i < InterviewRoomService::MAX_QUESTIONS + 2 && ! $state['done']; $i++) {
            $state = $this->room->answer($ws, $iv, 'Answer ' . $i);
        }

        $this->assertTrue($state['done']);
        $row = $this->interviews->find($ws, $iv);
        $this->assertSame('completed', $row['status']);
        $this->assertNotEmpty($row['transcript']);
        $this->assertStringContainsString('Candidate:', (string) $row['transcript']);

        // The assessment was produced from the transcript.
        $assessment = $this->connection->selectOne('SELECT * FROM candidate_assessments WHERE interview_id = ?', [$iv]);
        $this->assertNotNull($assessment);
        $this->assertNotNull($row['score']);
    }

    public function test_answering_a_completed_interview_is_a_noop(): void
    {
        [$ws, $iv] = $this->scheduled();
        $this->room->begin($ws, $iv);
        $this->room->finalize($ws, $iv);

        $before = $this->room->state($ws, $iv);
        $after = $this->room->answer($ws, $iv, 'too late');
        $this->assertTrue($after['done']);
        $this->assertCount(count($before['messages']), $after['messages']);
    }

    /** @return array{0:string,1:string} workspace id, interview id */
    private function scheduled(): array
    {
        $owner = $this->user('owner@x.co');
        $cand = $this->user('cand@x.co');
        $ws = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$ws, 'Acme', 'acme-' . substr($ws, -6), $owner, $now, $now],
        );
        $jobId = $this->jobs->create($ws, $owner, 'Senior Engineer');
        $this->jobs->publish($ws, $jobId);
        $appId = $this->applications->apply($ws, $jobId, $cand);
        $iv = $this->interviews->schedule($ws, $appId, 'ai', ['mode' => 'text', 'created_by' => $owner]);

        return [$ws, $iv];
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
