<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\CandidacyService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\Exceptions\ApplicationException;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Recruitment\Application\OfferService;
use HaHireAI\Modules\Users\Infrastructure\UserRepository;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Candidate Portal: candidacy resolution, overview, offers (accept/decline/counter), profile. */
final class CandidatePortalTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private ApplicationService $applications;
    private OfferService $offers;
    private CandidacyService $candidacy;
    private MembershipService $memberships;
    private UserRepository $users;

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
        $this->offers = new OfferService($this->connection);
        $this->candidacy = new CandidacyService($this->connection);
        $this->memberships = new MembershipService($this->connection);
        $this->users = new UserRepository($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_candidacy_lists_only_applied_workspaces(): void
    {
        [$wsA, $ownerA] = $this->workspace();
        [$wsB] = $this->workspace('b@x.co');
        $cand = $this->user('cand@x.co');

        $this->applyTo($wsA, $ownerA, $cand);

        $list = $this->candidacy->workspacesForCandidate($cand);
        $this->assertCount(1, $list);
        $this->assertSame($wsA, $list[0]['id']);
        $this->assertTrue($this->candidacy->isCandidate($wsA, $cand));
        $this->assertFalse($this->candidacy->isCandidate($wsB, $cand));
    }

    public function test_members_are_not_candidates_in_their_own_workspace(): void
    {
        [$ws, $owner] = $this->workspace();
        $staff = $this->user('staff@x.co');
        $this->memberships->create($ws, $staff);          // they hold a role here
        $this->applyTo($ws, $owner, $staff);              // …and somehow also applied

        // A user with a role is staff, never a candidate, in that workspace.
        $this->assertFalse($this->candidacy->isCandidate($ws, $staff));
        $this->assertCount(0, $this->candidacy->workspacesForCandidate($staff));
    }

    public function test_overview_summarizes_applications_offers_and_jobs(): void
    {
        [$ws, $owner] = $this->workspace();
        $cand = $this->user('cand@x.co');
        $appId = $this->applyTo($ws, $owner, $cand);

        $offerId = $this->offers->create($ws, $appId, 'Engineer', 5000, 'USD', $owner);
        $this->offers->send($ws, $offerId);

        $ov = $this->candidacy->overview($ws, $cand);
        $this->assertSame(1, $ov['counts']['applications']);
        $this->assertSame(1, $ov['counts']['offers_pending']);
        $this->assertCount(1, $ov['latest_jobs']);
        $this->assertArrayHasKey('applied', $ov['status_counts']);
    }

    public function test_candidate_can_accept_only_their_own_offer(): void
    {
        [$ws, $owner] = $this->workspace();
        $cand = $this->user('cand@x.co');
        $other = $this->user('other@x.co');
        $appId = $this->applyTo($ws, $owner, $cand);
        $offerId = $this->offers->create($ws, $appId, 'Engineer', 5000, 'USD', $owner);
        $this->offers->send($ws, $offerId);

        $this->expectException(ApplicationException::class);
        $this->offers->acceptAsCandidate($ws, $offerId, $other);   // not their offer
    }

    public function test_candidate_accepts_offer_and_is_hired(): void
    {
        [$ws, $owner] = $this->workspace();
        $cand = $this->user('cand@x.co');
        $appId = $this->applyTo($ws, $owner, $cand);
        $offerId = $this->offers->create($ws, $appId, 'Engineer', 5000, 'USD', $owner);
        $this->offers->send($ws, $offerId);

        $this->offers->acceptAsCandidate($ws, $offerId, $cand);

        $offer = $this->offers->find($ws, $offerId);
        $this->assertSame('accepted', $offer['status']);
        $app = $this->applications->find($ws, $appId);
        $this->assertSame('hired', $app['status']);
    }

    public function test_candidate_counter_offer_is_recorded_as_proposed(): void
    {
        [$ws, $owner] = $this->workspace();
        $cand = $this->user('cand@x.co');
        $appId = $this->applyTo($ws, $owner, $cand);

        $id = $this->offers->counter($ws, $appId, $cand, 'Senior Engineer', 7000, 'USD', 'My market rate is higher.');
        $offer = $this->offers->find($ws, $id);
        $this->assertSame('proposed', $offer['status']);
        $this->assertSame('candidate', $offer['proposed_by']);
        $this->assertSame('My market rate is higher.', $offer['note']);

        // Cannot counter on someone else's application.
        $this->expectException(ApplicationException::class);
        $this->offers->counter($ws, $appId, $this->user('x@x.co'), 'x', 1, 'USD', null);
    }

    public function test_application_detail_is_scoped_to_the_owner(): void
    {
        [$ws, $owner] = $this->workspace();
        $cand = $this->user('cand@x.co');
        $appId = $this->applyTo($ws, $owner, $cand);

        $this->assertNotNull($this->applications->findForCandidate($ws, $appId, $cand));
        $this->assertNull($this->applications->findForCandidate($ws, $appId, $this->user('intruder@x.co')));
    }

    public function test_profile_update_persists_personal_data(): void
    {
        $cand = $this->user('cand@x.co');
        $this->users->updatePersonal($cand, 'Ammar N', '+201234567890', 8, 45000);

        $row = $this->users->find($cand);
        $this->assertSame('Ammar N', $row['name']);
        $this->assertSame('+201234567890', $row['phone']);
        $this->assertSame(8, (int) $row['years_experience']);
        $this->assertSame(45000, (int) $row['target_salary']);
    }

    private function applyTo(string $ws, string $owner, string $candidate): string
    {
        $jobId = $this->jobs->create($ws, $owner, 'Engineer');
        $this->jobs->publish($ws, $jobId);

        return $this->applications->apply($ws, $jobId, $candidate);
    }

    /** @return array{0:string,1:string} */
    private function workspace(string $ownerEmail = 'owner@x.co'): array
    {
        $owner = $this->user($ownerEmail);
        $workspaceId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $owner, $now, $now],
        );

        return [$workspaceId, $owner];
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
