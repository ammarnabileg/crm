<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Platform\Application\AccountPlanService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Account governance: workspace caps, expiry, bonus months, create-permission. */
final class AccountPlanTest extends TestCase
{
    private Connection $connection;
    private AccountPlanService $accounts;

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
        $this->accounts = new AccountPlanService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_cap_from_plan_and_active_count(): void
    {
        $user = $this->user();
        $this->assertSame(1, $this->accounts->maxWorkspaces($user)); // free tier default

        $plan = $this->plan('Growth', ['workspaces' => 3]);
        $this->accounts->assignPlan($user, $plan);
        $this->assertSame(3, $this->accounts->maxWorkspaces($user));

        $this->workspace($user, 'active');
        $this->workspace($user, 'active');
        $this->workspace($user, 'suspended');
        $this->assertSame(2, $this->accounts->activeWorkspaceCount($user)); // suspended not counted
    }

    public function test_can_create_respects_flag_cap_and_expiry(): void
    {
        $user = $this->user();
        $plan = $this->plan('Duo', ['workspaces' => 2]);
        $this->accounts->assignPlan($user, $plan);

        $this->assertTrue($this->accounts->canCreateWorkspace($user)['allowed']);

        // Cap reached.
        $this->workspace($user, 'active');
        $this->workspace($user, 'active');
        $this->assertFalse($this->accounts->canCreateWorkspace($user)['allowed']);

        // Free a slot.
        $this->workspace($user, 'active'); // 3rd active to exceed, then deactivate below
        $this->connection->statement("UPDATE workspaces SET status = 'suspended' WHERE owner_user_id = ? ORDER BY created_at DESC LIMIT 2", [$user]);
        $this->assertTrue($this->accounts->canCreateWorkspace($user)['allowed']);

        // Blocked flag.
        $this->connection->statement('UPDATE users SET can_create_workspaces = 0 WHERE id = ?', [$user]);
        $this->assertFalse($this->accounts->canCreateWorkspace($user)['allowed']);
    }

    public function test_bonus_months_and_expiry(): void
    {
        $user = $this->user();
        $this->assertFalse($this->accounts->isExpired($user)); // no expiry yet
        $this->assertTrue($this->accounts->isUsable($user));

        $this->accounts->grantMonths($user, 2);
        $this->assertFalse($this->accounts->isExpired($user));
        $this->assertSame(2, (int) $this->accounts->forUser($user)['bonus_months']);

        // Force expiry in the past → expired + unusable.
        $this->connection->statement("UPDATE account_plans SET expires_at = ? WHERE user_id = ?", [gmdate('Y-m-d H:i:s', time() - 86400), $user]);
        $this->assertTrue($this->accounts->isExpired($user));
        $this->assertFalse($this->accounts->isUsable($user));
        $this->assertFalse($this->accounts->canCreateWorkspace($user)['allowed']);

        // Suspend / resume.
        $this->accounts->grantMonths($user, 1); // pushes expiry to the future again
        $this->assertTrue($this->accounts->isUsable($user));
        $this->accounts->setStatus($user, 'suspended');
        $this->assertFalse($this->accounts->isUsable($user));
    }

    public function test_downgrade_archives_excess_keeping_oldest(): void
    {
        $user = $this->user();
        $big = $this->plan('Scale', ['workspaces' => 5]);
        $this->accounts->assignPlan($user, $big);

        // Four active workspaces, distinct creation order (oldest → newest).
        $w1 = $this->workspace($user, 'active', '2026-01-01 00:00:00');
        $w2 = $this->workspace($user, 'active', '2026-02-01 00:00:00');
        $w3 = $this->workspace($user, 'active', '2026-03-01 00:00:00');
        $w4 = $this->workspace($user, 'active', '2026-04-01 00:00:00');
        $this->assertSame(4, $this->accounts->activeWorkspaceCount($user));

        // Downgrade to a 2-workspace plan → the two oldest keep running.
        $small = $this->plan('Duo', ['workspaces' => 2]);
        $this->accounts->assignPlan($user, $small);

        $this->assertSame(2, $this->accounts->activeWorkspaceCount($user));
        $this->assertSame('active', $this->wsStatus($w1));
        $this->assertSame('active', $this->wsStatus($w2));
        $this->assertSame('archived', $this->wsStatus($w3));
        $this->assertSame('archived', $this->wsStatus($w4));
    }

    public function test_can_activate_respects_cap_and_usability(): void
    {
        $user = $this->user();
        $this->accounts->assignPlan($user, $this->plan('Duo', ['workspaces' => 2]));

        $this->workspace($user, 'active');
        $this->assertTrue($this->accounts->canActivateWorkspace($user)['allowed']); // 1/2

        $this->workspace($user, 'active');
        $this->assertFalse($this->accounts->canActivateWorkspace($user)['allowed']); // 2/2, full

        // Block flag does NOT stop re-activation (only creation does).
        $this->connection->statement('UPDATE users SET can_create_workspaces = 0 WHERE id = ?', [$user]);
        $this->connection->statement("UPDATE workspaces SET status = 'archived' WHERE owner_user_id = ? ORDER BY created_at DESC LIMIT 1", [$user]);
        $this->assertTrue($this->accounts->canActivateWorkspace($user)['allowed']); // 1/2 again, flag irrelevant

        // Expired plan blocks activation entirely.
        $this->connection->statement('UPDATE account_plans SET expires_at = ? WHERE user_id = ?', [gmdate('Y-m-d H:i:s', time() - 86400), $user]);
        $this->assertFalse($this->accounts->canActivateWorkspace($user)['allowed']);
    }

    private function wsStatus(string $id): string
    {
        return (string) $this->connection->selectOne('SELECT status FROM workspaces WHERE id = ?', [$id])['status'];
    }

    private function plan(string $name, array $limits): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            "INSERT INTO plans (id, code, name, price_cents, currency, `interval`, trial_days, features, limits, is_public, sort, created_at, updated_at)
             VALUES (?, ?, ?, 0, 'USD', 'month', 0, ?, ?, 1, 0, ?, ?)",
            [$id, 'p-' . substr($id, -6), $name, json_encode([]), json_encode($limits), $now, $now],
        );

        return $id;
    }

    private function workspace(string $owner, string $status, ?string $createdAt = null): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $created = $createdAt ?? $now;
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, 'WS', 'ws-' . substr($id, -8), $owner, $status, $created, $now],
        );

        return $id;
    }

    private function user(): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$id, 'U', 'u' . substr($id, -6) . '@x.co', 'x', $now, $now]);

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
