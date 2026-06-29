<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Billing\Application\PlanService;
use HaHireAI\Modules\Billing\Application\SubscriptionService;
use HaHireAI\Modules\Platform\Application\PlatformAdminService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Platform-context admin read models (System Owner) on live MySQL 8. */
final class PlatformAdminTest extends TestCase
{
    private Connection $connection;
    private PlatformAdminService $admin;

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
        $this->admin = new PlatformAdminService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_platform_read_models(): void
    {
        $owner = $this->user('Owner', 'owner@x.co', true);    // system owner
        $member = $this->user('Member', 'm@x.co');
        $ws = $this->workspace($owner);
        $this->membership($ws, $owner);
        $this->membership($ws, $member);

        (new PlanService($this->connection))->seedDefaults();
        $plan = (new PlanService($this->connection))->findByCode('pro');
        (new SubscriptionService($this->connection))->place($ws, (string) $plan['id'], 'active');

        $this->connection->statement(
            'INSERT INTO audit_logs (id, workspace_id, actor_user_id, action, created_at) VALUES (?, ?, ?, ?, ?)',
            [Ulid::generate(), $ws, $owner, 'test.event', gmdate('Y-m-d H:i:s')],
        );

        $workspaces = $this->admin->workspaces();
        $this->assertCount(1, $workspaces);
        $this->assertSame('Owner', $workspaces[0]['owner_name']);
        $this->assertSame(2, (int) $workspaces[0]['members']);
        $this->assertSame('Pro', $workspaces[0]['plan_name']);

        $users = $this->admin->users();
        $this->assertCount(2, $users);
        $sysOwners = array_filter($users, static fn (array $u): bool => (int) $u['is_system_owner'] === 1);
        $this->assertCount(1, $sysOwners);

        $this->assertCount(1, $this->admin->subscriptions());
        $this->assertSame('Pro', $this->admin->subscriptions()[0]['plan']);

        $audit = $this->admin->audit();
        $this->assertNotSame([], $audit);
        $this->assertSame('test.event', $audit[0]['action']);
    }

    public function test_ai_overview_surfaces_hints_never_keys(): void
    {
        $owner = $this->user('Owner', 'owner@x.co');
        $ws = $this->workspace($owner);
        $bare = $this->workspace($owner); // a workspace with no AI configured
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->statement(
            'INSERT INTO ai_settings (id, workspace_id, provider, model, use_platform_key, created_at, updated_at) VALUES (?, ?, ?, ?, 0, ?, ?)',
            [Ulid::generate(), $ws, 'openai', 'gpt-4o', $now, $now],
        );
        $this->connection->statement(
            'INSERT INTO ai_keys (id, workspace_id, provider, encrypted_key, key_hint, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $ws, 'openai', 'TOP-SECRET-ENCRYPTED-VALUE', 'sk-…abcd', $now, $now],
        );
        $this->connection->statement(
            'INSERT INTO ai_sessions (id, workspace_id, capability, provider, model, status, input_tokens, output_tokens, cost_cents, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $ws, 'cv_analysis', 'openai', 'gpt-4o', 'completed', 100, 50, 25, $now],
        );

        $overview = $this->admin->aiOverview();
        $this->assertSame(1, $overview['configured']); // only one of the two workspaces has keys
        $this->assertCount(2, $overview['workspaces']);

        $row = null;
        foreach ($overview['workspaces'] as $w) {
            if ((string) $w['id'] === $ws) {
                $row = $w;
            }
        }
        $this->assertNotNull($row);
        $this->assertSame('openai', $row['default_provider']);
        $this->assertStringContainsString('sk-…abcd', (string) $row['keys_configured']);
        $this->assertSame(1, (int) $row['runs']);

        // The encrypted key must NEVER appear anywhere in the overview.
        $this->assertStringNotContainsString('TOP-SECRET-ENCRYPTED-VALUE', json_encode($overview));

        $this->assertNotSame([], $overview['totals']);
        $this->assertSame('openai', $overview['totals'][0]['provider']);
        $this->assertSame(150, (int) $overview['totals'][0]['tokens']);
    }

    private function user(string $name, string $email, bool $systemOwner = false): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, is_system_owner, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $name, $email . '.' . substr($id, -4), 'x', $systemOwner ? 1 : 0, $now, $now],
        );

        return $id;
    }

    private function workspace(string $ownerId): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, 'Acme', 'acme-' . substr($id, -6), $ownerId, 'active', $now, $now],
        );

        return $id;
    }

    private function membership(string $ws, string $userId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO memberships (id, workspace_id, user_id, status, joined_at, last_activity_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $ws, $userId, 'active', $now, $now, $now, $now],
        );
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
