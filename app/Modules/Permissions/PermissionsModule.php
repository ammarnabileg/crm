<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Permissions;

use HaHireAI\Core\Contracts\AccessControl;
use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Permissions\Application\Authorizer;
use HaHireAI\Modules\Permissions\Presentation\PlatformRolesController;
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
        // The shared authorization surface other modules depend on (ARCHITECTURE.md §4).
        $container->singleton(AccessControl::class, static fn (Container $c): AccessControl => $c->make(Authorizer::class));
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/roles', [RolesController::class, 'index']);
        $router->post('/roles', [RolesController::class, 'create']);
        $router->get('/roles/{roleId}', [RolesController::class, 'show']);
        $router->post('/roles/{roleId}/edit', [RolesController::class, 'update']);
        $router->post('/roles/{roleId}/clone', [RolesController::class, 'clone']);
        $router->post('/roles/{roleId}/delete', [RolesController::class, 'delete']);

        // Platform-level roles & permissions (System Owner panel).
        $router->get('/permissions', [PlatformRolesController::class, 'index']);
        $router->post('/permissions', [PlatformRolesController::class, 'create']);
        $router->post('/permissions/{id}/edit', [PlatformRolesController::class, 'update']);
        $router->post('/permissions/{id}/delete', [PlatformRolesController::class, 'delete']);
        $router->post('/permissions/{id}/assign', [PlatformRolesController::class, 'assign']);
        $router->post('/permissions/{id}/unassign', [PlatformRolesController::class, 'unassign']);
    }
}
