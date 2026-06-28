<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Billing\Application\BillingService;
use HaHireAI\Modules\Billing\Application\Entitlements;
use HaHireAI\Modules\Billing\Application\InvoiceService;
use HaHireAI\Modules\Billing\Application\PaymentAttemptService;
use HaHireAI\Modules\Billing\Application\PlanService;
use HaHireAI\Modules\Billing\Application\SubscriptionService;
use HaHireAI\Modules\Billing\Contracts\PaymentGateway;
use HaHireAI\Modules\Billing\Domain\PaymentResult;
use HaHireAI\Modules\Billing\Infrastructure\ManualPaymentGateway;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Phase 14 — subscriptions, invoicing and entitlements on live MySQL 8. */
final class BillingTest extends TestCase
{
    private Connection $connection;
    private PlanService $plans;
    private SubscriptionService $subscriptions;
    private InvoiceService $invoices;
    private BillingService $billing;
    private Entitlements $entitlements;

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
        $this->invoices = new InvoiceService($this->connection);
        // Charge-path tests use a CONNECTED gateway (key != 'manual'); free-period
        // tests below use the built-in manual gateway (= not connected).
        $this->billing = new BillingService($this->subscriptions, $this->plans, $this->invoices, $this->connectedGateway());
        $this->entitlements = new Entitlements($this->subscriptions);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_subscribing_to_a_plan_with_a_trial_starts_trialing_without_an_invoice(): void
    {
        $ws = $this->workspace();
        $pro = (string) $this->plans->findByCode('pro')['id'];

        $this->billing->subscribe($ws, $pro, $this->at('2026-01-01'));

        $sub = $this->subscriptions->find($ws);
        $this->assertSame('trialing', $sub['status']);
        $this->assertNotNull($sub['trial_ends_at']);
        $this->assertCount(0, $this->invoices->listForWorkspace($ws)); // no charge during trial
    }

    public function test_subscribing_to_free_activates_immediately_without_charge(): void
    {
        $ws = $this->workspace();
        $free = (string) $this->plans->findByCode('free')['id'];

        $this->billing->subscribe($ws, $free, $this->at('2026-01-01'));

        $sub = $this->subscriptions->find($ws);
        $this->assertSame('active', $sub['status']);
        $this->assertNotNull($sub['current_period_end']);
        $this->assertCount(0, $this->invoices->listForWorkspace($ws)); // free = no invoice
    }

    public function test_switching_to_a_paid_plan_charges_and_issues_a_paid_invoice(): void
    {
        $ws = $this->workspace();
        $this->billing->subscribe($ws, (string) $this->plans->findByCode('free')['id'], $this->at('2026-01-01'));

        // Already subscribed (no new trial) → switching activates a paid period.
        $this->billing->changePlan($ws, (string) $this->plans->findByCode('pro')['id'], $this->at('2026-01-02'));

        $sub = $this->subscriptions->find($ws);
        $this->assertSame('active', $sub['status']);

        $invoices = $this->invoices->listForWorkspace($ws);
        $this->assertCount(1, $invoices);
        $this->assertSame(4900, (int) $invoices[0]['amount_cents']);
        $this->assertSame('paid', $invoices[0]['status']);
        $this->assertNotNull($invoices[0]['paid_at']);
    }

    public function test_cancel_defaults_to_end_of_period(): void
    {
        $ws = $this->workspace();
        $this->billing->subscribe($ws, (string) $this->plans->findByCode('free')['id'], $this->at('2026-01-01'));

        $this->billing->cancel($ws, $this->at('2026-01-05'));

        $sub = $this->subscriptions->find($ws);
        $this->assertSame('active', $sub['status']);                 // still usable until period end
        $this->assertSame(1, (int) $sub['cancel_at_period_end']);
    }

    public function test_entitlements_reflect_the_active_plan(): void
    {
        $ws = $this->workspace();
        $this->billing->subscribe($ws, (string) $this->plans->findByCode('pro')['id'], $this->at('2026-01-01'));

        $this->assertTrue($this->entitlements->allows($ws, 'ai'));
        $this->assertTrue($this->entitlements->allows($ws, 'automation'));
        $this->assertFalse($this->entitlements->allows($ws, 'sso'));   // enterprise-only
        $this->assertSame(25, $this->entitlements->limit($ws, 'members'));
        $this->assertTrue($this->entitlements->within($ws, 'members', 24));
        $this->assertFalse($this->entitlements->within($ws, 'members', 25));
        $this->assertSame(['ai', 'automation', 'integrations'], $this->entitlements->gateFeatures($ws));
    }

    public function test_entitlements_are_permissive_without_a_subscription(): void
    {
        $ws = $this->workspace();

        $this->assertTrue($this->entitlements->allows($ws, 'ai'));      // un-gated
        $this->assertSame(-1, $this->entitlements->limit($ws, 'members')); // unlimited
        $this->assertNull($this->entitlements->gateFeatures($ws));      // do not gate the sidebar
        $this->assertTrue($this->entitlements->isUsable($ws));
    }

