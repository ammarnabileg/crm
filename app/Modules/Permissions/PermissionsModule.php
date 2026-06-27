<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Permissions;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Permissions\Presentation\RolesController;

final class PermissionsModule implements Module
{
    public function name(): string
    {
        return 'Permissions';
    }

    public function dependencies(): array
    {
        return ['Workspaces'];
    }

    public function register(Container $container): void
    {
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/roles', [RolesController::class, 'index']);
        $router->post('/roles', [RolesController::class, 'create']);
    }
}
