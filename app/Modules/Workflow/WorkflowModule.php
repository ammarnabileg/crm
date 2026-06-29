<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Workflow\Application\WorkflowEngine;
use HaHireAI\Modules\Workflow\Presentation\CollectionsController;
use HaHireAI\Modules\Workflow\Presentation\WorkflowController;

/**
 * The Workflow Engine subscribes to domain events and runs matching workflows —
 * actor modules stay decoupled from reactors (docs/ARCHITECTURE.md §4).
 */
final class WorkflowModule implements Module
{
    public function name(): string
    {
        return 'Workflow';
    }

    public function dependencies(): array
    {
        return ['Workspaces', 'AiEngine'];
    }

    public function register(Container $container): void
    {
    }

    public function boot(Container $container): void
    {
        $events = $container->make(EventDispatcher::class);

        // Recruitment publishes 'application.submitted'; the engine reacts.
        $events->listen('application.submitted', static function (mixed $payload) use ($container): void {
            if (! is_array($payload) || ! isset($payload['workspace_id'])) {
                return;
            }

            $container->make(WorkflowEngine::class)->runForTrigger(
                (string) $payload['workspace_id'],
                'application.submitted',
                $payload,
                $payload['user_id'] ?? null,
            );
        });
    }

    public function routes(Router $router): void
    {
        $router->get('/workflows', [WorkflowController::class, 'index']);
        $router->get('/workflows/new', [WorkflowController::class, 'builder']);
        $router->get('/workflows/{id}/edit', [WorkflowController::class, 'builder']);
        $router->post('/workflows/save', [WorkflowController::class, 'save']);
        $router->post('/workflows/{id}/run', [WorkflowController::class, 'run']);
        $router->post('/workflows/{id}/toggle', [WorkflowController::class, 'toggle']);

        // Dynamic Collections — the no-code "database" with CSV export.
        $router->get('/collections', [CollectionsController::class, 'index']);
        $router->post('/collections', [CollectionsController::class, 'create']);
        $router->get('/collections/{id}', [CollectionsController::class, 'show']);
        $router->get('/collections/{id}/export', [CollectionsController::class, 'export']);
        $router->post('/collections/{id}/records', [CollectionsController::class, 'addRecord']);
        $router->post('/collections/{id}/records/{recordId}/delete', [CollectionsController::class, 'deleteRecord']);
    }
}
