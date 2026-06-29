<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Audit\Application\AuditLogger;
use HaHireAI\Modules\Workflow\Application\AuditTriggerBridge;
use HaHireAI\Modules\Workflow\Application\WorkflowEngine;
use HaHireAI\Modules\Workflow\Domain\NodeCatalog;
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
        // Decorate the audit recorder so every audit entry is also re-published on
        // the bus as `audit.{action}` — that's how recruitment/workspace lifecycle
        // events become workflow triggers without touching any existing service.
        $container->singleton(AuditRecorder::class, static fn (Container $c): AuditRecorder => new AuditTriggerBridge(
            new AuditLogger($c->make(Connection::class)),
            $c->make(EventDispatcher::class),
        ));
    }

    public function boot(Container $container): void
    {
        $events = $container->make(EventDispatcher::class);

        // Subscribe to every trigger event the catalog declares (the domain
        // 'application.submitted' plus the bridged 'audit.*' events). One listener
        // per unique event runs all workflows bound to it.
        $seen = [];
        foreach (NodeCatalog::triggerBindings() as $binding) {
            $event = (string) $binding['event'];
            if ($event === '' || isset($seen[$event])) {
                continue;
            }
            $seen[$event] = true;

            $events->listen($event, static function (mixed $payload) use ($container, $event): void {
                if (! is_array($payload) || empty($payload['workspace_id'])) {
                    return;
                }

                $container->make(WorkflowEngine::class)->runForTrigger(
                    (string) $payload['workspace_id'],
                    $event,
                    $payload,
                    $payload['user_id'] ?? null,
                );
            });
        }
    }

    public function routes(Router $router): void
    {
        $router->get('/workflows', [WorkflowController::class, 'index']);
        $router->get('/workflows/new', [WorkflowController::class, 'builder']);
        $router->get('/workflows/templates', [WorkflowController::class, 'templates']);
        $router->post('/workflows/templates/{key}/use', [WorkflowController::class, 'useTemplate']);
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
