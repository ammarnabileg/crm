<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Integration\Application\ApiTokenService;
use HaHireAI\Modules\Integration\Application\WebhookService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/**
 * The Developer Portal — manage API tokens and webhook endpoints. Plaintext
 * tokens and webhook secrets are revealed exactly once (via flash) and never
 * shown again (docs/INTEGRATION_PLATFORM.md §6).
 */
final class IntegrationController
{
    /** Events the platform currently publishes (subscribable by webhooks). */
    private const PUBLISHABLE_EVENTS = ['application.submitted'];

    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly ApiTokenService $tokens,
        private readonly WebhookService $webhooks,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('integration.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();

        return $this->shell->render($this->context, 'integration.index', [
            'tokens' => $this->tokens->listForWorkspace($ws),
            'endpoints' => $this->webhooks->listForWorkspace($ws),
            'deliveries' => $this->webhooks->recentDeliveries($ws, 15),
            'events' => self::PUBLISHABLE_EVENTS,
            'canManageTokens' => $this->context->can('api.tokens.manage'),
            'canManageWebhooks' => $this->context->can('webhook.manage'),
            'newToken' => $this->session->pullFlash('new_token'),
            'newSecret' => $this->session->pullFlash('new_secret'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function createToken(Request $request): Response
    {
        if (($r = $this->gate('api.tokens.manage', $request)) !== null) {
            return $r;
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $this->session->flash('status', 'A token name is required.');

            return Response::redirect('/integrations');
        }

        $issued = $this->tokens->issue((string) $this->context->workspaceId(), (string) $this->context->userId(), $name);

        $this->audit->record('integration.api_token.created', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'api_token',
            'entity_id' => $issued['id'],
            'changes' => ['name' => $name],
        ]);

        $this->session->flash('new_token', $issued['plaintext']);
        $this->session->flash('status', "Token “{$name}” created. Copy it now — it won't be shown again.");

        return Response::redirect('/integrations');
    }

    public function revokeToken(Request $request, string $id): Response
    {
        if (($r = $this->gate('api.tokens.manage', $request)) !== null) {
            return $r;
        }

        $this->tokens->revoke((string) $this->context->workspaceId(), $id);
        $this->audit->record('integration.api_token.revoked', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'api_token',
            'entity_id' => $id,
        ]);
        $this->session->flash('status', 'Token revoked.');

        return Response::redirect('/integrations');
    }

    public function createWebhook(Request $request): Response
    {
        if (($r = $this->gate('webhook.manage', $request)) !== null) {
            return $r;
        }

        $url = trim((string) $request->input('url', ''));
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with($url, 'http')) {
            $this->session->flash('status', 'A valid https URL is required.');

            return Response::redirect('/integrations');
        }

        $events = (array) $request->input('events', []);
        $events = array_values(array_intersect(self::PUBLISHABLE_EVENTS, array_map('strval', $events)));
        if ($events === []) {
            $this->session->flash('status', 'Select at least one event to subscribe to.');

            return Response::redirect('/integrations');
        }

        $created = $this->webhooks->createEndpoint(
            (string) $this->context->workspaceId(),
            $url,
            $events,
            $this->context->userId(),
            trim((string) $request->input('description', '')) ?: null,
        );

        $this->audit->record('integration.webhook.created', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'webhook_endpoint',
            'entity_id' => $created['id'],
            'changes' => ['url' => $url, 'events' => $events],
        ]);

        $this->session->flash('new_secret', $created['secret']);
        $this->session->flash('status', 'Webhook created. Copy the signing secret now — it verifies every delivery.');

        return Response::redirect('/integrations');
    }

    public function toggleWebhook(Request $request, string $id): Response
    {
        if (($r = $this->gate('webhook.manage', $request)) !== null) {
            return $r;
        }

        $enabled = (string) $request->input('enabled', '0') === '1';
        $this->webhooks->setEnabled((string) $this->context->workspaceId(), $id, $enabled);
        $this->session->flash('status', $enabled ? 'Webhook enabled.' : 'Webhook disabled.');

        return Response::redirect('/integrations');
    }

    public function deleteWebhook(Request $request, string $id): Response
    {
        if (($r = $this->gate('webhook.manage', $request)) !== null) {
            return $r;
        }

        $this->webhooks->delete((string) $this->context->workspaceId(), $id);
        $this->audit->record('integration.webhook.deleted', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'webhook_endpoint',
            'entity_id' => $id,
        ]);
        $this->session->flash('status', 'Webhook deleted.');

        return Response::redirect('/integrations');
    }

    private function gate(string $permission, ?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can($permission)) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }
        if ($request !== null && ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        return null;
    }
}
