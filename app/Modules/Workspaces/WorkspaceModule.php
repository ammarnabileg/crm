<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Workspaces\Presentation\DashboardController;
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
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/dashboard', [DashboardController::class, 'index']);
        $router->get('/workspaces/create', [WorkspaceController::class, 'showCreate']);
        $router->post('/workspaces', [WorkspaceController::class, 'create']);
        $router->post('/workspaces/{id}/switch', [WorkspaceController::class, 'switch']);
        $router->get('/settings', [SettingsController::class, 'index']);
        $router->post('/settings', [SettingsController::class, 'update']);
    }
}
