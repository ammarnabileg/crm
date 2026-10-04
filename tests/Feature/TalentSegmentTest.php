<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\SegmentService;
use HaHireAI\Modules\Recruitment\Application\TalentPoolService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Smart Segments — saved candidate filters evaluated on live MySQL 8, workspace-scoped. */
final class TalentSegmentTest extends TestCase
{
    private Connection $connection;
    private SegmentService $segments;
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
        $this->segments = new SegmentService($this->connection);
        $this->profiles = new CandidateProfileService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_create_segment_with_rules_and_evaluate_and(): void
    {
        [$ws, $owner] = $this->workspace();

        // Ada: React + English + score 90 → should match "React AND English AND score>=85".
        $ada = $this->candidate($ws, 'ada@x.co', ['skills' => ['React', 'PHP'], 'languages' => ['English'], 'availability' => 'Immediately']);
        $this->assess($ws, $ada, 90);
        // Bob: React only, low score → filtered out by the AND segment.
        $bob = $this->candidate($ws, 'bob@x.co', ['skills' => ['React'], 'languages' => ['Arabic']]);
        $this->assess($ws, $bob, 60);

        $segmentId = $this->segments->createSegment($ws, 'React + English + 85', 'all', $owner);
        $this->segments->replaceRules($ws, $segmentId, [
            ['field' => 'skill', 'value' => 'React'],
            ['field' => 'language', 'value' => 'English'],
            ['field' => 'min_score', 'value' => '85'],
        ]);

        $matches = $this->segments->evaluate($ws, $segmentId);

        $this->assertCount(1, $matches);
        $this->assertSame($ada, $matches[0]['user_id']);
        $this->assertContains('Skill: React', $matches[0]['reasons']);
        $this->assertContains('Score ≥ 85', $matches[0]['reasons']);
    }

