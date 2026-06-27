<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Authentication;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Authentication\Presentation\AuthController;

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
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/login', [AuthController::class, 'showLogin']);
        $router->post('/login', [AuthController::class, 'login']);
        $router->get('/register', [AuthController::class, 'showRegister']);
        $router->post('/register', [AuthController::class, 'register']);
        $router->post('/logout', [AuthController::class, 'logout']);
    }
}
