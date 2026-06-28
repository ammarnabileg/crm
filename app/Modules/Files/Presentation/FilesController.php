<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Files\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Files\Application\Exceptions\FileException;
use HaHireAI\Modules\Files\Application\FileService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** Workspace files: list, upload, permission-gated streamed download, delete. */
final class FilesController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly FileService $files,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('files.view')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'files.index', [
            'files' => $this->files->listForWorkspace((string) $this->context->workspaceId()),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function upload(Request $request): Response
    {
        if (($r = $this->gate('files.upload', $request)) !== null) {
            return $r;
        }

        $redirect = $this->safeRedirect((string) $request->input('redirect_to', '/files'));
        $upload = $request->file('file');
        if ($upload === null) {
            $this->session->flash('status', 'No valid file was uploaded.');

            return Response::redirect($redirect);
        }

        try {
            $id = $this->files->store(
                (string) $this->context->workspaceId(),
                $this->context->userId(),
                trim((string) $request->input('entity_type', '')) ?: null,
                trim((string) $request->input('entity_id', '')) ?: null,
                $upload['tmp_name'],
                $upload['name'],
                $upload['size'],
            );
        } catch (FileException $e) {
            $this->session->flash('status', $e->getMessage());

            return Response::redirect($redirect);
        }

        $this->audit->record('files.file.uploaded', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'file',
            'entity_id' => $id,
            'changes' => ['name' => $upload['name']],
        ]);
        $this->session->flash('status', 'File uploaded.');

        return Response::redirect($redirect);
    }

    public function download(string $fileId): Response
    {
        if (($r = $this->gate('files.view')) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();
        $file = $this->files->find($workspaceId, $fileId);
        $bytes = $file !== null ? $this->files->read($workspaceId, $fileId) : null;
        if ($file === null || $bytes === null) {
            return Response::html('<h1>404</h1><p>File not found.</p>', 404);
        }

        return Response::make($bytes, 200, [
            'Content-Type' => (string) $file['mime'],
            'Content-Disposition' => 'attachment; filename="' . $this->headerSafe((string) $file['original_name']) . '"',
            'Content-Length' => (string) strlen($bytes),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function delete(Request $request, string $fileId): Response
    {
        if (($r = $this->gate('files.delete', $request)) !== null) {
            return $r;
        }

        $this->files->delete((string) $this->context->workspaceId(), $fileId);
        $this->audit->record('files.file.deleted', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'file',
            'entity_id' => $fileId,
        ]);
        $this->session->flash('status', 'File deleted.');

        return Response::redirect($this->safeRedirect((string) $request->input('redirect_to', '/files')));
    }

    /** Only allow same-origin relative redirects. */
    private function safeRedirect(string $to): string
    {
        return (str_starts_with($to, '/') && ! str_contains($to, '://')) ? $to : '/files';
    }

    private function headerSafe(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9._ -]+/', '_', $name) ?? 'download';
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
