<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\CandidateHealthService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** The unified Candidate Health Score, gathered from already-collected data, on live MySQL 8. */
final class CandidateHealthTest extends TestCase
{
    private Connection $connection;
    private CandidateHealthService $health;
    private CandidateProfileService $profiles;

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
        $this->health = new CandidateHealthService($this->connection);
        $this->profiles = new CandidateProfileService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_no_signals_yields_zero_weak(): void
    {
        [$ws, , $candidate] = $this->workspace();
        $out = $this->health->forCandidate($ws, $candidate);

        $this->assertSame(0, $out['score']);
        $this->assertSame('Weak', $out['band']);
        foreach ($out['components'] as $c) {
            $this->assertFalse($c['contributing']);
        }
    }

    public function test_gathers_and_blends_available_signals(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();
        $job = $this->job($ws, $owner);

        // First Impression sub-scores (job match / resume / social) — already computed.
        $this->fiReport($ws, $candidate, $job, jobMatch: 90, resume: 80, social: 70);
        // AI assessment: fit + per-skill scores.
        $this->assessment($ws, $candidate, 88, ['php' => ['score' => 84], 'sql' => ['score' => 76]]);
        // AI interview score + human rating.
        $this->interview($ws, $candidate, $job, score: 82, rating: 4);
        // Learning + a certification.
        $this->enrollment($ws, $candidate, 60);
        $this->profiles->saveDetails($ws, $candidate, ['certifications' => ['AWS SAA'], 'years_experience' => 6]);

        $out = $this->health->forCandidate($ws, $candidate);

        $this->assertGreaterThan(0, $out['score']);
        $this->assertLessThanOrEqual(100, $out['score']);

        $byKey = [];
        foreach ($out['components'] as $c) {
            $byKey[$c['key']] = $c;
        }
        $this->assertTrue($byKey['job_match']['contributing']);
        $this->assertSame(90, $byKey['job_match']['value']);
        $this->assertTrue($byKey['human_evaluation']['contributing']);
        $this->assertSame(80, $byKey['human_evaluation']['value']); // rating 4 → 80
        $this->assertTrue($byKey['learning']['contributing']);
        $this->assertSame(60, $byKey['learning']['value']);
        $this->assertTrue($byKey['certifications']['contributing']);
        $this->assertSame(60, $byKey['experience']['value']);       // 6 years → 60
    }

    public function test_is_workspace_isolated(): void
    {
        [$wsA, $ownerA, $candidate] = $this->workspace();
        [$wsB] = $this->workspace('b@x.co');
        $job = $this->job($wsA, $ownerA);
        $this->fiReport($wsA, $candidate, $job, jobMatch: 90, resume: 80, social: 70);

        // Workspace B has no data for this candidate → nothing contributes.
        $out = $this->health->forCandidate($wsB, $candidate);
        $this->assertSame(0, $out['score']);
    }

    // ── fixtures ─────────────────────────────────────────────────────────────

    private function fiReport(string $ws, string $userId, string $jobId, int $jobMatch, int $resume, int $social): void
    {
        $this->connection->statement(
            'INSERT INTO first_impression_reports (id, workspace_id, candidate_user_id, job_id, overall_score, core_score, resume_score, job_match_score, social_score, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $ws, $userId, $jobId, 80, 80, $resume, $jobMatch, $social, gmdate('Y-m-d H:i:s')],
        );
    }

    /** @param array<string,array{score:int}> $skills */
    private function assessment(string $ws, string $userId, int $fit, array $skills): void
    {
        $this->connection->statement(
            'INSERT INTO candidate_assessments (id, workspace_id, candidate_user_id, source, fit_score, skills, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $ws, $userId, 'test', $fit, json_encode($skills), gmdate('Y-m-d H:i:s')],
        );
    }

    private function interview(string $ws, string $userId, string $jobId, int $score, int $rating): void
    {
        $appId = Ulid::generate();
        $ivId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO applications (id, workspace_id, job_id, user_id, status, applied_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$appId, $ws, $jobId, $userId, 'applied', $now, $now, $now],
        );
        $this->connection->statement(
            'INSERT INTO interviews (id, workspace_id, application_id, candidate_user_id, job_id, type, status, score, completed_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$ivId, $ws, $appId, $userId, $jobId, 'ai', 'completed', $score, $now, $now, $now],
        );
        $this->connection->statement(
            'INSERT INTO interview_feedback (id, workspace_id, interview_id, rating, comment, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $ws, $ivId, $rating, 'ok', $now],
        );
    }

    private function enrollment(string $ws, string $userId, int $progress): void
    {
        $programId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO learning_programs (id, workspace_id, title, slug, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$programId, $ws, 'Onboarding', 'onboarding-' . substr($programId, -6), 'published', $userId, $now, $now],
        );
        $this->connection->statement(
            'INSERT INTO learning_enrollments (id, workspace_id, program_id, user_id, status, progress_percent, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $ws, $programId, $userId, 'in_progress', $progress, $now, $now],
        );
    }

    private function job(string $workspaceId, string $createdBy): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO jobs (id, workspace_id, title, slug, public_token, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, 'Engineer', 'engineer-' . substr($id, -6), 'tok-' . substr($id, -6), 'published', $createdBy, $now, $now],
        );

        return $id;
    }

    /** @return array{0:string,1:string,2:string} [workspaceId, ownerId, candidateId] */
    private function workspace(string $ownerEmail = 'owner@x.co'): array
    {
        $ownerId = $this->user($ownerEmail);
        $candidateId = $this->user('cand-' . substr(Ulid::generate(), -6) . '@x.co');
        $workspaceId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $ownerId, $now, $now],
        );

        return [$workspaceId, $ownerId, $candidateId];
    }

    private function user(string $email): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, 'User', substr($id, -4) . '.' . $email, 'x', $now, $now],
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
