<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces;

use HaHireAI\Core\Contracts\CompanyDirectory;
use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Workspaces\Application\CompanyDirectoryService;
use HaHireAI\Modules\Workspaces\Presentation\DashboardController;
use HaHireAI\Modules\Workspaces\Presentation\MaintenanceController;
use HaHireAI\Modules\Workspaces\Presentation\SettingsController;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceController;

final class WorkspaceModule implements Module
{
    public function name(): string
    {
        return 'Workspaces';
    }

    public function dependencies(): array
    {
        return ['Authentication'];
    }

    public function register(Container $container): void
    {
        // Public company profile (careers page) exposed via the Core contract so
        // Recruitment renders a tenant's brand without touching our tables (§4).
        $container->singleton(CompanyDirectory::class, CompanyDirectoryService::class);
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/dashboard', [DashboardController::class, 'index']);
        $router->get('/my-workspaces', [WorkspaceController::class, 'myWorkspaces']);
        $router->get('/workspaces/create', [WorkspaceController::class, 'showCreate']);
        $router->post('/workspaces', [WorkspaceController::class, 'create']);
        $router->post('/workspaces/{id}/switch', [WorkspaceController::class, 'switch']);
        $router->post('/workspaces/transfer-ownership', [WorkspaceController::class, 'transferOwnership']);
        $router->post('/workspaces/archive', [WorkspaceController::class, 'archiveOwn']);
        $router->post('/workspaces/{id}/deactivate', [WorkspaceController::class, 'deactivateWorkspace']);
        $router->post('/workspaces/{id}/activate', [WorkspaceController::class, 'activateWorkspace']);
        $router->get('/settings', [SettingsController::class, 'index']);
        $router->get('/settings/logo', [SettingsController::class, 'logo']);
        $router->post('/settings', [SettingsController::class, 'update']);
        $router->get('/settings/maintenance', [MaintenanceController::class, 'index']);
        $router->post('/settings/maintenance/enable', [MaintenanceController::class, 'enable']);
        $router->post('/settings/maintenance/disable', [MaintenanceController::class, 'disable']);
    }
}
