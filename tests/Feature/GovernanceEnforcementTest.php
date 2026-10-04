<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Platform\Application\AccountPlanService;
use HaHireAI\Modules\Platform\Application\PlatformAdminService;
use HaHireAI\Modules\Platform\Application\PlatformSettings;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * Platform governance (System Owner): support contact (SupportInfo), the
 * block/allow workspace-creation flag, and the read-model enrichment that backs
 * the admin Users screen. Caps/expiry themselves live in AccountPlanTest.
 */
final class GovernanceEnforcementTest extends TestCase
{
    private Connection $connection;
    private PlatformSettings $settings;
    private PlatformAdminService $admin;
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
        $this->settings = new PlatformSettings($this->connection);
        $this->admin = new PlatformAdminService($this->connection);
        $this->accounts = new AccountPlanService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_support_contact_round_trips_and_is_json_safe(): void
    {
        // Defaults: empty contact, never null.
        $blank = $this->settings->support();
        $this->assertSame(['email' => '', 'url' => '', 'phone' => '', 'message' => ''], $blank);

        // Values with slashes/unicode must survive the JSON-encoded settings column.
        $this->settings->set('support.email', 'help@hahire.ai');
        $this->settings->set('support.url', 'https://help.hahire.ai/contact?ref=suspended');
        $this->settings->set('support.phone', '+20 100 000 0000');
        $this->settings->set('support.message', "خدمتك متوقفة مؤقتًا.\nراسلنا للتفعيل.");
        $this->settings->set('platform.name', 'HaHireAI');

        $support = $this->settings->support();
        $this->assertSame('help@hahire.ai', $support['email']);
        $this->assertSame('https://help.hahire.ai/contact?ref=suspended', $support['url']);
        $this->assertSame('+20 100 000 0000', $support['phone']);
        $this->assertStringContainsString('خدمتك', $support['message']);
        $this->assertSame('HaHireAI', $this->settings->get('platform.name'));

        // Update in place (no duplicate row), and the raw column holds valid JSON.
        $this->settings->set('support.email', 'new@hahire.ai');
        $this->assertSame('new@hahire.ai', $this->settings->support()['email']);
        $rows = $this->connection->select('SELECT `value` FROM settings WHERE `key` = ?', ['support.email']);
        $this->assertCount(1, $rows);
        json_decode((string) $rows[0]['value'], true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'settings value must be valid JSON');
    }

    public function test_block_flag_toggles_and_protects_system_owners(): void
    {
        $user = $this->user('Jane', 'jane@x.co');
        $owner = $this->user('Root', 'root@x.co', true);

        // A normal account can be blocked, then re-allowed.
        $this->assertTrue($this->admin->setCanCreateWorkspaces($user, false));
        $this->assertFalse($this->accounts->canCreateWorkspace($user)['allowed']);
        $this->assertStringContainsString('not permitted', $this->accounts->canCreateWorkspace($user)['reason']);

        $this->assertTrue($this->admin->setCanCreateWorkspaces($user, true));
        $this->assertTrue($this->accounts->canCreateWorkspace($user)['allowed']);

        // System owners are protected: no row is changed.
        $this->assertFalse($this->admin->setCanCreateWorkspaces($owner, false));
        $row = $this->connection->selectOne('SELECT can_create_workspaces FROM users WHERE id = ?', [$owner]);
        $this->assertSame(1, (int) $row['can_create_workspaces']);
    }

    public function test_users_read_model_carries_governance_fields(): void
    {
        $user = $this->user('Paul', 'paul@x.co');
        $plan = $this->plan('Team', ['workspaces' => 3]);
        $this->accounts->assignPlan($user, $plan);
        $this->workspace($user, 'active');
        $this->workspace($user, 'suspended'); // not counted as active
        $this->admin->setCanCreateWorkspaces($user, false);

        $rows = array_values(array_filter($this->admin->users(), static fn (array $u): bool => (string) $u['id'] === $user));
        $this->assertCount(1, $rows);
        $u = $rows[0];

        $this->assertSame(0, (int) $u['can_create_workspaces']);
        $this->assertSame(1, (int) $u['owned_active']);
        $this->assertSame('Team', $u['plan_name']);
        $this->assertSame((string) $plan, (string) $u['plan_id']);
        $limits = is_array($u['plan_limits']) ? $u['plan_limits'] : (json_decode((string) $u['plan_limits'], true) ?: []);
        $this->assertSame(3, (int) ($limits['workspaces'] ?? 0));

        // Search narrows the directory.
        $this->assertSame([], array_values(array_filter(
            $this->admin->users(200, 'zzz-no-match'),
            static fn (array $r): bool => (string) $r['id'] === $user,
        )));
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

    private function workspace(string $owner, string $status): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, 'WS', 'ws-' . substr($id, -8), $owner, $status, $now, $now],
        );

        return $id;
    }

    private function user(string $name, string $email, bool $systemOwner = false): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, is_system_owner, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $name, substr($id, -4) . $email, 'x', $systemOwner ? 1 : 0, $now, $now],
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
