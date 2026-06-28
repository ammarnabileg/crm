<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Installer;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Installer\Application\Installer;
use HaHireAI\Modules\Installer\Presentation\InstallerController;
use HaHireAI\Modules\Permissions\Application\PermissionSeeder;
use HaHireAI\Modules\Users\Application\UserRegistrar;

final class InstallerModule implements Module
{
    public function name(): string
    {
        return 'Installer';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->singleton(Installer::class, static fn (Container $c): Installer => new Installer(
            $c->make(MigrationRunner::class),
            $c->make(PermissionSeeder::class),
            $c->make(UserRegistrar::class),
            $c->make(EventDispatcher::class),
            storage_path('installed.lock'),
            base_path('database/migrations'),
        ));
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/install', [InstallerController::class, 'show']);
        $router->post('/install', [InstallerController::class, 'run']);
    }
}
