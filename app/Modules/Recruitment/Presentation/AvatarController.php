<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Contracts\FileStorage;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Files\Application\Exceptions\FileException;
use HaHireAI\Modules\Recruitment\Application\AvatarService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** AI interviewer avatars — name, persona, gender, language, image (spec #3). */
final class AvatarController
{
    private const IMAGE_EXT = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    private const IMAGE_ENTITY = 'avatar_image';

    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly AvatarService $avatars,
        private readonly FileStorage $files,
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
            $ws = (string) $this->context->workspaceId();
            $id = $this->avatars->create($ws, $name, $this->opts($request), $this->context->userId());
            $this->storeImage($ws, $id, $request);
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

        $ws = (string) $this->context->workspaceId();
        $this->avatars->update($ws, $id, ['name' => trim((string) $request->input('name', ''))] + $this->opts($request));
        $this->storeImage($ws, $id, $request);
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

    /** Toggle active/inactive. */
    public function toggleStatus(Request $request, string $id): Response
    {
        if (($r = $this->gate('avatar.manage', $request)) !== null) {
            return $r;
        }
        $avatar = $this->avatars->find((string) $this->context->workspaceId(), $id);
        if ($avatar !== null) {
            $this->avatars->setStatus((string) $this->context->workspaceId(), $id, ((string) ($avatar['status'] ?? 'active')) === 'active' ? 'inactive' : 'active');
            $this->session->flash('status', 'Avatar status updated.');
        }

        return Response::redirect('/avatars');
    }

    /** Preview / test the avatar — renders its greeting and a sample question. */
    public function preview(string $id): Response
    {
        if (($r = $this->gate('avatar.view')) !== null) {
            return $r;
        }
        $avatar = $this->avatars->find((string) $this->context->workspaceId(), $id);
        if ($avatar === null) {
            return Response::redirect('/avatars');
        }

        return $this->shell->render($this->context, 'recruitment.avatars.preview', [
            'avatar' => $avatar,
            'imageSrc' => $this->imageSrc((string) $this->context->workspaceId(), $id, $avatar),
        ]);
    }

    /** Stream an avatar's uploaded image inline (any member who can view avatars). */
    public function image(string $id): Response
    {
        if (($r = $this->gate('avatar.view')) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $fileId = $this->imageFileId($ws, $id);
        $bytes = $fileId !== null ? $this->files->read($ws, $fileId) : null;
        $file = $fileId !== null ? $this->files->find($ws, $fileId) : null;
        if ($bytes === null || $file === null) {
            return Response::html('<h1>404</h1><p>No image.</p>', 404);
        }

        return Response::make($bytes, 200, [
            'Content-Type' => (string) $file['mime'],
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string,mixed> */
    private function opts(Request $request): array
    {
        return [
            'persona' => (string) $request->input('persona', 'professional'),
            'gender' => trim((string) $request->input('gender', '')) ?: null,
            'language' => (string) $request->input('language', 'en'),
            'style_notes' => trim((string) $request->input('style_notes', '')) ?: null,
            'prompt' => trim((string) $request->input('prompt', '')) ?: null,
            'greeting' => trim((string) $request->input('greeting', '')) ?: null,
            'voice' => trim((string) $request->input('voice', '')) ?: null,
            'knowledge' => trim((string) $request->input('knowledge', '')) ?: null,
        ];
    }

    /**
     * Persist an uploaded avatar image (replacing any previous one) and link it
     * to the avatar via the files table. Images are uploaded, never URLs.
     *
     * @param array<string,mixed> $avatar unused; kept for symmetry
     */
    private function storeImage(string $ws, string $avatarId, Request $request): void
    {
        $upload = $request->file('image');
        if ($upload === null || ($upload['tmp_name'] ?? '') === '') {
            return;
        }
        $ext = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
        if (! in_array($ext, self::IMAGE_EXT, true)) {
            $this->session->flash('error', 'Avatar image must be a PNG, JPG, WEBP or GIF.');

            return;
        }

        try {
            $newId = $this->files->store($ws, $this->context->userId(), self::IMAGE_ENTITY, $avatarId, (string) $upload['tmp_name'], (string) $upload['name'], (int) ($upload['size'] ?? 0));
        } catch (FileException $e) {
            $this->session->flash('error', $e->getMessage());

            return;
        }

        // Drop older images so only the latest remains.
        foreach ($this->files->listForEntity($ws, self::IMAGE_ENTITY, $avatarId) as $f) {
            if ((string) $f['id'] !== $newId) {
                $this->files->delete($ws, (string) $f['id']);
            }
        }
    }

    /** Newest uploaded image file id for an avatar, or null. */
    private function imageFileId(string $ws, string $avatarId): ?string
    {
        $files = $this->files->listForEntity($ws, self::IMAGE_ENTITY, $avatarId);

        return $files !== [] ? (string) $files[0]['id'] : null;
    }

    /** Resolve the display source: uploaded image stream, else legacy URL, else null. */
    private function imageSrc(string $ws, string $avatarId, array $avatar): ?string
    {
        if ($this->imageFileId($ws, $avatarId) !== null) {
            return '/avatars/' . $avatarId . '/image';
        }

        return ! empty($avatar['image_url']) ? (string) $avatar['image_url'] : null;
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
