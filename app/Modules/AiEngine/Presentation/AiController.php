<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\AiEngine\Application\AiEngine;
use HaHireAI\Modules\AiEngine\Application\AiSettingsService;
use HaHireAI\Modules\AiEngine\Application\ProviderRegistry;
use HaHireAI\Modules\Audit\Application\AuditLogger;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** Per-workspace AI settings + encrypted keys (docs/AI_SETTINGS.md). */
final class AiController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly AiSettingsService $settings,
        private readonly AiEngine $engine,
        private readonly ProviderRegistry $registry,
        private readonly Session $session,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('ai.view')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'ai.settings', [
            'config' => $this->settings->forWorkspace((string) $this->context->workspaceId()),
            'keyHints' => $this->settings->keyHints((string) $this->context->workspaceId()),
            'providers' => $this->registry->keys(),
            'usage' => $this->engine->usageSummary((string) $this->context->workspaceId()),
            'canConfigure' => $this->context->can('ai.configure'),
            'canManageKeys' => $this->context->can('ai.keys.manage'),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ]);
    }

    public function setProvider(Request $request): Response
    {
        if (($r = $this->gate('ai.configure', $request)) !== null) {
            return $r;
        }

        $this->settings->setProvider(
            (string) $this->context->workspaceId(),
            (string) $request->input('provider', 'echo'),
            ($m = trim((string) $request->input('model', ''))) !== '' ? $m : null,
            ($f = trim((string) $request->input('fallback_provider', ''))) !== '' ? $f : null,
            (bool) $request->input('use_platform_key', false),
        );
        $this->audit->record('ai.settings.updated', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'ai_settings',
        ]);
        $this->session->flash('status', 'AI provider settings updated.');

        return Response::redirect('/ai');
    }

    public function addKey(Request $request): Response
    {
        if (($r = $this->gate('ai.keys.manage', $request)) !== null) {
            return $r;
        }

        $provider = trim((string) $request->input('provider', ''));
        $key = trim((string) $request->input('api_key', ''));

        if ($provider !== '' && $key !== '') {
            $this->settings->setKey((string) $this->context->workspaceId(), $provider, $key);
            // Never log the key value.
            $this->audit->record('ai.key.added', [
                'workspace_id' => $this->context->workspaceId(),
                'actor_user_id' => $this->context->userId(),
                'entity_type' => 'ai_key',
                'changes' => ['provider' => $provider],
            ]);
            $this->session->flash('status', "Encrypted API key saved for {$provider}.");
        }

        return Response::redirect('/ai');
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
