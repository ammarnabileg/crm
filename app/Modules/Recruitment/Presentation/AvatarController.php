<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Recruitment\Application\AvatarService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** AI interviewer avatars — name, persona, gender, language, image (spec #3). */
final class AvatarController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly AvatarService $avatars,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('avatar.view')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'recruitment.avatars.index', [
            'avatars' => $this->avatars->listForWorkspace((string) $this->context->workspaceId()),
            'canManage' => $this->context->can('avatar.manage'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($r = $this->gate('avatar.manage', $request)) !== null) {
            return $r;
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            $id = $this->avatars->create((string) $this->context->workspaceId(), $name, $this->opts($request), $this->context->userId());
            $this->audit->record('recruitment.avatar.created', [
                'workspace_id' => $this->context->workspaceId(),
                'actor_user_id' => $this->context->userId(),
                'entity_type' => 'ai_avatar',
                'entity_id' => $id,
            ]);
            $this->session->flash('status', "Avatar “{$name}” created.");
        }

        return Response::redirect('/avatars');
    }

    public function update(Request $request, string $id): Response
    {
        if (($r = $this->gate('avatar.manage', $request)) !== null) {
            return $r;
        }

        $this->avatars->update((string) $this->context->workspaceId(), $id, ['name' => trim((string) $request->input('name', ''))] + $this->opts($request));
        $this->session->flash('status', 'Avatar updated.');

        return Response::redirect('/avatars');
    }

    public function delete(Request $request, string $id): Response
    {
        if (($r = $this->gate('avatar.manage', $request)) !== null) {
            return $r;
        }

        $this->avatars->delete((string) $this->context->workspaceId(), $id);
        $this->session->flash('status', 'Avatar deleted.');

        return Response::redirect('/avatars');
    }

    /** @return array{persona:string,gender:?string,language:string,image_url:?string,style_notes:?string} */
    private function opts(Request $request): array
    {
        return [
            'persona' => (string) $request->input('persona', 'professional'),
            'gender' => trim((string) $request->input('gender', '')) ?: null,
            'language' => (string) $request->input('language', 'en'),
            'image_url' => trim((string) $request->input('image_url', '')) ?: null,
            'style_notes' => trim((string) $request->input('style_notes', '')) ?: null,
        ];
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
