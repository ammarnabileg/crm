<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Billing\Application\Exceptions\BillingException;
use HaHireAI\Modules\Billing\Application\InvoiceService;
use HaHireAI\Modules\Billing\Application\PlanComposer;
use HaHireAI\Modules\Billing\Application\PricingCatalog;
use HaHireAI\Modules\Billing\Application\SeatCounter;
use HaHireAI\Modules\Billing\Application\TopUpService;
use HaHireAI\Modules\Billing\Application\WalletService;
use HaHireAI\Modules\Billing\Application\WorkspacePlanLifecycle;
use HaHireAI\Modules\Billing\Application\WorkspacePlanService;
use HaHireAI\Modules\Billing\Application\WorkspaceSeatGuardAdapter;
use HaHireAI\Modules\Billing\Infrastructure\FawaterakGateway;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * Workspace wallet billing (docs/WALLET_AND_BILLING.md) on live MySQL 8:
 * wallet ledger, seats, plan composition, add-ons, renewal/lock, top-up webhook
 * idempotency and per-workspace isolation. Seat price = $9 (900 cents).
 */
final class WalletBillingTest extends TestCase
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

    public function test_wallet_credit_and_debit_keep_an_accurate_ledger(): void
    {
        $ws = $this->workspace();

        $this->assertSame(0, $this->wallet->balance($ws));
        $this->assertSame(5000, $this->wallet->credit($ws, 5000, 'fawaterak', null, 'Top up'));
        $this->assertSame(3500, $this->wallet->debit($ws, 1500, 'plan'));
        $this->assertSame(3500, $this->wallet->balance($ws));

        $tx = $this->wallet->transactions($ws);
        $this->assertCount(2, $tx);
        $this->assertSame(3500, (int) $tx[0]['balance_after_cents']);
    }

    public function test_debit_beyond_balance_is_rejected(): void
    {
        $ws = $this->workspace();
        $this->wallet->credit($ws, 1000, 'fawaterak');

        $this->expectException(BillingException::class);
        $this->wallet->debit($ws, 1500, 'plan');
    }

    public function test_owner_is_free_and_extra_staff_are_billable_seats(): void
    {
        [$ws, $owner] = $this->workspaceWithOwner();
        $this->assertSame(0, $this->seats->billableSeats($ws));

        $this->member($ws, $this->user());           // a second staff member
        $this->member($ws, $this->user());           // a third
        $this->assertSame(2, $this->seats->billableSeats($ws));
    }

    public function test_compose_requires_funds_then_activates_and_gates_features(): void
    {
        [$ws] = $this->workspaceWithOwner();
        $this->member($ws, $this->user());           // 1 billable seat

        // Insufficient balance -> rejected.
        try {
            $this->composer->compose($ws, 1, ['automation'], null, $this->at('2026-03-01'));
            $this->fail('Expected BillingException for insufficient funds.');
        } catch (BillingException) {
        }

        $this->wallet->credit($ws, 10000, 'fawaterak');
        $this->composer->compose($ws, 1, ['automation'], null, $this->at('2026-03-01'));

        $plan = $this->plans->find($ws);
        $this->assertNotNull($plan);
        $this->assertSame('active', (string) $plan['status']);
        // seat (900) + automation (2000) = 2900 debited.
        $this->assertSame(7100, $this->wallet->balance($ws));
        $this->assertSame(['automation'], $this->plans->activeFeatureKeys($ws));
        $this->assertContains('plan.activated', $this->events->names());
    }

    public function test_addon_is_charged_now_and_expires_with_the_plan(): void
    {
        [$ws] = $this->workspaceWithOwner();
        $this->wallet->credit($ws, 10000, 'fawaterak');
        $this->composer->compose($ws, 0, [], null, $this->at('2026-03-01'));

        $this->composer->addAddonFeature($ws, 'integrations', null, $this->at('2026-03-15'));
        $this->assertContains('integrations', $this->plans->activeFeatureKeys($ws, $this->sql('2026-03-20')));

        // After the plan period ends, the add-on no longer counts.
        $this->assertNotContains('integrations', $this->plans->activeFeatureKeys($ws, $this->sql('2026-05-01')));
        $this->assertContains('addon.activated', $this->events->names());
    }

    public function test_renewal_charges_the_wallet_or_locks_when_short(): void
    {
        [$ws] = $this->workspaceWithOwner();
        $this->member($ws, $this->user()); // 1 seat -> 900/month
        $this->wallet->credit($ws, 2000, 'fawaterak');
        $this->composer->compose($ws, 1, [], null, $this->at('2026-03-01')); // -900 -> 1100 left

        $lifecycle = new WorkspacePlanLifecycle($this->connection, $this->composer);

        // First renewal funded (1100 >= 900) -> still active, 200 left.
        $r1 = $lifecycle->tick($this->at('2026-04-02'));
        $this->assertSame(1, $r1['renewed']);
        $this->assertSame(200, $this->wallet->balance($ws));
        $this->assertFalse($this->plans->isLocked($ws));

        // Second renewal short (200 < 900) -> locked.
        $r2 = $lifecycle->tick($this->at('2026-05-03'));
        $this->assertSame(1, $r2['locked']);
        $this->assertTrue($this->plans->isLocked($ws));
        $this->assertContains('plan.locked', $this->events->names());
    }

    public function test_topup_webhook_credits_once_and_is_idempotent(): void
    {
        $ws = $this->workspace();
        $gateway = new FawaterakGateway(['webhook_hash' => 'secret']);
        $topup = new TopUpService($this->connection, $gateway, $this->wallet, $this->events);

        // Record a pending payment with a known provider invoice id.
        $invoiceId = 'inv_123';
        $this->connection->statement(
            'INSERT INTO fawaterak_payments (id, workspace_id, provider_invoice_id, amount_cents, currency, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $ws, $invoiceId, 4000, 'USD', 'pending', gmdate('Y-m-d H:i:s')],
        );

        $payload = ['invoice_id' => $invoiceId, 'amount_cents' => 4000, 'status' => 'paid'];
        $sig = hash_hmac('sha256', $invoiceId . '|4000', 'secret');

        $this->assertTrue($topup->handleWebhook($payload, $sig));
        $this->assertSame(4000, $this->wallet->balance($ws));

        // Replay -> no double credit.
        $this->assertFalse($topup->handleWebhook($payload, $sig));
        $this->assertSame(4000, $this->wallet->balance($ws));

        // Wrong signature -> rejected.
        $this->assertFalse($topup->handleWebhook($payload, 'bad'));
    }

    public function test_seat_guard_enforces_paid_seats(): void
    {
        $guard = new WorkspaceSeatGuardAdapter($this->seats, $this->plans);
        [$ws] = $this->workspaceWithOwner();
        $this->member($ws, $this->user()); // 1 billable staff member

        // No composed plan yet -> permissive (pre-billing / legacy).
        $this->assertTrue($guard->canAddBillableMember($ws));

        // Fund and compose exactly 1 seat -> no room for a 2nd billable member.
        $this->wallet->credit($ws, 10000, 'fawaterak');
        $this->composer->compose($ws, 1, [], null, $this->at('2026-03-01'));
        $this->assertFalse($guard->canAddBillableMember($ws));
        $this->assertNotSame('', $guard->denyReason($ws));

        // Re-compose with 2 seats -> room for one more.
        $this->composer->compose($ws, 2, [], null, $this->at('2026-03-01'));
        $this->assertTrue($guard->canAddBillableMember($ws));
    }

    public function test_wallets_are_isolated_per_workspace(): void
    {
        $a = $this->workspace();
        $b = $this->workspace();

        $this->wallet->credit($a, 5000, 'fawaterak');
        $this->assertSame(5000, $this->wallet->balance($a));
        $this->assertSame(0, $this->wallet->balance($b));
    }

    // --- helpers ---------------------------------------------------------

    private function at(string $date): int
    {
        return (int) strtotime($date . ' 00:00:00 UTC');
    }

    private function sql(string $date): string
    {
        return gmdate('Y-m-d H:i:s', $this->at($date));
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

    /** A bare workspace (its own owner) and its id. */
    private function workspace(): string
    {
        return $this->workspaceWithOwner()[0];
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
        $this->member($ws, $owner); // the owner is an active member but never billable

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

/** Records dispatched event names so tests can assert the billing event stream. */
final class RecordingDispatcher implements EventDispatcher
{
    /** @var list<string> */
    private array $names = [];

    public function listen(string $event, callable $listener): void
    {
    }

    public function hasListeners(string $event): bool
    {
        return false;
    }

    public function dispatch(string $event, mixed $payload = null): array
    {
        $this->names[] = $event;

        return [];
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->names;
    }
}
