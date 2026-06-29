<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Authentication;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\UserDirectory;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Authentication\Presentation\AuthController;
use HaHireAI\Modules\Users\Infrastructure\UserRepository;

final class AuthenticationModule implements Module
{
    public function name(): string
    {
        return 'Authentication';
    }

    public function dependencies(): array
    {
        return ['Installer'];
    }

    public function register(Container $container): void
    {
        // Publish the User identity as a shared-service contract so other modules
        // depend on the contract, never the concrete repository (ARCHITECTURE.md §4).
        $container->singleton(UserDirectory::class, UserRepository::class);
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/', [AuthController::class, 'home']);
        $router->get('/login', [AuthController::class, 'showLogin']);
        $router->post('/login', [AuthController::class, 'login']);
        $router->get('/register', [AuthController::class, 'showRegister']);
        $router->post('/register', [AuthController::class, 'register']);
        $router->post('/logout', [AuthController::class, 'logout']);
    }
}
