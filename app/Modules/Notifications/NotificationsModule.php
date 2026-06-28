<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Notifications;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Contracts\NotificationFeed;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Notifications\Application\NotificationService;
use HaHireAI\Modules\Notifications\Presentation\NotificationsController;

/**
 * Notification Center — a reactor on the event bus. Actor modules publish domain
 * events; this turns relevant ones into personal notifications, with no coupling
 * back from the actors (docs/ARCHITECTURE.md §4).
 */
final class NotificationsModule implements Module
{
    public function name(): string
    {
        return 'Notifications';
    }

    public function dependencies(): array
    {
        return ['Workspaces'];
    }

    public function register(Container $container): void
    {
        // Header bell feed other modules read without coupling (§4).
        $container->singleton(NotificationFeed::class, static fn (Container $c): NotificationFeed => $c->make(NotificationService::class));
    }

    public function boot(Container $container): void
    {
        $events = $container->make(EventDispatcher::class);

        // Acknowledge the candidate the moment their application lands.
        $events->listen('application.submitted', static function (mixed $payload) use ($container): void {
            if (! is_array($payload) || ! isset($payload['workspace_id'], $payload['user_id'])) {
                return;
            }

            try {
                $job = (string) ($payload['job_title'] ?? 'a role');
                $container->make(NotificationService::class)->notify(
                    (string) $payload['workspace_id'],
                    (string) $payload['user_id'],
                    'application',
                    'Application received',
                    'Your application for ' . $job . ' has been received.',
                    isset($payload['job_id']) ? '/jobs/' . (string) $payload['job_id'] : null,
                );
            } catch (\Throwable) {
                // Notifications must never break the publishing action.
            }
        });
    }

    public function routes(Router $router): void
    {
        $router->get('/notifications', [NotificationsController::class, 'index']);
        $router->post('/notifications/read', [NotificationsController::class, 'markAll']);
        $router->post('/notifications/{id}/read', [NotificationsController::class, 'read']);
        $router->post('/notifications/{id}/archive', [NotificationsController::class, 'archive']);
        $router->post('/notifications/{id}/unarchive', [NotificationsController::class, 'unarchive']);
    }
}
