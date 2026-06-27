<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\App\BillingController;
use App\Core\Request;
use App\Models\Plan;
use App\Services\Billing\BillingService;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * Billing web layer (in-app subscription management) — the page renders end to end
 * (controller -> service -> view -> layout) with real data, the manual subscribe
 * path creates a tenant subscription with no external gateway, and the webhook
 * logs a gateway_events row while remaining inert-but-safe with zero keys.
 *
 * Fixtures are built fresh inside a rolled-back transaction (mirrors MembersWebTest)
 * so the suite never depends on or mutates seeded data: a brand-new workspace gives
 * its creator the Owner role (every tenant permission, including billing.view and
 * billing.manage), and the creator is authenticated via the session so the RBAC
 * gates resolve for real. All FK ids are resolved from real rows (status_id /
 * lookup_id / currencies SAR), never hard-coded.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $workspaceId = 0;
    private int $ownerId = 0;
    private int $monthlyPlanId = 0;
    private int $freePlanId = 0;

    public function setUp(): void
    {
        // Defensive isolation: the runner skips tearDown when a test's assertion
        // fails, which would leave a stale resolved auth user behind and 403 the
        // next test. Reset the auth/access singletons up-front so every test starts
        // from a clean slate regardless of how the previous one ended.
        $this->resetAuthState();

        $owner = \App\Models\User::create([
            'name'           => 'Billing Owner',
            'email'          => 'owner-' . uniqid() . '@billing.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->ownerId = (int) $owner->getKey();

        $workspace = (new WorkspaceService())->create($owner, 'Billing Test Co');
        $this->workspaceId = (int) $workspace->getKey();
        tenant()->setById($this->workspaceId);

        // WorkspaceService auto-provisions a trial subscription on the seeded default
        // plan. Remove it so each test starts from a known-clean billing state (the
        // empty-state path is part of the spec) and our own subscribe() calls are
        // deterministic. Hard delete keeps currentSubscription() (which filters
        // deleted_at) genuinely empty.
        app('db')->table('subscriptions')
            ->where('workspace_id', '=', $this->workspaceId)
            ->delete();

        session()->put((string) config('auth.session_key', 'auth_user_id'), $this->ownerId);

        // A couple of active, public plans for the catalogue. interval_id resolves
        // via lookup_id('billing_interval', ...); plans are NOT tenant-scoped.
        $monthlyInterval = (int) lookup_id('billing_interval', 'monthly');
        $suffix = uniqid();

        $this->monthlyPlanId = (int) Plan::create([
            'name'        => 'Pro Monthly',
            'slug'        => 'pro-monthly-' . $suffix,
            'description' => 'Everything a growing team needs.',
            'price'       => 99.00,
            'currency'    => 'SAR',
            'interval_id' => $monthlyInterval,
            'trial_days'  => 14,
            'features'    => json_encode(['Unlimited jobs', 'Priority support']),
            'is_active'   => 1,
            'is_public'   => 1,
            'sort_order'  => 2,
        ])->getKey();

        $this->freePlanId = (int) Plan::create([
            'name'        => 'Starter',
            'slug'        => 'starter-' . $suffix,
            'description' => 'Get started for free.',
            'price'       => 0.00,
            'currency'    => 'SAR',
            'interval_id' => $monthlyInterval,
            'trial_days'  => 0,
            'features'    => json_encode(['1 workspace']),
            'is_active'   => 1,
            'is_public'   => 1,
            'sort_order'  => 1,
        ])->getKey();
    }

    public function tearDown(): void
    {
        $this->resetAuthState();
    }

    /**
     * Reset the AuthManager resolution + the AccessControl per-request cache on the
     * EXISTING singletons (preserving registered policy gates) and clear the auth
     * session, so session-auth tests re-resolve cleanly — test isolation, no leak.
     */
    private function resetAuthState(): void
    {
        session()->forget((string) config('auth.session_key', 'auth_user_id'));

        $auth = app('auth');
        $r = new \ReflectionObject($auth);
        foreach (['resolved' => false, 'user' => null] as $prop => $value) {
            if ($r->hasProperty($prop)) {
                $p = $r->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue($auth, $value);
            }
        }

        $access = app('access');
        $ra = new \ReflectionObject($access);
        if ($ra->hasProperty('cache')) {
            $p = $ra->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue($access, []);
        }
    }

    private function request(array $query = [], array $body = []): Request
    {
        $method = $body === [] ? 'GET' : 'POST';
        $req = new Request($query, $body, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/'], [], []);
        app()->instance('request', $req);

        return $req;
    }

    public function test_index_renders_with_no_subscription_and_manual_mode(): void
    {
        $res = (new BillingController())->index($this->request());
        $this->assertSame(200, $res->getStatus());
        $content = $res->getContent();

        // Page header + empty-state with no subscription yet.
        $this->assertTrue(str_contains($content, 'Billing'));
        $this->assertTrue(str_contains($content, 'No active subscription'));

        // No online gateway is configured in tests -> the manual-mode notice shows.
        $this->assertFalse((new BillingService())->gatewayConfigured());
        $this->assertTrue(str_contains($content, 'subscriptions are managed manually'));
    }

    public function test_available_plans_are_listed_and_rendered(): void
    {
        $plans = (new BillingService())->availablePlans();
        $ids = array_map(static fn (Plan $p): int => (int) $p->getKey(), $plans);
        $this->assertContains($this->monthlyPlanId, $ids);
        $this->assertContains($this->freePlanId, $ids);

        $content = (new BillingController())->index($this->request())->getContent();
        $this->assertTrue(str_contains($content, 'Pro Monthly'));
        $this->assertTrue(str_contains($content, 'Starter'));
        // The price (formattedPrice) renders for the paid plan.
        $this->assertTrue(str_contains($content, '99.00'));
    }

    public function test_subscribe_creates_tenant_subscription_and_shows_current_plan(): void
    {
        $subscription = (new BillingService())->subscribe($this->monthlyPlanId);

        $this->assertSame($this->monthlyPlanId, (int) $subscription->getAttribute('plan_id'));
        $this->assertSame((int) $subscription->getAttribute('workspace_id'), $this->workspaceId);

        // Paid plan with trial days -> trialing (an active-family status).
        $this->assertSame(
            status_id('subscription_statuses', 'trialing'),
            (int) $subscription->getAttribute('subscription_status_id')
        );
        $this->assertTrue($subscription->isActive());

        // The row is persisted and tenant-scoped.
        $exists = app('db')->table('subscriptions')
            ->where('workspace_id', '=', $this->workspaceId)
            ->where('plan_id', '=', $this->monthlyPlanId)
            ->whereNull('deleted_at')
            ->exists();
        $this->assertTrue($exists);

        // The index now reflects the current plan.
        $content = (new BillingController())->index($this->request())->getContent();
        $this->assertTrue(str_contains($content, 'Current subscription'));
        $this->assertTrue(str_contains($content, 'Pro Monthly'));
        $this->assertTrue(str_contains($content, 'Current plan'));
    }

    public function test_subscribe_to_free_plan_is_active_immediately(): void
    {
        $subscription = (new BillingService())->subscribe($this->freePlanId);

        $this->assertSame(
            status_id('subscription_statuses', 'active'),
            (int) $subscription->getAttribute('subscription_status_id')
        );
        $this->assertSame(0.0, (float) $subscription->getAttribute('amount'));
    }

    public function test_subscribe_switch_updates_the_same_subscription_row(): void
    {
        $billing = new BillingService();
        $first = $billing->subscribe($this->freePlanId);
        $second = $billing->subscribe($this->monthlyPlanId);

        // A plan switch updates in place — same row id, new plan.
        $this->assertSame((int) $first->getKey(), (int) $second->getKey());
        $this->assertSame($this->monthlyPlanId, (int) $second->getAttribute('plan_id'));

        $count = app('db')->table('subscriptions')
            ->where('workspace_id', '=', $this->workspaceId)
            ->whereNull('deleted_at')
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_checkout_url_redirects_to_the_gateway_for_a_paid_plan_when_configured(): void
    {
        $gateway = new class implements \App\Contracts\Billing\CheckoutGateway {
            public array $params = [];
            public function isConfigured(): bool
            {
                return true;
            }
            public function createCheckoutSession(array $params): ?string
            {
                $this->params = $params;

                return 'https://checkout.gateway.test/session/abc123';
            }
        };

        $url = (new BillingService($gateway))->checkoutUrl($this->monthlyPlanId, 'https://app/ok', 'https://app/cancel');

        $this->assertSame('https://checkout.gateway.test/session/abc123', $url);
        // The paid plan's amount was passed in minor units (price * 100).
        $this->assertTrue(isset($gateway->params['line_items[0][price_data][unit_amount]']));
    }

    public function test_checkout_url_is_null_without_a_configured_gateway(): void
    {
        $gateway = new class implements \App\Contracts\Billing\CheckoutGateway {
            public function isConfigured(): bool
            {
                return false;
            }
            public function createCheckoutSession(array $params): ?string
            {
                return 'should-never-be-called';
            }
        };

        // Not configured → null → the controller uses the manual in-app path.
        $this->assertNull((new BillingService($gateway))->checkoutUrl($this->monthlyPlanId, 'https://app/ok', 'https://app/cancel'));
    }

    public function test_checkout_url_is_null_for_a_free_plan_even_when_configured(): void
    {
        $gateway = new class implements \App\Contracts\Billing\CheckoutGateway {
            public function isConfigured(): bool
            {
                return true;
            }
            public function createCheckoutSession(array $params): ?string
            {
                return 'https://checkout.gateway.test/should-not-happen';
            }
        };

        // Free plans never need a checkout round-trip.
        $this->assertNull((new BillingService($gateway))->checkoutUrl($this->freePlanId, 'https://app/ok', 'https://app/cancel'));
    }

    public function test_webhook_with_no_signing_secret_logs_unverified_event_and_returns_200(): void
    {
        $payload = json_encode([
            'id'   => 'evt_test_' . uniqid(),
            'type' => 'checkout.session.completed',
        ], JSON_UNESCAPED_SLASHES);

        // The controller reads php://input first (empty under the test runner) and
        // falls back to this injected raw payload, keeping the webhook testable
        // without real HTTP.
        $res = (new BillingController())->webhook($this->request([], ['payload' => $payload]));
        $this->assertSame(200, $res->getStatus());

        $stripeId = (int) app('db')->table('payment_gateways')->where('key', '=', 'stripe')->value('id');
        $this->assertTrue($stripeId > 0);

        $event = app('db')->table('gateway_events')
            ->where('payment_gateway_id', '=', $stripeId)
            ->where('event_type', '=', 'checkout.session.completed')
            ->orderBy('id', 'desc')
            ->first();
        $this->assertNotNull($event);
        // No signing secret in tests -> accepted but flagged unverified.
        $this->assertSame(0, (int) $event['is_verified']);
        $this->assertTrue(str_contains((string) $event['payload'], 'checkout.session.completed'));
    }

    public function test_webhook_rejects_a_non_json_payload_with_400(): void
    {
        // gateway_events.payload is a JSON column (CHECK valid JSON); a non-JSON body
        // must be rejected up front with a 400 rather than reaching the insert (which
        // would 500 on the constraint). Regression guard for the raw-body handling.
        $before = (int) app('db')->table('gateway_events')->count();

        $res = (new BillingController())->webhook($this->request([], ['payload' => 'not-json']));
        $this->assertSame(400, $res->getStatus());

        // Nothing was written.
        $this->assertSame($before, (int) app('db')->table('gateway_events')->count());
    }
};