    public function test_free_plan_gates_premium_features(): void
    {
        $ws = $this->workspace();
        $this->billing->subscribe($ws, (string) $this->plans->findByCode('free')['id'], $this->at('2026-01-01'));

        $this->assertFalse($this->entitlements->allows($ws, 'ai'));
        $this->assertSame([], $this->entitlements->gateFeatures($ws)); // hides AI/Workflows/Developer
        $this->assertTrue($this->entitlements->isUsable($ws));
    }

    public function test_free_period_when_no_payment_gateway_is_connected(): void
    {
        $ws = $this->workspace();
        // The built-in manual gateway = "not connected" → free-for-a-limited-period.
        $free = new BillingService($this->subscriptions, $this->plans, $this->invoices, new ManualPaymentGateway(), 30);
        $this->assertFalse($free->gatewayConnected());

        $free->subscribe($ws, (string) $this->plans->findByCode('pro')['id'], $this->at('2026-01-01'));

        $sub = $this->subscriptions->find($ws);
        $this->assertSame('trialing', $sub['status']);             // granted free
        $this->assertNotNull($sub['trial_ends_at']);
        $this->assertCount(0, $this->invoices->listForWorkspace($ws)); // never charged
        // Full plan features unlocked at no cost, and the workspace is usable.
        $this->assertTrue($this->entitlements->allows($ws, 'ai'));
        $this->assertTrue($this->entitlements->isUsable($ws));
    }

    public function test_owner_payment_switch_off_forces_free_mode_even_with_a_connected_gateway(): void
    {
        $ws = $this->workspace();
        $pro = (string) $this->plans->findByCode('pro')['id'];

        // A real PSP is wired, but the System Owner switched payments OFF.
        $paymentsOff = new class implements \HaHireAI\Core\Contracts\PaymentSettings {
            public function paymentsEnabled(): bool
            {
                return false;
            }
        };
        $billing = new BillingService($this->subscriptions, $this->plans, $this->invoices, $this->connectedGateway(), 30, $paymentsOff);

        $this->assertTrue($billing->gatewayConnected());  // gateway is connected
        $this->assertFalse($billing->paymentsEnabled());  // but payments are off
        $this->assertTrue($billing->freeMode());

        $billing->subscribe($ws, $pro, $this->at('2026-01-01'));

        $sub = $this->subscriptions->find($ws);
        $this->assertSame('trialing', $sub['status']);                  // granted free
        $this->assertCount(0, $this->invoices->listForWorkspace($ws));  // never charged

        // Flipping payments back on restores charging for the same gateway.
        $paymentsOn = new class implements \HaHireAI\Core\Contracts\PaymentSettings {
            public function paymentsEnabled(): bool
            {
                return true;
            }
        };
        $billingOn = new BillingService($this->subscriptions, $this->plans, $this->invoices, $this->connectedGateway(), 30, $paymentsOn);
        $this->assertFalse($billingOn->freeMode());
    }

    public function test_charge_attempts_are_recorded_for_the_report(): void
    {
        $attempts = new PaymentAttemptService($this->connection);
        $paidPlanId = $this->plans->create([
            'code' => 'paid-test', 'name' => 'Paid', 'price_cents' => 4900, 'currency' => 'USD',
            'interval' => 'month', 'trial_days' => 0, 'features' => [], 'is_public' => true, 'sort' => 0, 'max_workspaces' => 1,
        ]);
        $plan = $this->plans->find($paidPlanId);

        // Failing connected gateway → a 'failed' attempt with the gateway code.
        $declining = new class implements PaymentGateway {
            public function key(): string
            {
                return 'stripe';
            }

            public function charge(int $amountCents, string $currency, string $description, array $metadata = []): PaymentResult
            {
                return PaymentResult::failed('Your card was declined.', 'card_declined');
            }
        };
        $wsA = $this->workspace();
        $subA = $this->subscriptions->place($wsA, $paidPlanId, 'active');
        $billingFail = new BillingService($this->subscriptions, $this->plans, $this->invoices, $declining, 30, null, $attempts);
        $this->assertFalse($billingFail->activatePaidPeriod($wsA, $subA, $plan));

        $failed = $attempts->recent(10, 'failed');
        $this->assertNotSame([], $failed);
        $this->assertSame('failed', $failed[0]['status']);
        $this->assertSame('card_declined', $failed[0]['error_code']);
        $this->assertSame('stripe', $failed[0]['provider']);

        // Successful connected gateway → a 'success' attempt with a reference.
        $wsB = $this->workspace();
        $subB = $this->subscriptions->place($wsB, $paidPlanId, 'active');
        $billingOk = new BillingService($this->subscriptions, $this->plans, $this->invoices, $this->connectedGateway(), 30, null, $attempts);
        $this->assertTrue($billingOk->activatePaidPeriod($wsB, $subB, $plan));

        $success = $attempts->recent(10, 'success');
        $this->assertNotSame([], $success);
        $this->assertSame('success', $success[0]['status']);
        $this->assertNotEmpty($success[0]['reference']);

        $stats = $attempts->stats();
        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['success']);
        $this->assertSame(1, $stats['failed']);
        $this->assertSame(4900, $stats['charged_cents']);
    }

    private function at(string $date): int
    {
        return (int) strtotime($date . ' 00:00:00 UTC');
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
