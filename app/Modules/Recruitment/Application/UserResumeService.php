<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Recruitment\Application\Exceptions\ApplicationException;
use HaHireAI\Modules\Recruitment\Infrastructure\Resume\ResumeParserManager;
use HaHireAI\Shared\Ulid;

/**
 * The candidate's GLOBAL CV library — owned by the User, reusable across every
 * workspace and job (the CV is the person's, not the workspace's). Bytes are
 * stored OUTSIDE the web root under a per-user folder; the normalised text is
 * parsed ONCE at upload and cached, so re-analysing for a new job never re-reads
 * the file. Read access is always scoped by user_id, so a candidate only ever
 * sees their own résumés.
 */
final class UserResumeService
{
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const ALLOWED_EXT = ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt'];

    public function __construct(
        private readonly Connection $connection,
        private readonly ResumeParserManager $parsers,
        private readonly string $storageDir,
    ) {
    }

    /** Persist a CV into the user's library; parse + cache its text. Returns the id. */
    public function store(string $userId, string $tmpPath, string $originalName, ?string $mime = null, bool $isUpload = true): string
    {
        $size = is_file($tmpPath) ? (int) filesize($tmpPath) : 0;
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new ApplicationException('CV is empty or exceeds the 10 MB limit.');
        }
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            throw new ApplicationException('CV type not allowed: .' . $ext);
        }

        $dir = rtrim($this->storageDir, '/') . '/' . $userId;
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new ApplicationException('Could not create the CV storage directory.');
        }

        $id = Ulid::generate();
        $dest = $dir . '/' . $id . '-' . $this->safeName($originalName);
        $ok = $isUpload ? @move_uploaded_file($tmpPath, $dest) : @copy($tmpPath, $dest);
        if (! $ok) {
            throw new ApplicationException('Could not store the CV.');
        }

        $extracted = $this->parsers->extract($dest, $originalName, (string) ($mime ?? ''));
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO user_resumes (id, user_id, original_name, stored_path, mime, size_bytes, extracted_text, parser, parse_confidence, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $userId, $originalName, $dest, $mime ?? $this->guessMime($ext), $size, $extracted->text, $extracted->parser, $extracted->confidence, $now, $now],
        );

        return $id;
    }

    /** @return list<array<string, mixed>> the user's CVs (newest first) */
    public function list(string $userId): array
    {
        return $this->connection->select(
            'SELECT id, original_name, mime, size_bytes, parser, parse_confidence, created_at
               FROM user_resumes WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC',
            [$userId],
        );
    }

    /** @return array<string, mixed>|null a CV row scoped to its owner */
    public function find(string $userId, string $resumeId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM user_resumes WHERE id = ? AND user_id = ? AND deleted_at IS NULL',
            [$resumeId, $userId],
        );
    }

    /**
     * The cached normalised text for a CV (re-parsing from disk if the cache is
     * empty). Scoped to the owner.
     *
     * @return array{text: string, confidence: int, parser: string}
     */
    public function text(string $userId, string $resumeId): array
    {
        $row = $this->find($userId, $resumeId);
        if ($row === null) {
            return ['text' => '', 'confidence' => 0, 'parser' => 'none'];
        }
        $text = (string) ($row['extracted_text'] ?? '');
        if ($text !== '') {
            return ['text' => $text, 'confidence' => (int) ($row['parse_confidence'] ?? 0), 'parser' => (string) ($row['parser'] ?? 'cache')];
        }
        // Cache miss — re-extract from disk.
        $extracted = $this->parsers->extract((string) $row['stored_path'], (string) $row['original_name'], (string) ($row['mime'] ?? ''));

        return ['text' => $extracted->text, 'confidence' => $extracted->confidence, 'parser' => $extracted->parser];
    }

    /** Raw bytes for a download, scoped to the owner. */
    public function readBytes(string $userId, string $resumeId): ?string
    {
        $row = $this->find($userId, $resumeId);
        if ($row === null || ! is_file((string) $row['stored_path'])) {
            return null;
        }
        $bytes = file_get_contents((string) $row['stored_path']);

        return $bytes === false ? null : $bytes;
    }

    public function delete(string $userId, string $resumeId): void
    {
        $row = $this->find($userId, $resumeId);
        if ($row === null) {
            return;
        }
        $this->connection->statement(
            'UPDATE user_resumes SET deleted_at = ? WHERE id = ? AND user_id = ?',
            [gmdate('Y-m-d H:i:s'), $resumeId, $userId],
        );
        @unlink((string) $row['stored_path']);
    }

    public function countForUser(string $userId): int
    {
        $row = $this->connection->selectOne('SELECT COUNT(*) AS c FROM user_resumes WHERE user_id = ? AND deleted_at IS NULL', [$userId]);

        return (int) ($row['c'] ?? 0);
    }

    private function safeName(string $name): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name) ?? 'cv';

        return substr(trim($clean, '_'), 0, 120) ?: 'cv';
    }

    private function guessMime(string $ext): string
    {
        return match ($ext) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            'odt' => 'application/vnd.oasis.opendocument.text',
            default => 'application/octet-stream',
        };
    }
}
