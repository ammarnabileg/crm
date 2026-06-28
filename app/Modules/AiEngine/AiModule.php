<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\AiEngine\Presentation\AiController;

final class AiModule implements Module
{
    public function name(): string
    {
        return 'AiEngine';
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
        $router->get('/ai', [AiController::class, 'index']);
        $router->get('/ai/analytics', [AiController::class, 'analytics']);
        $router->post('/ai/provider', [AiController::class, 'setProvider']);
        $router->post('/ai/keys', [AiController::class, 'addKey']);
        $router->post('/ai/interview-mode', [AiController::class, 'setInterviewMode']);
    }
}
