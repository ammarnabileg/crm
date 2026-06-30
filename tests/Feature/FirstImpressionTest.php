<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Contracts\SocialProfileProbe;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Core\Events\Dispatcher;
use HaHireAI\Modules\Recruitment\Application\FirstImpressionReportService;
use HaHireAI\Modules\Recruitment\Application\FirstImpressionService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Recruitment\Application\UserResumeService;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\ResumeAnalysisEngine;
use HaHireAI\Modules\Recruitment\Domain\Resume\ResumeStructurer;
use HaHireAI\Modules\Recruitment\Infrastructure\Resume\ResumeParserManager;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * The zero-AI First Impression Engine end to end on live MySQL 8: parse → score
 * → persist into the normalised tables → decide → events. No AI provider is ever
 * touched (the engine spends zero credits by construction).
 */
final class FirstImpressionTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private UserResumeService $resumes;
    private FirstImpressionReportService $reports;
    private Dispatcher $events;
    private string $storageDir;

    private const PHP_CV = "Sara Hassan\nSenior Software Engineer\nsara@example.com\n"
        . "Summary\n8+ years of PHP and Laravel.\n"
        . "Experience\nSenior Software Engineer at Acme (2018 - Present)\n"
        . "Skills\nPHP, Laravel, MySQL, Docker, REST\nEducation\nBSc Computer Science\nLanguages\nArabic, English";

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

        $this->storageDir = sys_get_temp_dir() . '/fi-resumes-' . substr(Ulid::generate(), -8);
        $this->jobs = new JobService($this->connection);
        $this->resumes = new UserResumeService($this->connection, new ResumeParserManager(), $this->storageDir);
        $this->reports = new FirstImpressionReportService($this->connection);
        $this->events = new Dispatcher();
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    private function service(?SocialProfileProbe $social = null): FirstImpressionService
    {
        return new FirstImpressionService(
            $this->connection,
            new ResumeStructurer(),
            new ResumeAnalysisEngine(),
            $this->resumes,
            $this->events,
            $social,
        );
    }

    public function test_strong_candidate_passes_and_persists_normalised_report(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $job = $this->fiJob($ws, $owner, 'Senior PHP Engineer', 'PHP,Laravel,MySQL,Docker', 'senior', 5, 65);
        $resumeId = $this->resumes->store($candidate, $this->cvFile(self::PHP_CV), 'sara.txt', 'text/plain', false);
        $appId = $this->application($ws, (string) $job['id'], $candidate);

        $out = $this->service()->run($ws, $job, $appId, $candidate, $resumeId, [], '');

        $this->assertTrue($out['passed']);
        $this->assertSame('passed', $out['decision']);
        $this->assertGreaterThanOrEqual(65, $out['overall']);

        $full = $this->reports->full($ws, $out['report_id']);
        $this->assertNotNull($full);
        $this->assertSame(1, (int) $full['report']['passed']);
        $this->assertNotNull($full['analysis']);
        $this->assertSame(100, (int) $full['analysis']['skill_match']);
        $matched = array_column($full['details']['skill_matched'] ?? [], 'label');
        $this->assertContains('PHP', $matched);
        $this->assertContains('Laravel', $matched);
        // No social was provided → strictly neutral, nothing stored.
        $this->assertNull($full['social']);
        $this->assertNull($full['report']['social_score']);
    }

    public function test_mismatched_candidate_is_filtered_before_ai(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $job = $this->fiJob($ws, $owner, 'Senior Java Engineer', 'Java,Spring,Kotlin,Kafka', 'lead', 8, 65);
        $resumeId = $this->resumes->store($candidate, $this->cvFile(self::PHP_CV), 'sara.txt', 'text/plain', false);

        $out = $this->service()->run($ws, $job, $this->application($ws, (string) $job['id'], $candidate), $candidate, $resumeId, [], '');

        $this->assertFalse($out['passed']);
        $this->assertSame('filtered', $out['decision']);
        $full = $this->reports->full($ws, $out['report_id']);
        $missing = array_column($full['details']['skill_missing'] ?? [], 'label');
        $this->assertContains('Java', $missing);
    }

    public function test_relevant_social_boost_is_persisted(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $job = $this->fiJob($ws, $owner, 'Senior PHP Engineer', 'PHP,Laravel,MySQL', 'senior', 5, 65);
        $resumeId = $this->resumes->store($candidate, $this->cvFile(self::PHP_CV), 'sara.txt', 'text/plain', false);

        $probe = new class implements SocialProfileProbe {
            public function probe(array $urls): array
            {
                return [[
                    'url' => 'https://github.com/sarah', 'platform' => 'github', 'reachable' => true, 'fetched' => true,
                    'footprint_strength' => 80, 'relevance_text' => 'PHP Laravel toolkit', 'skills' => ['PHP', 'Laravel'],
                    'signals' => [['key' => 'public_repos', 'string_value' => null, 'numeric_value' => 32]],
                    'summary' => 'GitHub: 32 repos', 'error' => null,
                ]];
            }
        };

        $out = $this->service($probe)->run($ws, $job, $this->application($ws, (string) $job['id'], $candidate), $candidate, $resumeId, ['https://github.com/sarah'], '');

        $full = $this->reports->full($ws, $out['report_id']);
        $this->assertNotNull($full['social']);
        $this->assertNotNull($full['report']['social_score']);
        $this->assertGreaterThan(0, (int) $full['report']['social_boost']);
        $this->assertCount(1, $full['snapshots']);
        $this->assertSame('github', $full['snapshots'][0]['platform']);
        $this->assertNotEmpty($full['snapshots'][0]['signals']);
    }

    public function test_override_flags_report_and_marks_passed(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $job = $this->fiJob($ws, $owner, 'Senior Java Engineer', 'Java,Spring', 'lead', 8, 90);
        $resumeId = $this->resumes->store($candidate, $this->cvFile(self::PHP_CV), 'sara.txt', 'text/plain', false);
        $svc = $this->service();
        $out = $svc->run($ws, $job, $this->application($ws, (string) $job['id'], $candidate), $candidate, $resumeId, [], '');
        $this->assertFalse($out['passed']);

        $svc->override($ws, $out['report_id'], $owner);

        $report = $this->reports->find($ws, $out['report_id']);
        $this->assertSame(1, (int) $report['overridden']);
        $this->assertSame(1, (int) $report['passed']);
        $this->assertSame('passed', (string) $report['decision']);
    }

    public function test_events_are_published(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $job = $this->fiJob($ws, $owner, 'Senior PHP Engineer', 'PHP,Laravel,MySQL', 'senior', 5, 65);
        $resumeId = $this->resumes->store($candidate, $this->cvFile(self::PHP_CV), 'sara.txt', 'text/plain', false);

        $seen = [];
        foreach (['first_impression.completed', 'first_impression.passed', 'first_impression.failed'] as $e) {
            $this->events->listen($e, static function () use (&$seen, $e): void {
                $seen[] = $e;
            });
        }

        $this->service()->run($ws, $job, $this->application($ws, (string) $job['id'], $candidate), $candidate, $resumeId, [], '');

        $this->assertContains('first_impression.completed', $seen);
        $this->assertContains('first_impression.passed', $seen);
        $this->assertNotContains('first_impression.failed', $seen);
    }

    public function test_cv_library_is_global_and_caches_text(): void
    {
        [, , $candidate] = $this->workspace();
        $resumeId = $this->resumes->store($candidate, $this->cvFile(self::PHP_CV), 'sara.txt', 'text/plain', false);

        $this->assertCount(1, $this->resumes->list($candidate));
        $text = $this->resumes->text($candidate, $resumeId);
        $this->assertStringContainsString('Laravel', $text['text']);
        // A different user cannot see it (per-user scoping).
        $this->assertNull($this->resumes->find(Ulid::generate(), $resumeId));
    }

    // --- helpers ----------------------------------------------------------

    /** @return array{0:string,1:string,2:string} [workspaceId, ownerId, candidateId] */
    private function workspace(): array
    {
        $owner = $this->user('Owner', 'owner-' . substr(Ulid::generate(), -6) . '@x.co');
        $candidate = $this->user('Sara', 'sara-' . substr(Ulid::generate(), -6) . '@x.co');
        $ws = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$ws, 'Acme', 'acme-' . substr($ws, -6), $owner, $now, $now],
        );

        return [$ws, $owner, $candidate];
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

    /**
     * A real application row — production always creates the application before
     * the gate runs (CandidatePortalController/PublicJobController), so the report
     * FK to `applications` is satisfied. The tests mirror that ordering.
     */
    private function application(string $ws, string $jobId, string $candidate): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO applications (id, workspace_id, job_id, user_id, status, applied_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $ws, $jobId, $candidate, 'applied', $now, $now, $now],
        );

        return $id;
    }

    /** @return array<string,mixed> a published job row with the First Impression filter on. */
    private function fiJob(string $ws, string $owner, string $title, string $skills, string $seniority, int $expMin, int $minFi): array
    {
        $jobId = $this->jobs->create($ws, $owner, $title, null, null, null, ['seniority' => $seniority]);
        $this->jobs->publish($ws, $jobId);
        $this->jobs->update($ws, $jobId, [
            'first_impression_enabled' => 1,
            'min_first_impression_score' => $minFi,
            'required_skills' => $skills,
            'screening_keywords' => strtolower(str_replace(',', ',', $skills)),
            'experience_min' => $expMin,
        ]);

        return $this->jobs->find($ws, $jobId) ?? [];
    }

    private function cvFile(string $text): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cv') . '.txt';
        file_put_contents($path, $text);

        return $path;
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
