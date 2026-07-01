<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Permissions\Application\PermissionSeeder;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Modules\Permissions\Infrastructure\PermissionRepository;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\Exceptions\ApplicationException;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Recruitment\Application\OfferService;
use HaHireAI\Modules\Users\Application\PasswordHasher;
use HaHireAI\Modules\Users\Infrastructure\UserRepository;
use HaHireAI\Modules\Workspaces\Application\WorkspaceCreator;
use PHPUnit\Framework\TestCase;

/** Phase 10 — the recruitment hiring loop against a live MySQL 8 database. */
final class RecruitmentTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private CandidateProfileService $candidates;
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

        $schema = new SchemaBuilder($this->connection);
        $this->wipe();
        (new MigrationRunner($this->connection, $schema))->run(dirname(__DIR__, 2) . '/database/migrations');
        (new PermissionSeeder(new PermissionRepository($this->connection)))->seed();

        $this->jobs = new JobService($this->connection);
        $this->candidates = new CandidateProfileService($this->connection);
        $this->applications = new ApplicationService($this->connection, $this->jobs, $this->candidates);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_full_hiring_loop(): void
    {
        [$workspaceId, $ownerId] = $this->workspace('Acme');
        $applicantId = $this->user('Sara', 'sara@example.com');

        // Create + publish a job → default stages are created.
        $jobId = $this->jobs->create($workspaceId, $ownerId, 'PHP Engineer', 'Build things');
        $this->jobs->publish($workspaceId, $jobId);
        $stages = $this->jobs->stagesForJob($jobId);
        $this->assertCount(6, $stages);
        $this->assertSame('Applied', $stages[0]['name']);

        // Apply → application + candidate profile + initial stage + history.
        $appId = $this->applications->apply($workspaceId, $jobId, $applicantId, 'Excited!');
        $app = $this->applications->find($workspaceId, $appId);
        $this->assertSame('applied', $app['status']);
        $this->assertSame((string) $stages[0]['id'], (string) $app['current_stage_id']);
        $this->assertNotNull($this->candidates->profile($workspaceId, $applicantId));
        $this->assertCount(1, $this->history($appId));

        // A user applies to a job at most once.
        $this->expectExceptionDoesNotLeak(fn () => $this->applications->apply($workspaceId, $jobId, $applicantId));

        // Pipeline grouping + stage move.
        $byStage = $this->applications->byStage($workspaceId, $jobId);
        $this->assertArrayHasKey((string) $stages[0]['id'], $byStage);

        $screening = $this->stageNamed($stages, 'Screening');
        $this->applications->moveStage($workspaceId, $appId, $screening, $ownerId);
        $moved = $this->applications->find($workspaceId, $appId);
        $this->assertSame($screening, (string) $moved['current_stage_id']);
        $this->assertSame('in_pipeline', $moved['status']);
        $this->assertCount(2, $this->history($appId));

        // Candidate profile: notes + tags (workspace-scoped view).
        $profileId = $this->candidates->getOrCreate($workspaceId, $applicantId);
        $this->candidates->addNote($workspaceId, $profileId, $ownerId, 'Strong PHP background');
        $this->candidates->addTag($workspaceId, $profileId, 'strong-fit');
        $this->assertSame('Strong PHP background', $this->candidates->notes($workspaceId, $profileId)[0]['body']);
        $this->assertContains('strong-fit', $this->candidates->tags($profileId));
        $this->assertSame('PHP Engineer', $this->candidates->applications($workspaceId, $applicantId)[0]['job_title']);
    }

    public function test_offer_acceptance_hires_candidate_and_creates_employee(): void
    {
        [$workspaceId, $ownerId] = $this->workspace('Acme');
        $applicantId = $this->user('Sara', 'sara@example.com');

        $jobId = $this->jobs->create($workspaceId, $ownerId, 'PHP Engineer');
        $this->jobs->publish($workspaceId, $jobId);
        $appId = $this->applications->apply($workspaceId, $jobId, $applicantId);

        $offers = new OfferService($this->connection);
        $offerId = $offers->create($workspaceId, $appId, 'Senior PHP', 5000, 'USD', $ownerId);
        $offers->send($workspaceId, $offerId);
        $employeeId = $offers->accept($workspaceId, $offerId);

        // Application is Hired, offer Accepted, and the Employee context exists.
        $this->assertSame('hired', $this->applications->find($workspaceId, $appId)['status']);
        $this->assertSame('accepted', $offers->find($workspaceId, $offerId)['status']);

        $employee = $this->connection->selectOne('SELECT * FROM employees WHERE id = ?', [$employeeId]);
        $this->assertSame($applicantId, (string) $employee['user_id']);
        $this->assertSame('onboarding', $employee['status']);

        // An already-accepted offer cannot be accepted again.
        $this->expectException(ApplicationException::class);
        $offers->accept($workspaceId, $offerId);
    }

    public function test_candidate_profile_is_isolated_per_workspace(): void
    {
        [$wsA, $ownerA] = $this->workspace('Workspace A');
        [$wsB] = $this->workspace('Workspace B', 'ownerb@example.com');
        $applicantId = $this->user('Sara', 'sara@example.com');

        $jobA = $this->jobs->create($wsA, $ownerA, 'Role A');
        $this->jobs->publish($wsA, $jobA);
        $this->applications->apply($wsA, $jobA, $applicantId);

        // Workspace A sees the candidate; Workspace B does NOT (cross-tenant isolation).
        $this->assertNotNull($this->candidates->profile($wsA, $applicantId));
        $this->assertNull($this->candidates->profile($wsB, $applicantId));
        $this->assertCount(1, $this->candidates->applications($wsA, $applicantId));
        $this->assertCount(0, $this->candidates->applications($wsB, $applicantId));
    }

    /** @return array{0:string,1:string} [workspaceId, ownerId] */
    private function workspace(string $name, string $ownerEmail = 'owner@example.com'): array
    {
        $ownerId = $this->user('Owner', $ownerEmail);
        $creator = new WorkspaceCreator(
            $this->connection,
            new MembershipService($this->connection),
            new RoleService($this->connection, new PermissionRepository($this->connection)),
        );
        $result = $creator->create($ownerId, $name);

        return [$result['workspace_id'], $ownerId];
    }

    private function user(string $name, string $email): string
    {
        return (new UserRepository($this->connection))->create($name, $email, (new PasswordHasher())->hash('password123'));
    }

    /** @param list<array<string,mixed>> $stages */
    private function stageNamed(array $stages, string $name): string
    {
        foreach ($stages as $stage) {
            if ($stage['name'] === $name) {
                return (string) $stage['id'];
            }
        }

        $this->fail("Stage {$name} not found");
    }

    /** @return list<array<string,mixed>> */
    private function history(string $applicationId): array
    {
        return $this->connection->select('SELECT * FROM application_stage_history WHERE application_id = ?', [$applicationId]);
    }

    private function expectExceptionDoesNotLeak(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected ApplicationException for duplicate apply.');
        } catch (ApplicationException $e) {
            $this->assertStringContainsString('already applied', $e->getMessage());
        }
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
