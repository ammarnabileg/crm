<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\EntitlementResolver;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Billing\Application\EntitlementResolverAdapter;
use HaHireAI\Modules\Billing\Application\PlanService;
use HaHireAI\Modules\Billing\Application\PricingCatalog;
use HaHireAI\Modules\Billing\Contracts\HostedCheckoutGateway;
use HaHireAI\Modules\Billing\Contracts\PaymentGateway;
use HaHireAI\Modules\Billing\Infrastructure\FawaterakGateway;
use HaHireAI\Modules\Billing\Infrastructure\ManualPaymentGateway;
use HaHireAI\Modules\Billing\Presentation\BillingController;
use HaHireAI\Modules\Billing\Presentation\FawaterakWebhookController;
use HaHireAI\Modules\Billing\Presentation\PricingController;

/**
 * SaaS Billing, Subscriptions & Licensing. The billing unit is the Workspace (a
 * company): each owns a prepaid wallet, a composed monthly plan (seats +
 * features), and pays via the Fawaterak top-up behind the HostedCheckoutGateway
 * contract (docs/WALLET_AND_BILLING.md). Legacy plan/subscription charging stays
 * behind the PaymentGateway contract (ManualPaymentGateway) for compatibility.
 */
final class BillingModule implements Module
{
    public function name(): string
    {
        return 'Billing';
    }

    public function dependencies(): array
    {
        return ['Workspaces'];
    }

    public function register(Container $container): void
    {
        // Network-free default direct-charge gateway (legacy subscriptions).
        $container->singleton(PaymentGateway::class, ManualPaymentGateway::class);

        // Hosted checkout for wallet top-ups: Fawaterak (platform-level secrets
        // from config/env; offline simulate when not configured).
        $container->singleton(HostedCheckoutGateway::class, static fn (): FawaterakGateway => new FawaterakGateway((array) config('fawaterak', [])));

        // Override Core's permissive null resolver with the real, plan-aware one.
        $container->singleton(EntitlementResolver::class, EntitlementResolverAdapter::class);

        // Override Core's permissive seat guard with the real, seat-aware one so
        // Memberships can enforce paid-seat limits (docs/WALLET_AND_BILLING.md §5).
        $container->singleton(
            \HaHireAI\Core\Contracts\WorkspaceSeatGuard::class,
            \HaHireAI\Modules\Billing\Application\WorkspaceSeatGuardAdapter::class,
        );
    }

    public function boot(Container $container): void
    {
        // Seed the plan catalog AND the wallet pricing catalog on install.
        $container->make(EventDispatcher::class)->listen(
            'platform.installed',
            static function () use ($container): void {
                $container->make(PlanService::class)->seedDefaults();
                $container->make(PricingCatalog::class)->seedDefaults();
            },
        );
    }

    public function routes(Router $router): void
    {
        // Workspace wallet & composed plan.
        $router->get('/billing', [BillingController::class, 'index']);
        $router->post('/billing/topup', [BillingController::class, 'topup']);
        $router->get('/billing/topup/simulate/{paymentId}', [BillingController::class, 'topupSimulate']);
        $router->post('/billing/topup/simulate/{paymentId}', [BillingController::class, 'topupSimulate']);
        $router->post('/billing/compose', [BillingController::class, 'compose']);
        $router->post('/billing/seats', [BillingController::class, 'addSeat']);
        $router->post('/billing/addons', [BillingController::class, 'addAddon']);
        $router->post('/billing/auto-renew', [BillingController::class, 'autoRenew']);

        // Public, signature-verified Fawaterak webhook (no auth/CSRF).
        $router->post('/webhooks/fawaterak', [FawaterakWebhookController::class, 'paid']);

        // Platform pricing catalog (System Owner only).
        $router->get('/pricing', [PricingController::class, 'index']);
        $router->post('/pricing', [PricingController::class, 'update']);
    }
}
