<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Billing\Application\InvoiceService;
use HaHireAI\Modules\Billing\Application\PlanComposer;
use HaHireAI\Modules\Billing\Application\PricingCatalog;
use HaHireAI\Modules\Billing\Application\SeatCounter;
use HaHireAI\Modules\Billing\Application\WalletService;
use HaHireAI\Modules\Billing\Application\WorkspacePlanService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * Mid-term plan changes settle only the remaining term, pro-rata
 * (docs/WALLET_AND_BILLING.md §14) on live MySQL 8. Seat price = $9 (900¢);
 * automation feature = $20 (2000¢). A 31-day term (2026-03-01 → 04-01) changed
 * on 2026-03-16 has 16/31 of the term left.
 *
 * Reuses RecordingDispatcher from WalletBillingTest (same test namespace).
 */
final class PlanChangeProrationTest extends TestCase
{
    private Connection $connection;
    private WalletService $wallet;
    private PricingCatalog $pricing;
    private WorkspacePlanService $plans;
    private SeatCounter $seats;
    private PlanComposer $composer;
    private RecordingDispatcher $events;

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

        $this->wallet = new WalletService($this->connection);
        $this->pricing = new PricingCatalog($this->connection);
        $this->pricing->seedDefaults();
        $this->plans = new WorkspacePlanService($this->connection);
        $this->seats = new SeatCounter($this->connection);
        $this->events = new RecordingDispatcher();
        $this->composer = new PlanComposer(
            $this->wallet,
            $this->pricing,
            $this->plans,
            $this->seats,
            new InvoiceService($this->connection),
            $this->events,
        );
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_mid_term_upgrade_charges_only_the_prorated_difference(): void
    {
        [$ws] = $this->workspaceWithOwner();
        $this->member($ws, $this->user()); // 1 billable seat
        $this->wallet->credit($ws, 10000, 'fawaterak');

        // Compose 1 seat, no features, on 2026-03-01 -> 900/month, balance 9100.
        $this->composer->compose($ws, 1, [], null, $this->at('2026-03-01'));
        $this->assertSame(9100, $this->wallet->balance($ws));

        // Upgrade to 1 seat + automation (2900/month) on 2026-03-16 (16/31 left).
        // Prorated charge = round((2900-900) * 16/31) = round(1032.26) = 1032.
        $this->composer->changePlan($ws, 1, ['automation'], null, $this->at('2026-03-16'));

        $this->assertSame(9100 - 1032, $this->wallet->balance($ws));
        $plan = $this->plans->find($ws);
        $this->assertNotNull($plan);
        $this->assertSame(2900, (int) $plan['monthly_cost_cents']); // new full monthly for next renewal
        $this->assertSame(1, (int) $plan['seats_paid']);
        $this->assertSame(['automation'], $this->plans->activeFeatureKeys($ws));
        $this->assertContains('plan.changed', $this->events->names());
    }

    public function test_mid_term_downgrade_credits_the_prorated_difference_back(): void
    {
        [$ws] = $this->workspaceWithOwner();
        $this->member($ws, $this->user()); // 1 billable seat
        $this->wallet->credit($ws, 10000, 'fawaterak');

        // Compose 1 seat + automation on 2026-03-01 -> 2900/month, balance 7100.
        $this->composer->compose($ws, 1, ['automation'], null, $this->at('2026-03-01'));
        $this->assertSame(7100, $this->wallet->balance($ws));

        // Downgrade to 1 seat, no features (900/month) on 2026-03-16 (16/31 left).
        // Prorated credit = round((2900-900) * 16/31) = 1032 back to the wallet.
        $this->composer->changePlan($ws, 1, [], null, $this->at('2026-03-16'));

        $this->assertSame(7100 + 1032, $this->wallet->balance($ws));
        $plan = $this->plans->find($ws);
        $this->assertNotNull($plan);
        $this->assertSame(900, (int) $plan['monthly_cost_cents']);
        $this->assertSame([], $this->plans->activeFeatureKeys($ws));
    }

    public function test_change_requires_an_active_plan(): void
    {
        [$ws] = $this->workspaceWithOwner();

        $this->expectException(\HaHireAI\Modules\Billing\Application\Exceptions\BillingException::class);
        $this->composer->changePlan($ws, 1, [], null, $this->at('2026-03-16'));
    }

    // --- helpers ---------------------------------------------------------

    private function at(string $date): int
    {
        return (int) strtotime($date . ' 00:00:00 UTC');
    }

    private function user(): string
    {
        $id = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, 'U', strtolower($id) . '@e.test', 'x', gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')],
        );

        return $id;
    }

    /** @return array{0:string,1:string} [workspaceId, ownerUserId] */
    private function workspaceWithOwner(): array
    {
        $owner = $this->user();
        $ws = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$ws, 'Acme', 'acme-' . strtolower($ws), $owner, 'active', $now, $now],
        );
        $this->member($ws, $owner);

        return [$ws, $owner];
    }

    private function member(string $workspaceId, string $userId): void
    {
        $this->connection->statement(
            'INSERT INTO memberships (id, workspace_id, user_id, status, joined_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $userId, 'active', gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')],
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
