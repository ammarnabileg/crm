<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
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
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/admin/workspaces', [AdminController::class, 'workspaces']);
        $router->get('/admin/users', [AdminController::class, 'users']);
        $router->get('/admin/subscriptions', [AdminController::class, 'subscriptions']);
        $router->get('/admin/audit', [AdminController::class, 'audit']);
    }
}
