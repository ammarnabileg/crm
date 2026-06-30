<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Certificates issued when a learner completes a program. Idempotent (one
 * certificate per program per learner; re-issuing refreshes the percent). A short
 * human-readable serial makes the certificate verifiable. Tenant-scoped.
 */
final class CertificateService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** Issue (or refresh) a completion certificate. Returns the certificate id. */
    public function issue(string $workspaceId, string $programId, string $userId, string $title, int $percent = 100, ?string $enrollmentId = null): string
    {
        $existing = $this->forProgram($workspaceId, $programId, $userId);
        $now = gmdate('Y-m-d H:i:s');
        if ($existing !== null) {
            $this->connection->statement(
                'UPDATE learning_certificates SET percent = ?, title = ? WHERE id = ? AND workspace_id = ?',
                [$percent, mb_substr($title, 0, 255), (string) $existing['id'], $workspaceId],
            );

            return (string) $existing['id'];
        }

        $id = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO learning_certificates (id, workspace_id, program_id, user_id, enrollment_id, serial, title, percent, issued_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $programId, $userId, $enrollmentId, $this->serial($id), mb_substr($title, 0, 255), $percent, $now, $now],
        );

        return $id;
    }

    /** @return array<string,mixed>|null */
    public function forProgram(string $workspaceId, string $programId, string $userId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM learning_certificates WHERE workspace_id = ? AND program_id = ? AND user_id = ?',
            [$workspaceId, $programId, $userId],
        );
    }

    /** @return list<array<string,mixed>> a learner's certificates */
    public function forUser(string $workspaceId, string $userId): array
    {
        return $this->connection->select(
            'SELECT c.*, p.title AS program_title FROM learning_certificates c
               JOIN learning_programs p ON p.id = c.program_id
              WHERE c.workspace_id = ? AND c.user_id = ? ORDER BY c.issued_at DESC',
            [$workspaceId, $userId],
        );
    }

    /** @return array<string,mixed>|null verify a certificate by its serial */
    public function findBySerial(string $workspaceId, string $serial): ?array
    {
        return $this->connection->selectOne(
            'SELECT c.*, p.title AS program_title, u.name AS user_name FROM learning_certificates c
               JOIN learning_programs p ON p.id = c.program_id
               JOIN users u ON u.id = c.user_id
              WHERE c.workspace_id = ? AND c.serial = ?',
            [$workspaceId, $serial],
        );
    }

    private function serial(string $id): string
    {
        return 'CERT-' . strtoupper(substr($id, -10));
    }
}
