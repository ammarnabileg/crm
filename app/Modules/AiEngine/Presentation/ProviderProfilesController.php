<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Contracts\EntitlementResolver;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\AiEngine\Application\ProviderProfileService;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** AI Provider Profiles (Phase B): manage named provider/model/key profiles. */
final class ProviderProfilesController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly ProviderProfileService $profiles,
        private readonly EntitlementResolver $entitlements,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('ai.keys.manage')) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();

        return $this->shell->render($this->context, 'ai.profiles', [
            'profiles' => $this->profiles->list($ws),
            'entitled' => $this->entitled($ws),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($r = $this->gate('ai.keys.manage', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        if (! $this->entitled($ws)) {
            $this->session->flash('error', 'AI is a premium service. Enable it in Build Your Workspace first.');

            return Response::redirect('/billing');
        }

        $name = trim((string) $request->input('name', ''));
        $provider = trim((string) $request->input('provider', ''));
        $key = (string) $request->input('api_key', '');
        if ($name === '' || $provider === '' || $key === '') {
            $this->session->flash('error', 'Name, provider and API key are required.');

            return Response::redirect('/ai/profiles');
        }

        $id = $this->profiles->create($ws, $name, $provider, trim((string) $request->input('model', '')) ?: null, $key, $request->input('make_default') !== null);
        $this->audit->record('ai.profile.created', [
            'workspace_id' => $ws, 'actor_user_id' => $this->context->userId(),
            'entity_type' => 'ai_provider_profile', 'entity_id' => $id,
        ]);
        $this->session->flash('status', 'Provider profile created.');

        return Response::redirect('/ai/profiles');
    }

    public function setDefault(Request $request, string $id): Response
    {
        if (($r = $this->gate('ai.keys.manage', $request)) !== null) {
            return $r;
        }
        $this->profiles->setDefault((string) $this->context->workspaceId(), $id);
        $this->session->flash('status', 'Default profile updated.');

        return Response::redirect('/ai/profiles');
    }

    public function delete(Request $request, string $id): Response
    {
        if (($r = $this->gate('ai.keys.manage', $request)) !== null) {
            return $r;
        }
        $this->profiles->delete((string) $this->context->workspaceId(), $id);
        $this->session->flash('status', 'Profile deleted.');

        return Response::redirect('/ai/profiles');
    }

    private function entitled(string $ws): bool
    {
        $features = $this->entitlements->gateFeatures($ws);

        return $features === null || in_array('ai', $features, true);
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
