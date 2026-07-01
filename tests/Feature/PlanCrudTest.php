<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Billing\Application\PlanService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Plan CRUD: create/edit/delete with a workspace cap, and deletion blocked while in use. */
final class PlanCrudTest extends TestCase
{
    private Connection $connection;
    private PlanService $plans;

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
        $this->plans = new PlanService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_create_update_carry_workspace_cap_and_features(): void
    {
        $id = $this->plans->create(['name' => 'Growth', 'code' => 'growth', 'max_workspaces' => 3, 'price_cents' => 2900, 'features' => ['ai', 'automation'], 'is_public' => true]);

        $plan = $this->plans->find($id);
        $this->assertSame('Growth', $plan['name']);
        $this->assertSame(3, (int) $plan['limits']['workspaces']);
        $this->assertEqualsCanonicalizing(['ai', 'automation'], $plan['features']);

        $this->plans->update($id, ['name' => 'Growth Plus', 'max_workspaces' => 5, 'features' => ['ai']]);
        $plan = $this->plans->find($id);
        $this->assertSame('Growth Plus', $plan['name']);
        $this->assertSame(5, (int) $plan['limits']['workspaces']);

        // allPlans includes non-public plans.
        $hidden = $this->plans->create(['name' => 'Internal', 'is_public' => false, 'max_workspaces' => 99]);
        $codes = array_map(static fn (array $p): string => (string) $p['id'], $this->plans->allPlans());
        $this->assertContains($hidden, $codes);
    }

    public function test_delete_blocked_while_in_use(): void
    {
        $id = $this->plans->create(['name' => 'Duo', 'max_workspaces' => 2]);
        $this->assertSame(0, $this->plans->usageCount($id));
        $this->assertTrue($this->plans->delete($id)); // unused → deletable

        $id2 = $this->plans->create(['name' => 'Team', 'max_workspaces' => 4]);
        $user = $this->user();
        $this->connection->statement(
            'INSERT INTO account_plans (id, user_id, plan_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $user, $id2, 'active', gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')],
        );
        $this->assertSame(1, $this->plans->usageCount($id2));
        $this->assertFalse($this->plans->delete($id2)); // in use → refused
        $this->assertNotNull($this->plans->find($id2));
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
