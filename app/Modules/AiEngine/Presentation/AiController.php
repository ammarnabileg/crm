<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\AiEngine\Application\AiAnalyticsService;
use HaHireAI\Modules\AiEngine\Application\AiEngine;
use HaHireAI\Modules\AiEngine\Application\AiSettingsService;
use HaHireAI\Modules\AiEngine\Application\ProviderRegistry;
use HaHireAI\Modules\AiEngine\Contracts\AiCapabilities;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Application\WorkspacePreferences;
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
        private readonly WorkspacePreferences $preferences,
        private readonly AiAnalyticsService $analytics,
        private readonly AiCapabilities $capabilities,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    /** Per-workspace AI analytics dashboard (performance + token consumption). */
    public function analytics(): Response
    {
        if (($r = $this->gate('ai.view')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'ai.analytics', [
            'analytics' => $this->analytics->workspaceAnalytics((string) $this->context->workspaceId()),
        ]);
    }

    public function index(): Response
    {
        if (($r = $this->gate('ai.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();

        return $this->shell->render($this->context, 'ai.settings', [
            'config' => $this->settings->forWorkspace($ws),
            'keyHints' => $this->settings->keyHints($ws),
            'providers' => $this->registry->keys(),
            'usage' => $this->engine->usageSummary($ws),
            'canConfigure' => $this->context->can('ai.configure'),
            'canManageKeys' => $this->context->can('ai.keys.manage'),
            'interviewVideo' => $this->preferences->bool($ws, 'ai.interview_video'),
            'capabilities' => $this->capabilities->status($ws),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ]);
    }

    /** Owner toggles AI interviews between text-only and live video (HeyGen). */
    public function setInterviewMode(Request $request): Response
    {
        if (($r = $this->gate('ai.configure', $request)) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $video = (string) $request->input('interview_video', '0') === '1';

        // Can't enable video without the keys it depends on (HeyGen + OpenAI).
        if ($video && ! $this->capabilities->videoEnabled($ws)) {
            $this->session->flash('error', 'Add an OpenAI key and a HeyGen key first — video interviews can’t run without them.');

            return Response::redirect('/ai');
        }

        $this->preferences->set($ws, 'ai.interview_video', $video ? '1' : '0');
        $this->audit->record('ai.settings.updated', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'ai_settings',
            'changes' => ['interview_video' => $video],
        ]);
        $this->session->flash('status', $video ? 'Live video interviews enabled (HeyGen).' : 'Interviews set to text-only.');

        return Response::redirect('/ai');
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
            $ws = (string) $this->context->workspaceId();
            $this->settings->setKey($ws, $provider, $key);
            // Never log the key value.
            $this->audit->record('ai.key.added', [
                'workspace_id' => $this->context->workspaceId(),
                'actor_user_id' => $this->context->userId(),
                'entity_type' => 'ai_key',
                'changes' => ['provider' => $provider],
            ]);

            // Seamless: turn the dependent feature on the moment its key arrives.
            $note = '';
            if ($provider === 'heygen' && $this->capabilities->videoEnabled($ws)) {
                $this->preferences->set($ws, 'ai.interview_video', '1');
                $note = ' Live video interviews are now enabled.';
            } elseif ($provider === 'openai') {
                $note = ' AI interviews and CV analysis are now active.';
            }

            $this->session->flash('status', "Encrypted API key saved for {$provider}.{$note}");
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
