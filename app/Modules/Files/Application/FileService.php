<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Files\Application;

use HaHireAI\Core\Contracts\FileStorage;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Files\Application\Exceptions\FileException;
use HaHireAI\Shared\Ulid;

/**
 * Stores workspace-scoped files (CVs, attachments) OUTSIDE the web root and
 * records metadata. Files are never web-served directly — download is streamed
 * through a permission-gated controller, so one workspace can never read
 * another's (docs/SECURITY_GUIDE.md, privacy isolation).
 *
 * The shared file surface is the FileStorage contract (ARCHITECTURE.md §4).
 */
final class FileService implements FileStorage
{
    private const MAX_BYTES = 10 * 1024 * 1024; // 10 MB
    private const ALLOWED_EXT = ['pdf', 'doc', 'docx', 'txt', 'rtf', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'odt'];

    public function __construct(
        private readonly Connection $connection,
        private readonly string $storageDir,
    ) {
    }

    /**
     * Persist an uploaded (or provided) file and record it.
     *
     * @return string the file id
     */
    public function store(
        string $workspaceId,
        ?string $uploaderId,
        ?string $entityType,
        ?string $entityId,
        string $tmpPath,
        string $originalName,
        ?int $sizeBytes = null,
        bool $isUpload = true,
    ): string {
        $size = $sizeBytes ?? (is_file($tmpPath) ? (int) filesize($tmpPath) : 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new FileException('File is empty or exceeds the 10 MB limit.');
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            throw new FileException('File type not allowed: .' . $ext);
        }

        $dir = rtrim($this->storageDir, '/') . '/' . $workspaceId;
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new FileException('Could not create storage directory.');
        }

        $id = Ulid::generate();
        $dest = $dir . '/' . $id . '-' . $this->safeName($originalName);

        if (! $this->persist($tmpPath, $dest, $isUpload)) {
            throw new FileException('Could not store the uploaded file.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO files (id, workspace_id, entity_type, entity_id, uploaded_by, original_name, stored_path, mime, size_bytes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $entityType, $entityId, $uploaderId, $originalName, $dest, $this->detectMime($dest, $ext), $size, $now, $now],
        );

        return $id;
    }

    /** @return list<array<string, mixed>> files attached to an entity in this workspace */
    public function listForEntity(string $workspaceId, string $entityType, string $entityId): array
    {
        return $this->connection->select(
            'SELECT id, original_name, mime, size_bytes, uploaded_by, created_at
               FROM files WHERE workspace_id = ? AND entity_type = ? AND entity_id = ? AND deleted_at IS NULL
              ORDER BY created_at DESC',
            [$workspaceId, $entityType, $entityId],
        );
    }

    /** @return list<array<string, mixed>> */
    public function listForWorkspace(string $workspaceId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return $this->connection->select(
            'SELECT id, entity_type, original_name, mime, size_bytes, created_at
               FROM files WHERE workspace_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT ' . $limit,
            [$workspaceId],
        );
    }

    /** @return array<string, mixed>|null a file row, scoped to the workspace */
    public function find(string $workspaceId, string $fileId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM files WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$fileId, $workspaceId],
        );
    }

    /** Read the bytes of a workspace file, or null if missing. */
    public function read(string $workspaceId, string $fileId): ?string
    {
        $file = $this->find($workspaceId, $fileId);
        if ($file === null || ! is_file((string) $file['stored_path'])) {
            return null;
        }

        $bytes = file_get_contents((string) $file['stored_path']);

        return $bytes === false ? null : $bytes;
    }

    public function delete(string $workspaceId, string $fileId): void
    {
        $file = $this->find($workspaceId, $fileId);
        if ($file === null) {
            return;
        }

        $this->connection->statement(
            'UPDATE files SET deleted_at = ? WHERE id = ? AND workspace_id = ?',
            [gmdate('Y-m-d H:i:s'), $fileId, $workspaceId],
        );
        @unlink((string) $file['stored_path']);
    }

    private function persist(string $tmp, string $dest, bool $isUpload): bool
    {
        // Real uploads must use move_uploaded_file; tests/imports use copy.
        return $isUpload ? @move_uploaded_file($tmp, $dest) : @copy($tmp, $dest);
    }

    private function detectMime(string $path, string $ext): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path) ?: '';
                finfo_close($finfo);
                if ($mime !== '') {
                    return $mime;
                }
            }
        }

        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }

    private function safeName(string $name): string
    {
        return \HaHireAI\Support\Filename::safe($name, 'file');
    }
}
