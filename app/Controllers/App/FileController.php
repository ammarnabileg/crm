<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\File;
use App\Services\Files\FileService;
use App\Services\Files\FileStorage;

/**
 * Files (docs/30 File Upload) — the documents uploaded into the current workspace.
 *
 * Lists this workspace's files, accepts a single multipart upload, streams a file
 * back as an attachment, and soft-deletes one. Reads require recruitment.view and
 * writes recruitment.manage; every action re-checks its permission at the top with
 * abort_unless. All work is tenant-scoped to the active workspace via FileService,
 * and bytes live on the local disk (no external storage credential needed).
 */
final class FileController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(can('recruitment.view'), 403);

        return $this->view('app.files.index', [
            'title'     => 'Files',
            'files'     => $this->files()->list(),
            'canManage' => can('recruitment.manage'),
        ]);
    }

    public function upload(Request $request): Response
    {
        abort_unless(can('recruitment.manage'), 403);

        $uploaded = $request->file('file');
        if (! is_array($uploaded)) {
            $this->withError('Please choose a file to upload.');

            return $this->back();
        }

        $result = $this->files()->store($uploaded, (int) auth()->id());

        if (! $result instanceof File) {
            $errors = is_array($result) ? ($result['errors'] ?? []) : [];
            $this->withError($errors[0] ?? 'The file could not be uploaded.');

            return $this->back();
        }

        ActivityLog::record(
            'files.uploaded',
            tenant()->id(),
            (int) auth()->id(),
            'Uploaded ' . (string) $result->getAttribute('original_name') . '.',
            [],
            'file',
            (int) $result->getKey(),
        );
        $this->withSuccess('File uploaded.');

        return $this->redirect(url('files'));
    }

    public function download(Request $request): Response
    {
        abort_unless(can('recruitment.view'), 403);

        $id = (int) $request->query('id', 0);
        $file = $id > 0 ? $this->files()->find($id) : null;
        abort_unless($file !== null, 404);

        // Resolve a traversal-safe absolute path on the local disk.
        $absolute = (new FileStorage())->path((string) $file->getAttribute('path'));
        abort_unless($absolute !== null, 404);

        $contents = @file_get_contents($absolute);
        abort_unless($contents !== false, 404);

        $name = (string) $file->getAttribute('original_name');
        $mime = (string) ($file->getAttribute('mime') ?: 'application/octet-stream');

        return Response::make($contents, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'attachment; filename="' . $this->headerSafeName($name) . '"',
            'Content-Length'      => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'       => 'private, no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
        ]);
    }

    public function delete(Request $request): Response
    {
        abort_unless(can('recruitment.manage'), 403);

        $id = (int) $request->input('id', 0);
        $deleted = $id > 0 && $this->files()->delete($id, (int) auth()->id());

        if ($deleted) {
            ActivityLog::record('files.deleted', tenant()->id(), (int) auth()->id(), 'Deleted a file.', [], 'file', $id);
            $this->withSuccess('File deleted.');
        } else {
            $this->withError('That file could not be found.');
        }

        return $this->redirect(url('files'));
    }

    private function files(): FileService
    {
        return new FileService();
    }

    /**
     * Strip characters that could break out of the Content-Disposition filename
     * (quotes, control chars, path separators); the browser only needs a safe label.
     */
    private function headerSafeName(string $name): string
    {
        $name = str_replace(['\\', '/', '"', "\r", "\n", "\0"], '_', $name);

        return $name === '' ? 'download' : $name;
    }
}
