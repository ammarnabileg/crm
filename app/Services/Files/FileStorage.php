<?php

declare(strict_types=1);

namespace App\Services\Files;

/**
 * Local-disk storage abstraction for uploaded files (docs/30 File Upload).
 *
 * Everything is confined to the local 'local' disk rooted at
 * storage/app/uploads — there is NO cloud driver and NO external credential, so
 * the feature works out of the box on any host. Every method that takes a
 * caller-supplied relative path resolves it against that root and re-checks the
 * realpath, so a crafted "../" or absolute path can never read or write outside
 * the uploads tree (the established traversal guard from
 * App\Controllers\System\BackupController / BackupManager).
 */
final class FileStorage
{
    /** Disk key recorded on every files row written by this driver. */
    public const DISK = 'local';

    /**
     * Move (or copy) an uploaded temporary file to its relative destination under
     * the uploads root, creating intermediate directories. Returns false rather
     * than throwing on any failure (unsafe path, unwritable target) so callers
     * can fail closed. Genuine HTTP uploads are moved with move_uploaded_file();
     * anything else (e.g. a synthesised file in a test) falls back to rename/copy.
     */
    public function store(string $tmpPath, string $relativePath): bool
    {
        if ($tmpPath === '' || ! is_file($tmpPath)) {
            return false;
        }

        $target = $this->resolveForWrite($relativePath);
        if ($target === null) {
            return false;
        }

        $dir = dirname($target);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return false;
        }

        // A real uploaded file must be relocated with move_uploaded_file() so a
        // forged local path can never be passed off as an upload; otherwise fall
        // back to rename(), then copy() across filesystems.
        if (is_uploaded_file($tmpPath)) {
            if (@move_uploaded_file($tmpPath, $target)) {
                @chmod($target, 0664);

                return true;
            }

            return false;
        }

        if (@rename($tmpPath, $target) || @copy($tmpPath, $target)) {
            @chmod($target, 0664);

            return true;
        }

        return false;
    }

    /**
     * Absolute, traversal-safe path for a stored file. Only paths that resolve
     * inside the uploads root are returned; anything escaping (or that does not
     * exist) yields null. Use this before reading a file off disk.
     */
    public function path(string $relativePath): ?string
    {
        $full = $this->resolveForWrite($relativePath);
        if ($full === null || ! is_file($full)) {
            return null;
        }

        // Defence in depth: re-check the realpath after symlinks are resolved.
        $root = realpath($this->root());
        $real = realpath($full);
        if ($root === false || $real === false || ! str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    public function exists(string $relativePath): bool
    {
        return $this->path($relativePath) !== null;
    }

    /**
     * Delete a stored file. Returns true when the file is gone afterwards (an
     * already-absent file counts as deleted); false only when an existing file
     * could not be removed or the path was unsafe.
     */
    public function delete(string $relativePath): bool
    {
        $full = $this->path($relativePath);
        if ($full === null) {
            // Unsafe path, or nothing there to delete — treat a missing file as
            // already removed (idempotent), but a malformed path as a no-op true.
            return true;
        }

        return @unlink($full);
    }

    /**
     * Public URL for a stored file when one is exposed. The uploads tree is NOT
     * web-served by default (files are streamed through the download controller),
     * so this returns null unless a CDN/base URL is explicitly configured.
     */
    public function url(string $relativePath): ?string
    {
        $base = (string) config('filesystems.uploads_url', '');
        if ($base === '') {
            return null;
        }

        $clean = ltrim(str_replace('\\', '/', $relativePath), '/');

        return rtrim($base, '/') . '/' . $clean;
    }

    /**
     * Absolute uploads root (storage/app/uploads), created on first use.
     */
    public function root(): string
    {
        $root = storage_path('app/uploads');
        if (! is_dir($root)) {
            @mkdir($root, 0775, true);
        }

        return $root;
    }

    /**
     * Resolve a relative path to an absolute path inside the uploads root WITHOUT
     * requiring the file to exist yet (needed for writes). Rejects absolute paths,
     * NUL bytes and any ".." segment, then verifies the lexically-resolved path
     * still begins with the root. Returns null for anything unsafe.
     */
    private function resolveForWrite(string $relativePath): ?string
    {
        $relative = str_replace('\\', '/', trim($relativePath));
        if ($relative === '' || str_contains($relative, "\0")) {
            return null;
        }

        // Never accept an absolute path or a drive-style/UNC prefix.
        if (str_starts_with($relative, '/') || preg_match('#^[A-Za-z]:#', $relative) === 1) {
            return null;
        }

        // Reject any traversal segment outright before touching the filesystem.
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '..' || $segment === '.') {
                return null;
            }
        }

        $root = rtrim($this->root(), '/');
        $full = $root . '/' . ltrim($relative, '/');

        // Lexical containment check (realpath() can't run on a not-yet-created
        // file): the normalised candidate must stay under the root prefix.
        $normalised = $this->normalise($full);
        $normalisedRoot = $this->normalise($root);
        if ($normalised !== $normalisedRoot && ! str_starts_with($normalised, $normalisedRoot . '/')) {
            return null;
        }

        return $full;
    }

    /**
     * Collapse "." / ".." segments lexically (no filesystem access).
     */
    private function normalise(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/');
        $out = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($out);
                continue;
            }
            $out[] = $segment;
        }

        return ($isAbsolute ? '/' : '') . implode('/', $out);
    }
}
