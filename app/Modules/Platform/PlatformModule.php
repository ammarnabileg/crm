<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\PaymentSettings;
use HaHireAI\Core\Contracts\SupportInfo;
use HaHireAI\Core\Contracts\WorkspaceAllowance;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Platform\Application\AccountPlanService;
use HaHireAI\Modules\Platform\Application\PlatformSettings;
use HaHireAI\Modules\Platform\Presentation\AccountPlanController;
use HaHireAI\Modules\Platform\Presentation\AdminController;
use HaHireAI\Modules\Platform\Presentation\ProfileController;
use HaHireAI\Modules\Platform\Presentation\PlatformPlansController;
use HaHireAI\Modules\Platform\Presentation\PlatformSettingsController;

/**
 * Platform administration (System Owner context): cross-workspace management
 * screens. Read-models live in PlatformAdminService (docs/PERMISSION_MODEL.md §6).
 */
final class PlatformModule implements Module
{
    public function name(): string
    {
        return 'Platform';
    }

    public function dependencies(): array
    {
        return ['Workspaces'];
    }

    public function register(Container $container): void
    {
        // Account governance surface other modules enforce against (ARCHITECTURE.md §4).
        $container->singleton(WorkspaceAllowance::class, static fn (Container $c): WorkspaceAllowance => $c->make(AccountPlanService::class));
        // Support contact shown on suspended workspaces.
        $container->singleton(SupportInfo::class, static fn (Container $c): SupportInfo => $c->make(PlatformSettings::class));
        // Platform payment switch (Billing reads this to decide free mode).
        $container->singleton(PaymentSettings::class, static fn (Container $c): PaymentSettings => $c->make(PlatformSettings::class));
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/all-workspaces', [AdminController::class, 'workspaces']);
        $router->post('/all-workspaces/{id}/suspend', [AdminController::class, 'suspendWorkspace']);
        $router->post('/all-workspaces/{id}/resume', [AdminController::class, 'resumeWorkspace']);
        $router->post('/all-workspaces/{id}/archive', [AdminController::class, 'archiveWorkspace']);
        $router->post('/all-workspaces/{id}/restore', [AdminController::class, 'restoreWorkspace']);
        $router->get('/users', [AdminController::class, 'users']);
        $router->post('/users/{id}/activate', [AdminController::class, 'activateUser']);
        $router->post('/users/{id}/deactivate', [AdminController::class, 'deactivateUser']);
        $router->post('/users/{id}/workspace-creation', [AdminController::class, 'toggleWorkspaceCreation']);
        $router->post('/users/{id}/plan', [AdminController::class, 'assignPlan']);
        $router->post('/users/{id}/grant-months', [AdminController::class, 'grantMonths']);
        $router->get('/subscriptions', [AdminController::class, 'subscriptions']);
        $router->get('/plans', [PlatformPlansController::class, 'index']);
        $router->post('/plans', [PlatformPlansController::class, 'create']);
        $router->post('/plans/{id}/edit', [PlatformPlansController::class, 'update']);
        $router->post('/plans/{id}/delete', [PlatformPlansController::class, 'delete']);
        $router->get('/platform-settings', [PlatformSettingsController::class, 'index']);
        $router->post('/platform-settings', [PlatformSettingsController::class, 'update']);
        $router->get('/ai-providers', [AdminController::class, 'ai']);
        $router->get('/payments', [AdminController::class, 'payments']);
        $router->get('/audit-log', [AdminController::class, 'audit']);
        // Self-service account pages — user-facing, not /overview.
        $router->get('/account/plan', [AccountPlanController::class, 'index']);
        $router->post('/account/plan', [AccountPlanController::class, 'choose']);
        $router->get('/account/profile', [ProfileController::class, 'index']);
        $router->post('/account/profile', [ProfileController::class, 'update']);
    }
}
