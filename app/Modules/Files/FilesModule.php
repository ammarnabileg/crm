<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Files;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\FileStorage;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Files\Application\FileService;
use HaHireAI\Modules\Files\Presentation\FilesController;

/**
 * Files & CVs — workspace-scoped attachments stored outside the web root and
 * served only through permission-gated, tenant-checked download. FileService is
 * a shared attachment service other modules (e.g. Recruitment) reuse.
 */
final class FilesModule implements Module
{
    public function name(): string
    {
        return 'Files';
    }

    public function dependencies(): array
    {
        return ['Workspaces'];
    }

    public function register(Container $container): void
    {
        $container->singleton(FileService::class, static fn (Container $c): FileService => new FileService(
            $c->make(Connection::class),
            storage_path('files'),
        ));

        // The shared file surface other modules depend on (ARCHITECTURE.md §4).
        $container->singleton(FileStorage::class, static fn (Container $c): FileStorage => $c->make(FileService::class));
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/files', [FilesController::class, 'index']);
        $router->post('/files/upload', [FilesController::class, 'upload']);
        $router->get('/files/{fileId}/download', [FilesController::class, 'download']);
        $router->post('/files/{fileId}/delete', [FilesController::class, 'delete']);
    }
}
