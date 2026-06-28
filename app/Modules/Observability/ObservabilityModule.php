<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Observability;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Observability\Application\BackupService;
use HaHireAI\Modules\Observability\Application\ErrorTracker;
use HaHireAI\Modules\Observability\Presentation\ObservabilityController;

/**
 * Observability, Diagnostics & Operations (System Owner context). Reuses the
 * existing HealthChecker, Logger, ErrorHandler and event bus rather than adding a
 * parallel telemetry stack (docs/OBSERVABILITY.md).
 */
final class ObservabilityModule implements Module
{
    public function name(): string
    {
        return 'Observability';
    }

    public function dependencies(): array
    {
        return ['Workspaces'];
    }

    public function register(Container $container): void
    {
        $container->singleton(BackupService::class, static fn (Container $c): BackupService => new BackupService(
            $c->make(Connection::class),
            storage_path('backups'),
        ));
    }

    public function boot(Container $container): void
    {
        // Persist every captured error (published by the Core ErrorHandler).
        $container->make(EventDispatcher::class)->listen(
            'system.error',
            static function (mixed $payload) use ($container): void {
                if (! is_array($payload)) {
                    return;
                }

                try {
                    $container->make(ErrorTracker::class)->record($payload);
                } catch (\Throwable) {
                    // Telemetry must never break request handling.
                }
            },
        );
    }

    public function routes(Router $router): void
    {
        $router->get('/admin', [ObservabilityController::class, 'overview']);
        $router->get('/admin/diagnostics', [ObservabilityController::class, 'diagnostics']);
        $router->get('/admin/diagnostics/json', [ObservabilityController::class, 'diagnosticsJson']);
        $router->post('/admin/diagnostics/backup', [ObservabilityController::class, 'runBackup']);
    }
}
