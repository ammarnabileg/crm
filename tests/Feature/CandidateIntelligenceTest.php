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
use HaHireAI\Modules\Recruitment\Application\AssessmentService;
use HaHireAI\Modules\Recruitment\Application\CandidateHealthService;
use HaHireAI\Modules\Recruitment\Application\CandidateIntelligenceService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** The zero-AI 360° Candidate Intelligence brief, assembled from prior analysis. */
final class CandidateIntelligenceTest extends TestCase
{
    private Connection $connection;
    private CandidateIntelligenceService $intelligence;
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

        $registry = new ProviderRegistry();
        $registry->register(new EchoProvider());
        $prompts = new PromptEngine($this->connection);
        $prompts->seedDefaults();
        $ai = new AiEngine($this->connection, $registry, $prompts, new AiSettingsService($this->connection, new Encrypter('base64:' . base64_encode(str_repeat('k', 32)))));

        $this->profiles = new CandidateProfileService($this->connection);
        $this->intelligence = new CandidateIntelligenceService(
            $this->connection,
            new AssessmentService($this->connection, $ai),
            $this->profiles,
            new CandidateHealthService($this->connection),
        );
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_empty_candidate_has_no_data(): void
    {
        [$ws, , $candidate] = $this->workspace();
        $iq = $this->intelligence->forCandidate($ws, $candidate);
        $this->assertFalse($iq['has_data']);
    }

    public function test_assembles_all_sections_from_prior_analysis(): void
    {
        [$ws, $owner, $candidate] = $this->workspace();

        $this->assessment($ws, $candidate, [
            'fit_score' => 86,
            'recommendation' => 'strong',
            'summary' => 'Seasoned backend engineer with strong ownership.',
            'strengths' => ['Led a payments rewrite', 'Deep PHP expertise'],
            'weaknesses' => ['Limited frontend exposure'],
            'red_flags' => [['label' => 'Short tenure at last role', 'severity' => 'low']],
            'skills' => ['php' => ['score' => 88], 'react' => ['score' => 45]],
            'behavior' => ['summary' => 'Conscientious, collaborative (DISC: C/S).'],
        ]);
        $this->profiles->saveDetails($ws, $candidate, [
            'skills' => ['PHP', 'MySQL'],
            'expected_salary' => 100000,
            'seniority' => 'Senior',
        ]);
        // A matching open job (skills overlap PHP) the candidate hasn't applied to.
        $this->job($ws, $owner, 'Senior PHP Engineer', 'PHP, MySQL, Redis');

        $iq = $this->intelligence->forCandidate($ws, $candidate);

        $this->assertTrue($iq['has_data']);
        $this->assertSame('Seasoned backend engineer with strong ownership.', $iq['executive_summary']);
        $this->assertContains('Led a payments rewrite', $iq['strengths']);
        $this->assertContains('Limited frontend exposure', $iq['weaknesses']);
        $this->assertSame(['Short tenure at last role (low)'], $iq['risks']);
        $this->assertStringContainsString('DISC', $iq['culture_fit']);
        $this->assertNotSame('', $iq['leadership']);
        $this->assertContains('php', $iq['technical_depth']['top_skills']);
        // React scored 45 (<60) → flagged as a live-verify focus area.
        $this->assertNotEmpty(array_filter($iq['interview_focus_areas'], static fn (string $f): bool => str_contains(strtolower($f), 'react')));
        $this->assertNotEmpty($iq['recommended_jobs']);
        $this->assertSame('Senior PHP Engineer', $iq['recommended_jobs'][0]['title']);
        $this->assertNotNull($iq['recommended_salary']);
        $this->assertStringContainsString('candidate expectation', $iq['recommended_salary']['basis']);
        $this->assertSame($iq['probability_of_success']['percent'], (new CandidateHealthService($this->connection))->forCandidate($ws, $candidate)['score']);
    }

    public function test_is_workspace_isolated(): void
    {
        [$wsA, $ownerA, $candidate] = $this->workspace();
        [$wsB] = $this->workspace('b@x.co');
        $this->assessment($wsA, $candidate, ['fit_score' => 80, 'summary' => 'A only']);

        $this->assertTrue($this->intelligence->forCandidate($wsA, $candidate)['has_data']);
        $this->assertFalse($this->intelligence->forCandidate($wsB, $candidate)['has_data']);
    }

    /** @param array<string,mixed> $data */
    private function assessment(string $ws, string $userId, array $data): void
    {
        $this->connection->statement(
            'INSERT INTO candidate_assessments (id, workspace_id, candidate_user_id, source, fit_score, recommendation, summary, strengths, weaknesses, skills, behavior, red_flags, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                Ulid::generate(), $ws, $userId, 'test',
                (int) ($data['fit_score'] ?? 0),
                (string) ($data['recommendation'] ?? 'maybe'),
                (string) ($data['summary'] ?? ''),
                json_encode($data['strengths'] ?? []),
                json_encode($data['weaknesses'] ?? []),
                json_encode($data['skills'] ?? []),
                json_encode($data['behavior'] ?? []),
                json_encode($data['red_flags'] ?? []),
                gmdate('Y-m-d H:i:s'),
            ],
        );
    }

    private function job(string $ws, string $owner, string $title, string $skills): void
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO jobs (id, workspace_id, title, slug, public_token, status, required_skills, seniority, salary_min, salary_max, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $ws, $title, 'j-' . substr($id, -6), 'tok-' . substr($id, -6), 'published', $skills, 'senior', 90000, 120000, $owner, $now, $now],
        );
    }

    /** @return array{0:string,1:string,2:string} */
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
