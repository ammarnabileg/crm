<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\InterviewInvitationService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Tokenized interview invitation links — expiry + single-use, on live MySQL 8. */
final class InterviewInvitationTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private InterviewInvitationService $invitations;

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
        $this->invitations = new InterviewInvitationService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_valid_link_resolves_and_is_single_use(): void
    {
        [$ws, $owner] = $this->workspace();
        $jobId = $this->jobs->create($ws, $owner, 'PHP Engineer');
        $invite = $this->invitations->create($ws, $jobId, null, 'sara@x.co', $owner);

        $this->assertSame('valid', $this->invitations->resolve($invite['token'])['state']);

        // Single-use: once completed, it can't be used again.
        $this->invitations->complete($invite['token']);
        $this->assertSame('completed', $this->invitations->resolve($invite['token'])['state']);
    }

    public function test_unknown_token_is_invalid(): void
    {
        $this->assertSame('invalid', $this->invitations->resolve('nope-' . bin2hex(random_bytes(4)))['state']);
    }

    public function test_link_expires_after_14_days(): void
    {
        [$ws, $owner] = $this->workspace();
        $jobId = $this->jobs->create($ws, $owner, 'PHP Engineer');
        $t0 = (int) strtotime('2026-01-01 00:00:00 UTC');
        $invite = $this->invitations->create($ws, $jobId, null, null, $owner, $t0);

        // Still valid on day 13, expired on day 15.
        $this->assertSame('valid', $this->invitations->resolve($invite['token'], $t0 + 13 * 86400)['state']);
        $this->assertSame('expired', $this->invitations->resolve($invite['token'], $t0 + 15 * 86400)['state']);
    }

    public function test_invitations_are_listed_per_job_and_isolated(): void
    {
        [$wsA, $ownerA] = $this->workspace();
        $jobId = $this->jobs->create($wsA, $ownerA, 'PHP Engineer');
        $this->invitations->create($wsA, $jobId, null, null, $ownerA);

        $this->assertCount(1, $this->invitations->listForJob($wsA, $jobId));

        [$wsB] = $this->workspace('b@x.co');
        $this->assertCount(0, $this->invitations->listForJob($wsB, $jobId)); // isolated per workspace
    }

    /** @return array{0:string,1:string} */
    private function workspace(string $ownerEmail = 'owner@x.co'): array
    {
        $ownerId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$ownerId, 'Owner', $ownerEmail . '.' . substr($ownerId, -4), 'x', $now, $now],
        );
        $workspaceId = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $ownerId, $now, $now],
        );

        return [$workspaceId, $ownerId];
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
