<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\WorkspaceAllowance;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Platform\Application\AccountPlanService;
use HaHireAI\Modules\Platform\Presentation\AdminController;

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
        $router->get('/admin/subscriptions', [AdminController::class, 'subscriptions']);
        $router->get('/admin/audit', [AdminController::class, 'audit']);
    }
}
