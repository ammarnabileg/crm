<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Tasks;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\TaskBoard;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Tasks\Application\TaskService;
use HaHireAI\Modules\Tasks\Presentation\TasksController;

/** Workspace tasks — hiring to-dos surfaced on the dashboard "My tasks" (Feature 14). */
final class TasksModule implements Module
{
    public function name(): string
    {
        return 'Tasks';
    }

    public function dependencies(): array
    {
        return ['Workspaces'];
    }

    public function register(Container $container): void
    {
        // The shared task read-surface other modules depend on (ARCHITECTURE.md §4).
        $container->singleton(TaskBoard::class, static fn (Container $c): TaskBoard => $c->make(TaskService::class));
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/tasks', [TasksController::class, 'index']);
        $router->post('/tasks', [TasksController::class, 'create']);
        $router->post('/tasks/{id}/complete', [TasksController::class, 'complete']);
        $router->post('/tasks/{id}/reopen', [TasksController::class, 'reopen']);
        $router->post('/tasks/{id}/delete', [TasksController::class, 'delete']);
    }
}
