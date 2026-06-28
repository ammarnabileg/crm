<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Sprint 4b — a candidate can withdraw their own active application (only). */
final class WithdrawApplicationTest extends TestCase
{
    private Connection $connection;
    private ApplicationService $applications;
    private string $ws = '';
    private string $owner = '';
    private string $jobId = '';

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
        $this->applications = new ApplicationService($this->connection, new JobService($this->connection), new CandidateProfileService($this->connection));

        [$this->ws, $this->owner] = $this->workspace();
        $this->jobId = $this->publishedJob();
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_owner_can_withdraw_active_application_once(): void
    {
        $candidate = $this->user('cand@x.co');
        $appId = $this->applications->apply($this->ws, $this->jobId, $candidate);

        $this->assertTrue($this->applications->withdraw($this->ws, $appId, $candidate));
        $this->assertSame('withdrawn', (string) $this->applications->find($this->ws, $appId)['status']);

        // History trail recorded the transition.
        $history = $this->applications->statusHistory($this->ws, $appId);
        $this->assertNotEmpty($history);
        $this->assertSame('withdrawn', (string) $history[0]['to_status']);

        // Already withdrawn → cannot withdraw again.
        $this->assertFalse($this->applications->withdraw($this->ws, $appId, $candidate));
    }

    public function test_non_owner_cannot_withdraw(): void
    {
        $candidate = $this->user('owner-app@x.co');
        $stranger = $this->user('stranger@x.co');
        $appId = $this->applications->apply($this->ws, $this->jobId, $candidate);

        $this->assertFalse($this->applications->withdraw($this->ws, $appId, $stranger));
        $this->assertSame('applied', (string) $this->applications->find($this->ws, $appId)['status']);
    }

    public function test_terminal_application_cannot_be_withdrawn(): void
    {
        $candidate = $this->user('hired@x.co');
        $appId = $this->applications->apply($this->ws, $this->jobId, $candidate);
        $this->applications->setStatus($this->ws, $appId, 'hired', $this->owner);

        $this->assertFalse($this->applications->withdraw($this->ws, $appId, $candidate));
        $this->assertSame('hired', (string) $this->applications->find($this->ws, $appId)['status']);
    }

    private function publishedJob(): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            "INSERT INTO jobs (id, workspace_id, title, currency, slug, status, public_token, created_by, published_at, created_at, updated_at)
             VALUES (?, ?, ?, 'USD', ?, 'published', ?, ?, ?, ?, ?)",
            [$id, $this->ws, 'Engineer', 'slug-' . substr($id, -6), substr($id, -16), $this->owner, $now, $now, $now],
        );

        return $id;
    }

    /** @return array{0:string,1:string} */
    private function workspace(): array
    {
        $owner = $this->user('owner@x.co');
        $ws = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$ws, 'Acme', 'acme-' . substr($ws, -6), $owner, $now, $now]);

        return [$ws, $owner];
    }

    private function user(string $email): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$id, 'U', substr($id, -5) . '.' . $email, 'x', $now, $now]);

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