    public function test_any_match_type_is_a_union(): void
    {
        [$ws, $owner] = $this->workspace();
        $ada = $this->candidate($ws, 'ada@x.co', ['skills' => ['React']]);
        $bob = $this->candidate($ws, 'bob@x.co', ['languages' => ['English']]);
        $this->candidate($ws, 'cid@x.co', ['skills' => ['COBOL']]); // matches neither

        $segmentId = $this->segments->createSegment($ws, 'React OR English', 'any', $owner);
        $this->segments->replaceRules($ws, $segmentId, [
            ['field' => 'skill', 'value' => 'React'],
            ['field' => 'language', 'value' => 'English'],
        ]);

        $ids = array_map(static fn (array $m): string => $m['user_id'], $this->segments->evaluate($ws, $segmentId));
        sort($ids);
        $expected = [$ada, $bob];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_available_and_status_rules(): void
    {
        [$ws, $owner] = $this->workspace();
        $ada = $this->candidate($ws, 'ada@x.co', ['availability' => 'Immediately']);
        $this->candidate($ws, 'bob@x.co', ['availability' => 'unavailable']); // excluded by "Available"

        $segmentId = $this->segments->createSegment($ws, 'Available', 'all', $owner);
        $this->segments->replaceRules($ws, $segmentId, [['field' => 'available', 'value' => '']]);

        $matches = $this->segments->evaluate($ws, $segmentId);
        $this->assertCount(1, $matches);
        $this->assertSame($ada, $matches[0]['user_id']);
        $this->assertContains('Available', $matches[0]['reasons']);
    }

    public function test_last_interview_recency_rule(): void
    {
        [$ws, $owner] = $this->workspace();
        $recent = $this->candidate($ws, 'recent@x.co', ['skills' => ['React']]);
        $stale = $this->candidate($ws, 'stale@x.co', ['skills' => ['React']]);
        $job = $this->job($ws, $owner);
        $this->interview($ws, $recent, $job, gmdate('Y-m-d H:i:s', (int) strtotime('-1 month')));
        $this->interview($ws, $stale, $job, gmdate('Y-m-d H:i:s', (int) strtotime('-2 years')));

        $segmentId = $this->segments->createSegment($ws, 'Interviewed < 6mo', 'all', $owner);
        $this->segments->replaceRules($ws, $segmentId, [['field' => 'last_interview_months', 'value' => '6']]);

        $matches = $this->segments->evaluate($ws, $segmentId);
        $this->assertCount(1, $matches);
        $this->assertSame($recent, $matches[0]['user_id']);
    }

    public function test_segments_and_evaluation_are_isolated_per_workspace(): void
    {
        [$wsA, $ownerA] = $this->workspace();
        [$wsB, $ownerB] = $this->workspace('b@x.co');
        $adaA = $this->candidate($wsA, 'ada@x.co', ['skills' => ['React']]);
        $this->candidate($wsB, 'ada2@x.co', ['skills' => ['React']]); // same skill, other workspace

        $segmentA = $this->segments->createSegment($wsA, 'React', 'all', $ownerA);
        $this->segments->replaceRules($wsA, $segmentA, [['field' => 'skill', 'value' => 'React']]);

        // Workspace B cannot see or evaluate A's segment.
        $this->assertCount(0, $this->segments->listSegments($wsB));
        $this->assertNull($this->segments->findSegment($wsB, $segmentA));
        $this->assertSame([], $this->segments->evaluate($wsB, $segmentA));

        // A's evaluation returns only A's candidate — never B's look-alike.
        $matches = $this->segments->evaluate($wsA, $segmentA);
        $this->assertCount(1, $matches);
        $this->assertSame($adaA, $matches[0]['user_id']);
    }

    public function test_segment_with_no_rules_matches_nobody(): void
    {
        [$ws, $owner] = $this->workspace();
        $this->candidate($ws, 'ada@x.co', ['skills' => ['React']]);
        $segmentId = $this->segments->createSegment($ws, 'Empty', 'all', $owner);

        $this->assertSame([], $this->segments->evaluate($ws, $segmentId));
    }

    public function test_bulk_add_matches_into_a_pool_reuses_talent_pool_service(): void
    {
        [$ws, $owner] = $this->workspace();
        $ada = $this->candidate($ws, 'ada@x.co', ['skills' => ['React']]);
        $segmentId = $this->segments->createSegment($ws, 'React', 'all', $owner);
        $this->segments->replaceRules($ws, $segmentId, [['field' => 'skill', 'value' => 'React']]);

        $pools = new TalentPoolService($this->connection);
        $poolId = $pools->createPool($ws, 'From segment', null, $owner);
        $matchIds = array_map(static fn (array $m): string => $m['user_id'], $this->segments->evaluate($ws, $segmentId));
        $added = $pools->addCandidates($ws, $poolId, $matchIds, $owner);

        $this->assertSame(1, $added);
        $this->assertSame($ada, $pools->members($ws, $poolId)[0]['user_id']);
    }

    // ── fixtures ─────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $details */
    private function candidate(string $workspaceId, string $email, array $details): string
    {
        $userId = $this->user($email);
        // saveDetails() dual-writes candidate_profiles.details + candidate_profile_fields.
        $this->profiles->saveDetails($workspaceId, $userId, $details);

        return $userId;
    }

    private function assess(string $workspaceId, string $userId, int $score): void
    {
        $this->connection->statement(
            'INSERT INTO candidate_assessments (id, workspace_id, candidate_user_id, source, fit_score, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $userId, 'test', $score, gmdate('Y-m-d H:i:s')],
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

    private function interview(string $workspaceId, string $userId, string $jobId, string $completedAt): void
    {
        $applicationId = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO applications (id, workspace_id, job_id, user_id, status, applied_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$applicationId, $workspaceId, $jobId, $userId, 'applied', $completedAt, $completedAt, $completedAt],
        );
        $this->connection->statement(
            'INSERT INTO interviews (id, workspace_id, application_id, candidate_user_id, job_id, type, status, completed_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $applicationId, $userId, $jobId, 'ai', 'completed', $completedAt, $completedAt, $completedAt],
        );
    }

    /** @return array{0:string,1:string} */
    private function workspace(string $ownerEmail = 'owner@x.co'): array
    {
        $ownerId = $this->user($ownerEmail);
        $workspaceId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $ownerId, $now, $now],
        );

        return [$workspaceId, $ownerId];
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
