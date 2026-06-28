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
use HaHireAI\Modules\Billing\Contracts\PaymentGateway;
use HaHireAI\Modules\Billing\Infrastructure\ManualPaymentGateway;
use HaHireAI\Modules\Billing\Presentation\BillingController;

/**
 * SaaS Billing, Subscriptions & Licensing. Money moves only through the
 * PaymentGateway contract (manual/offline by default; Stripe/Moyasar are
 * adapters). Plan entitlements are exposed to decoupled layers via the Core
 * EntitlementResolver contract (docs/BILLING_PLATFORM.md).
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
        // Network-free default gateway; a real PSP adapter rebinds this.
        $container->singleton(PaymentGateway::class, ManualPaymentGateway::class);

        // Override Core's permissive null resolver with the real, plan-aware one.
        $container->singleton(EntitlementResolver::class, EntitlementResolverAdapter::class);
    }

    public function boot(Container $container): void
    {
        // Seed the plan catalog when the platform is installed.
        $container->make(EventDispatcher::class)->listen(
            'platform.installed',
            static function () use ($container): void {
                $container->make(PlanService::class)->seedDefaults();
            },
        );
    }

    public function routes(Router $router): void
    {
        $router->get('/billing', [BillingController::class, 'index']);
        $router->post('/billing/subscribe', [BillingController::class, 'subscribe']);
        $router->post('/billing/cancel', [BillingController::class, 'cancel']);
    }
}
