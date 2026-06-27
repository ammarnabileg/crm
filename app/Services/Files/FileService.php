<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Core\Database;
use App\Models\File;

/**
 * Tenant-scoped orchestration over the `files` table (docs/30 File Upload).
 *
 * Validates and persists uploads, lists the workspace's files (with the uploader's
 * name in one batched query — no N+1), and soft-deletes a file together with its
 * bytes on disk. Every read/write is constrained to the active workspace
 * (tenant()->id()) and fails closed; the concrete bytes are handled by the
 * local-disk FileStorage, so no external storage credential is ever required.
 *
 * store() accepts a PHP upload array shaped exactly like a $_FILES entry
 * (App\Core\Request::file()) — ['name','type','tmp_name','error','size'] — and
 * returns the created File model on success, or ['errors' => string[]] on a
 * validation failure (so the controller can flash and redirect back).
 */
final class FileService
{
    /** Largest upload accepted, in bytes (10 MB). */
    public const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * Allowed extension => acceptable mime types. The extension is the primary
     * gate (the browser-supplied mime is advisory); both are recorded.
     *
     * @var array<string, string[]>
     */
    private const ALLOWED = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv'],
        'txt'  => ['text/plain'],
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
    ];

    private Database $db;

    public function __construct(private readonly FileStorage $storage = new FileStorage())
    {
        $this->db = app('db');
    }

    /**
     * The current workspace's non-deleted files, newest first, each decorated with
     * the uploader's name (resolved in ONE query keyed by user_id — no N+1).
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $rows = $this->db->table('files')
            ->select(
                'id', 'uuid', 'user_id', 'original_name', 'mime', 'size',
                'checksum', 'path', 'storage_provider_id', 'visibility_id', 'created_at',
            )
            ->where('workspace_id', '=', $this->workspaceId())
            ->whereNull('deleted_at')
            ->orderBy('id', 'desc')
            ->get();

        if ($rows === []) {
            return [];
        }

        $names = $this->uploaderNames(array_map(
            static fn (array $r): int => (int) ($r['user_id'] ?? 0),
            $rows
        ));

        foreach ($rows as &$row) {
            $row['uploader'] = $names[(int) ($row['user_id'] ?? 0)] ?? '—';
            $row['size_human'] = $this->humanSize((int) ($row['size'] ?? 0));
        }
        unset($row);

        return $rows;
    }

    /**
     * Validate and persist an uploaded file for the given user in the active
     * workspace. On success the bytes are moved under
     * storage/app/uploads/<workspace_id>/<uuid>-<sanitised name> and a `files`
     * row is written; the created File model is returned. On a validation failure
     * nothing is stored and ['errors' => string[]] is returned.
     *
     * @param array<string, mixed> $uploadedFile a $_FILES-shaped entry
     * @return File|array{errors: string[]}
     */
    public function store(array $uploadedFile, int $userId): File|array
    {
        $errors = $this->validateUpload($uploadedFile);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $tmpPath = (string) $uploadedFile['tmp_name'];
        $originalName = $this->sanitiseName((string) $uploadedFile['name']);
        $size = (int) ($uploadedFile['size'] ?? @filesize($tmpPath) ?: 0);
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = $this->resolveMime($tmpPath, $extension, (string) ($uploadedFile['type'] ?? ''));
        $checksum = hash_file('sha256', $tmpPath) ?: null;

        $workspaceId = $this->workspaceId();
        $uuid = File::generateUuid();
        $relativePath = $workspaceId . '/' . $uuid . '-' . $originalName;

        if (! $this->storage->store($tmpPath, $relativePath)) {
            return ['errors' => ['The file could not be saved. Please try again.']];
        }

        // Model::create stamps workspace_id (active tenant) and the uuid; pass the
        // SAME uuid we built the path from so row and disk stay in lockstep.
        return File::create([
            'uuid'                => $uuid,
            'user_id'             => $userId,
            'storage_provider_id' => $this->localProviderId(),
            'disk'                => FileStorage::DISK,
            'path'                => $relativePath,
            'original_name'       => $originalName,
            'mime'                => $mime,
            'size'                => $size,
            'checksum'            => $checksum,
            'visibility_id'       => (int) (lookup_id('file_visibility', 'private') ?? 0),
        ]);
    }

    /**
     * Tenant-scoped lookup of a single non-deleted file.
     */
    public function find(int $id): ?File
    {
        return File::find($id);
    }

    /**
     * Soft-delete a file (tenant-scoped) and remove its bytes from disk. Returns
     * false when the file does not belong to this workspace or is already gone.
     */
    public function delete(int $id, int $userId): bool
    {
        $file = $this->find($id);
        if ($file === null) {
            return false;
        }

        $deleted = $file->delete();
        if ($deleted) {
            // Best-effort disk cleanup; the soft-delete is the source of truth.
            $this->storage->delete((string) $file->getAttribute('path'));
        }

        return $deleted;
    }

    // --- Internals ---------------------------------------------------------

    /**
     * @param array<string, mixed> $file
     * @return string[] human-readable validation errors (empty when valid)
     */
    private function validateUpload(array $file): array
    {
        $errors = [];

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') {
            return ['Please choose a file to upload.'];
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return ['That file is too large.'];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['The upload failed. Please try again.'];
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || ! is_file($tmpPath)) {
            return ['The upload failed. Please try again.'];
        }

        $size = (int) ($file['size'] ?? @filesize($tmpPath) ?: 0);
        if ($size <= 0) {
            $errors[] = 'The file appears to be empty.';
        } elseif ($size > self::MAX_BYTES) {
            $errors[] = 'That file is too large (max ' . $this->humanSize(self::MAX_BYTES) . ').';
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension === '' || ! array_key_exists($extension, self::ALLOWED)) {
            $errors[] = 'That file type is not allowed.';
        }

        return $errors;
    }

    /**
     * Best-effort server-side mime: trust finfo when available, fall back to the
     * extension's first allowed mime, then the browser-supplied type.
     */
    private function resolveMime(string $tmpPath, string $extension, string $clientMime): string
    {
        if (class_exists(\finfo::class) && is_file($tmpPath)) {
            $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
            if (is_string($detected) && $detected !== '' && $detected !== 'application/octet-stream') {
                return $detected;
            }
        }

        $allowed = self::ALLOWED[$extension] ?? [];
        if ($allowed !== []) {
            return $allowed[0];
        }

        return $clientMime !== '' ? $clientMime : 'application/octet-stream';
    }

    /**
     * Reduce an uploaded filename to a safe basename: strip directories, collapse
     * anything non-portable to underscores, and cap the length.
     */
    private function sanitiseName(string $name): string
    {
        $base = basename(str_replace('\\', '/', trim($name)));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?? '';
        $base = trim($base, '._-');
        if ($base === '') {
            $base = 'file';
        }

        // Keep room for the "<uuid>-" prefix within the path column (varchar 512).
        if (strlen($base) > 180) {
            $extension = strtolower((string) pathinfo($base, PATHINFO_EXTENSION));
            $stem = substr($base, 0, 180 - (strlen($extension) + 1));
            $base = $extension !== '' ? $stem . '.' . $extension : $stem;
        }

        return $base;
    }

    /**
     * user_id => display name, in one query (no N+1 over the listing).
     *
     * @param int[] $userIds
     * @return array<int, string>
     */
    private function uploaderNames(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [];
        }

        $rows = $this->db->table('users')
            ->select('id', 'name')
            ->whereIn('id', $userIds)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = (string) $row['name'];
        }

        return $map;
    }

    /**
     * The "Default Local" storage provider id (driver = local). Cached per request.
     */
    private function localProviderId(): int
    {
        static $id = null;
        if ($id === null) {
            $id = (int) ($this->db->table('storage_providers')->where('driver', '=', 'local')->value('id') ?? 0);
        }

        return $id;
    }

    private function workspaceId(): int
    {
        return (int) tenant()->id();
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) min(floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return ($power === 0 ? (string) $bytes : number_format($value, 1)) . ' ' . $units[$power];
    }
}
