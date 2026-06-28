<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\SupportInfo;
use HaHireAI\Core\Contracts\WorkspaceAllowance;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Platform\Application\AccountPlanService;
use HaHireAI\Modules\Platform\Application\PlatformSettings;
use HaHireAI\Modules\Platform\Presentation\AdminController;
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
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/admin/workspaces', [AdminController::class, 'workspaces']);
        $router->post('/admin/workspaces/{id}/suspend', [AdminController::class, 'suspendWorkspace']);
        $router->post('/admin/workspaces/{id}/resume', [AdminController::class, 'resumeWorkspace']);
        $router->post('/admin/workspaces/{id}/archive', [AdminController::class, 'archiveWorkspace']);
        $router->post('/admin/workspaces/{id}/restore', [AdminController::class, 'restoreWorkspace']);
        $router->get('/admin/users', [AdminController::class, 'users']);
        $router->post('/admin/users/{id}/activate', [AdminController::class, 'activateUser']);
        $router->post('/admin/users/{id}/deactivate', [AdminController::class, 'deactivateUser']);
        $router->post('/admin/users/{id}/workspace-creation', [AdminController::class, 'toggleWorkspaceCreation']);
        $router->post('/admin/users/{id}/plan', [AdminController::class, 'assignPlan']);
        $router->post('/admin/users/{id}/grant-months', [AdminController::class, 'grantMonths']);
        $router->get('/admin/subscriptions', [AdminController::class, 'subscriptions']);
        $router->get('/admin/plans', [PlatformPlansController::class, 'index']);
        $router->post('/admin/plans', [PlatformPlansController::class, 'create']);
        $router->post('/admin/plans/{id}/edit', [PlatformPlansController::class, 'update']);
        $router->post('/admin/plans/{id}/delete', [PlatformPlansController::class, 'delete']);
        $router->get('/admin/settings', [PlatformSettingsController::class, 'index']);
        $router->post('/admin/settings', [PlatformSettingsController::class, 'update']);
        $router->get('/admin/ai', [AdminController::class, 'ai']);
        $router->get('/admin/audit', [AdminController::class, 'audit']);
    }
}
