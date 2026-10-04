<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Billing\Application\BillingService;
use HaHireAI\Modules\Billing\Application\InvoiceService;
use HaHireAI\Modules\Billing\Application\PlanService;
use HaHireAI\Modules\Billing\Application\SubscriptionLifecycle;
use HaHireAI\Modules\Billing\Application\SubscriptionService;
use HaHireAI\Modules\Billing\Contracts\PaymentGateway;
use HaHireAI\Modules\Billing\Domain\PaymentResult;
use HaHireAI\Modules\Billing\Infrastructure\ManualPaymentGateway;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Phase 14 — time-driven subscription transitions on live MySQL 8. */
final class SubscriptionLifecycleTest extends TestCase
{
    private Connection $connection;
    private PlanService $plans;
    private SubscriptionService $subscriptions;

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
        $this->plans->seedDefaults();
        $this->subscriptions = new SubscriptionService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_trial_converts_to_active_and_charges_on_expiry(): void
    {
        [$billing, $lifecycle] = $this->stack($this->connectedGateway());
        $ws = $this->workspace();
        $pro = (string) $this->plans->findByCode('pro')['id'];

        $billing->subscribe($ws, $pro, $this->at('2026-01-01'));        // 14-day trial
        $result = $lifecycle->tick($this->at('2026-01-20'));             // after the trial

        $this->assertSame(1, $result['processed']);
        $this->assertSame('active', $this->subscriptions->find($ws)['status']);
        $this->assertCount(1, (new InvoiceService($this->connection))->listForWorkspace($ws));
    }

    public function test_failed_charge_goes_past_due_then_suspends_after_grace(): void
    {
        [$billing, $lifecycle] = $this->stack($this->failingGateway());
        $ws = $this->workspace();
        $pro = (string) $this->plans->findByCode('pro')['id'];

        $billing->subscribe($ws, $pro, $this->at('2026-01-01'));        // trialing

        // Trial ends → charge attempted → fails → past_due with a 7-day grace.
        $lifecycle->tick($this->at('2026-01-20'));
        $sub = $this->subscriptions->find($ws);
        $this->assertSame('past_due', $sub['status']);
        $this->assertNotNull($sub['grace_ends_at']);

        // Grace elapses → suspended.
        $after = $lifecycle->tick($this->at('2026-02-01'));
        $this->assertSame('suspended', $this->subscriptions->find($ws)['status']);
        $this->assertSame([['workspace_id' => $ws, 'from' => 'past_due', 'to' => 'suspended']], $after['transitions']);
    }

    public function test_scheduled_cancellation_takes_effect_at_period_end(): void
    {
        [$billing, $lifecycle] = $this->stack($this->connectedGateway());
        $ws = $this->workspace();
        $free = (string) $this->plans->findByCode('free')['id'];

        $billing->subscribe($ws, $free, $this->at('2026-01-01'));        // active, period ~1 month
        $billing->cancel($ws, $this->at('2026-01-10'));                  // cancel at period end

        $this->assertSame('active', $this->subscriptions->find($ws)['status']);

        $lifecycle->tick($this->at('2026-03-01'));                       // past the period end
        $this->assertSame('canceled', $this->subscriptions->find($ws)['status']);
    }

    public function test_free_period_never_suspends_when_no_gateway_connected(): void
    {
        // Built-in manual gateway = not connected → rolling free period.
        [$billing, $lifecycle] = $this->stack(new ManualPaymentGateway());
        $ws = $this->workspace();
        $billing->subscribe($ws, (string) $this->plans->findByCode('pro')['id'], $this->at('2026-01-01'));

        // Long after any trial window — still trialing (auto-extended), never suspended.
        $lifecycle->tick($this->at('2026-03-15'));

        $sub = $this->subscriptions->find($ws);
        $this->assertSame('trialing', $sub['status']);
        $this->assertNotSame('suspended', $sub['status']);
    }

    /** A connected PSP stand-in (key != 'manual') that always succeeds. */
    private function connectedGateway(): PaymentGateway
    {
        return new class implements PaymentGateway {
            public function key(): string
            {
                return 'test';
            }

            public function charge(int $amountCents, string $currency, string $description, array $metadata = []): PaymentResult
            {
                return PaymentResult::ok('test_' . $amountCents);
            }
        };
    }

    /** @return array{0: BillingService, 1: SubscriptionLifecycle} */
    private function stack(PaymentGateway $gateway): array
    {
        $billing = new BillingService($this->subscriptions, $this->plans, new InvoiceService($this->connection), $gateway);
        $lifecycle = new SubscriptionLifecycle($this->subscriptions, $this->plans, $billing);

        return [$billing, $lifecycle];
    }

    private function failingGateway(): PaymentGateway
    {
        return new class implements PaymentGateway {
            public function key(): string
            {
                return 'failing';
            }

            public function charge(int $amountCents, string $currency, string $description, array $metadata = []): PaymentResult
            {
                return PaymentResult::failed('card declined');
            }
        };
    }

    private function at(string $date): int
    {
        return (int) strtotime($date . ' 00:00:00 UTC');
    }

    private function workspace(): string
    {
        $userId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, 'Owner', 'owner-' . substr($userId, -6) . '@x.co', 'x', $now, $now],
        );

        $workspaceId = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $userId, $now, $now],
        );

        return $workspaceId;
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
