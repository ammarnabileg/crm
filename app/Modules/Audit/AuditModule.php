<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Audit;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Audit\Presentation\ActivityController;

final class AuditModule implements Module
{
    public function name(): string
    {
        return 'Audit';
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
        $router->get('/activity', [ActivityController::class, 'index']);
    }
}
