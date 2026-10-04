<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Recruitment\Application\OfferService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * The offer negotiation loop: company ⇄ candidate counter-offers until one side
 * accepts (with a start date + note) or rejects outright. On live MySQL 8.
 */
final class OfferNegotiationTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private ApplicationService $applications;
    private OfferService $offers;

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
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_candidate_accepts_with_start_date_and_note(): void
    {
        [$ws, $owner, $cand, $appId] = $this->pipeline();
        $offer = $this->offers->create($ws, $appId, 'Engineer', 5000, 'USD', $owner, 'Base offer');
        $this->offers->send($ws, $offer);

        $this->offers->acceptAsCandidate($ws, $offer, $cand, '2026-08-01', 'Can start after notice period.');

        $row = $this->offers->find($ws, $offer);
        $this->assertSame('accepted', $row['status']);
        $this->assertStringContainsString('Accepted: Can start after notice period.', (string) $row['note']);

        $app = $this->connection->selectOne('SELECT status, available_from FROM applications WHERE id = ?', [$appId]);
        $this->assertSame('hired', $app['status']);
        $this->assertStringStartsWith('2026-08-01', (string) $app['available_from']);
        $this->assertNotNull($this->connection->selectOne('SELECT id FROM employees WHERE workspace_id = ? AND user_id = ?', [$ws, $cand]));
    }

    public function test_full_loop_company_countered_by_candidate_then_hr_accepts_proposal(): void
    {
        [$ws, $owner, $cand, $appId] = $this->pipeline();
        // Company sends → candidate counters (proposed) → HR accepts the proposal.
        $company = $this->offers->create($ws, $appId, 'Engineer', 5000, 'USD', $owner);
        $this->offers->send($ws, $company);
        $counter = $this->offers->counter($ws, $appId, $cand, 'Counter', 6000, 'USD', 'Market rate is higher.');
        $this->assertSame('proposed', $this->offers->find($ws, $counter)['status']);

        $this->offers->acceptProposal($ws, $counter, '2026-09-01', 'Deal.');

        $this->assertSame('accepted', $this->offers->find($ws, $counter)['status']);
        $this->assertSame('hired', $this->connection->selectOne('SELECT status FROM applications WHERE id = ?', [$appId])['status']);
    }

    public function test_hr_declines_a_candidate_proposal_ends_the_loop(): void
    {
        [$ws, $owner, $cand, $appId] = $this->pipeline();
        $company = $this->offers->create($ws, $appId, 'Engineer', 5000, 'USD', $owner);
        $this->offers->send($ws, $company);
        $counter = $this->offers->counter($ws, $appId, $cand, 'Counter', 9000, 'USD', 'Need more.');

        $this->offers->declineProposal($ws, $counter);

        $this->assertSame('declined', $this->offers->find($ws, $counter)['status']);
        // Not hired — the loop ended without agreement.
        $this->assertNotSame('hired', $this->connection->selectOne('SELECT status FROM applications WHERE id = ?', [$appId])['status']);
    }

    public function test_hr_counters_back_and_candidate_finally_accepts(): void
    {
        [$ws, $owner, $cand, $appId] = $this->pipeline();
        $company = $this->offers->create($ws, $appId, 'Engineer', 5000, 'USD', $owner);
        $this->offers->send($ws, $company);
        $this->offers->counter($ws, $appId, $cand, 'Counter', 7000, 'USD', 'Please review.');

        // HR counters back with a revised, already-sent company offer…
        $revised = $this->offers->counterFromCompany($ws, $appId, 'Revised offer', 6500, 'USD', $owner, 'Meeting in the middle.');
        $this->assertSame('sent', $this->offers->find($ws, $revised)['status']);

        // …which the candidate accepts, ending the loop as a hire.
        $this->offers->acceptAsCandidate($ws, $revised, $cand, '2026-08-15', null);
        $this->assertSame('accepted', $this->offers->find($ws, $revised)['status']);
        $this->assertSame('hired', $this->connection->selectOne('SELECT status FROM applications WHERE id = ?', [$appId])['status']);
    }

    /** @return array{0:string,1:string,2:string,3:string} [ws, owner, candidate, applicationId] */
    private function pipeline(): array
    {
        [$ws, $owner] = $this->workspace();
        $cand = $this->user('cand@x.co');
        $jobId = $this->jobs->create($ws, $owner, 'Engineer');
        $appId = $this->applications->apply($ws, $jobId, $cand);

        return [$ws, $owner, $cand, $appId];
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
