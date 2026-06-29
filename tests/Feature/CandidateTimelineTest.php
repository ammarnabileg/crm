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
use HaHireAI\Modules\Files\Application\FileService;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\CandidateTimelineService;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** The per-workspace candidate Timeline aggregation, on live MySQL 8. */
final class CandidateTimelineTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private CandidateProfileService $candidates;
    private ApplicationService $applications;
    private InterviewService $interviews;
    private FileService $files;
    private CandidateTimelineService $timeline;
    private string $storageDir;
    private string $tmpSource;

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
        $this->candidates = new CandidateProfileService($this->connection);
        $this->applications = new ApplicationService($this->connection, $this->jobs, $this->candidates);

        $registry = new ProviderRegistry();
        $registry->register(new EchoProvider());
        $prompts = new PromptEngine($this->connection);
        $prompts->seedDefaults();
        $ai = new AiEngine($this->connection, $registry, $prompts, new AiSettingsService($this->connection, new Encrypter('base64:' . base64_encode(str_repeat('k', 32)))));
        $this->interviews = new InterviewService($this->connection, $ai);

        $this->storageDir = sys_get_temp_dir() . '/hahireai-tl-' . substr(Ulid::generate(), -8);
        $this->files = new FileService($this->connection, $this->storageDir);
        $this->timeline = new CandidateTimelineService($this->connection, $this->files);

        $this->tmpSource = (string) tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($this->tmpSource, "%PDF-1.4 cv\n");
    }

    protected function tearDown(): void
    {
        $this->wipe();
        @unlink($this->tmpSource);
        foreach (glob($this->storageDir . '/*/*') ?: [] as $f) {
            @unlink($f);
        }
    }

    public function test_timeline_merges_all_workspace_events_newest_first(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $jobId = $this->jobs->create($ws, $owner, 'PHP Engineer');
        $this->jobs->publish($ws, $jobId);
        $appId = $this->applications->apply($ws, $jobId, $candidate);          // application + stage history
        $profileId = $this->candidates->getOrCreate($ws, $candidate);

        $this->candidates->addNote($ws, $profileId, $owner, 'Strong candidate'); // note
        $this->files->store($ws, $owner, 'candidate_profile', $profileId, $this->tmpSource, 'resume.pdf', null, false); // file
        $iv = $this->interviews->schedule($ws, $appId, 'human', ['created_by' => $owner]);
        $this->interviews->submitEvaluation($ws, $iv, $owner, 80, 'advance', 'good');   // interview

        $events = $this->timeline->timeline($ws, $candidate, $profileId);
        $types = array_values(array_unique(array_map(static fn (array $e): string => $e['type'], $events)));

        $this->assertContains('application', $types);
        $this->assertContains('stage', $types);
        $this->assertContains('note', $types);
        $this->assertContains('file', $types);
        $this->assertContains('interview', $types);

        // Sorted newest-first (descending by timestamp).
        $timestamps = array_map(static fn (array $e): string => $e['at'], $events);
        $sorted = $timestamps;
        rsort($sorted);
        $this->assertSame($sorted, $timestamps);
    }

    public function test_timeline_is_isolated_per_workspace(): void
    {
        [$wsA, $ownerA, $candidate] = $this->workspace();
        $jobId = $this->jobs->create($wsA, $ownerA, 'PHP Engineer');
        $this->jobs->publish($wsA, $jobId);
        $this->applications->apply($wsA, $jobId, $candidate);
        $profileId = $this->candidates->getOrCreate($wsA, $candidate);

        [$wsB] = $this->workspace('b@example.com');
        // Workspace B sees none of A's candidate activity.
        $this->assertSame([], $this->timeline->timeline($wsB, $candidate, $profileId));
        $this->assertNotSame([], $this->timeline->timeline($wsA, $candidate, $profileId));
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
