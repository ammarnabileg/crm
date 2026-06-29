<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Integration\Application\WebhookDispatcher;
use HaHireAI\Modules\Integration\Contracts\HttpClient;
use HaHireAI\Modules\Integration\Infrastructure\CurlHttpClient;
use HaHireAI\Modules\Integration\Presentation\Api\ApiController;
use HaHireAI\Modules\Integration\Presentation\IntegrationController;

/**
 * The Integration Platform: API Gateway (token-authenticated REST), and
 * outbound webhooks driven by the event bus. Like the Workflow Engine, webhook
 * delivery is a reactor — actor modules just publish events
 * (docs/INTEGRATION_PLATFORM.md, ARCHITECTURE.md §4).
 */
final class IntegrationModule implements Module
{
    /** Domain events fanned out to subscribed webhook endpoints. */
    private const WEBHOOK_EVENTS = ['application.submitted'];

    public function name(): string
    {
        return 'Integration';
    }

    public function dependencies(): array
    {
        return ['Workspaces', 'Recruitment'];
    }

    public function register(Container $container): void
    {
        // Real outbound transport; tests inject a fake HttpClient instead.
        $container->singleton(HttpClient::class, CurlHttpClient::class);
    }

    public function boot(Container $container): void
    {
        $events = $container->make(EventDispatcher::class);

        foreach (self::WEBHOOK_EVENTS as $event) {
            $events->listen($event, static function (mixed $payload, string $eventName) use ($container): void {
                if (! is_array($payload) || ! isset($payload['workspace_id'])) {
                    return;
                }

                $container->make(WebhookDispatcher::class)->dispatch(
                    (string) $payload['workspace_id'],
                    $eventName,
                    $payload,
                );
            });
        }
    }

    public function routes(Router $router): void
    {
        // API Gateway — token-authenticated REST surface.
        $router->group(['prefix' => '/api/v1'], static function (Router $r): void {
            $r->get('/ping', [ApiController::class, 'ping']);
            $r->get('/me', [ApiController::class, 'me']);
            $r->get('/jobs', [ApiController::class, 'listJobs']);
            $r->get('/jobs/{id}', [ApiController::class, 'showJob']);
        });

        // Developer Portal (session UI).
        $router->get('/integrations', [IntegrationController::class, 'index']);
        $router->post('/integrations/tokens', [IntegrationController::class, 'createToken']);
        $router->post('/integrations/tokens/{id}/revoke', [IntegrationController::class, 'revokeToken']);
        $router->post('/integrations/webhooks', [IntegrationController::class, 'createWebhook']);
        $router->post('/integrations/webhooks/{id}/toggle', [IntegrationController::class, 'toggleWebhook']);
        $router->post('/integrations/webhooks/{id}/delete', [IntegrationController::class, 'deleteWebhook']);
    }
}
