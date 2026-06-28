<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Tokenized interview invitation links (recruitment spec #5). A link is valid for
 * a fixed window (default 14 days) and is single-use: once the interview is done
 * it cannot be reused. Resolution is workspace-agnostic by token (it's a public
 * link) but every record is workspace-scoped.
 */
final class InterviewInvitationService
{
    public const VALID_DAYS = 14;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{id: string, token: string, expires_at: string}
     */
    public function create(string $workspaceId, string $jobId, ?string $applicationId = null, ?string $candidateEmail = null, ?string $createdBy = null, ?int $nowTs = null): array
    {
        $nowTs ??= time();
        $id = Ulid::generate();
        $token = bin2hex(random_bytes(24));
        $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+' . self::VALID_DAYS . ' days', $nowTs) ?: $nowTs);

        $this->connection->statement(
            'INSERT INTO interview_invitations (id, workspace_id, job_id, application_id, candidate_email, token, status, expires_at, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $jobId, $applicationId, $candidateEmail, $token, 'pending', $expiresAt, $createdBy, gmdate('Y-m-d H:i:s', $nowTs)],
        );

        return ['id' => $id, 'token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * Resolve a token to its state for the public page.
     *
     * @return array{state: string, invitation: array<string,mixed>|null}
     *         state ∈ invalid | expired | completed | revoked | valid
     */
    public function resolve(string $token, ?int $nowTs = null): array
    {
        $nowTs ??= time();
        $row = $this->connection->selectOne('SELECT * FROM interview_invitations WHERE token = ?', [$token]);

        if ($row === null) {
            return ['state' => 'invalid', 'invitation' => null];
        }
        if ((string) $row['status'] === 'completed') {
            return ['state' => 'completed', 'invitation' => $row];
        }
        if ((string) $row['status'] === 'revoked') {
            return ['state' => 'revoked', 'invitation' => $row];
        }
        if (strtotime((string) $row['expires_at']) < $nowTs) {
            return ['state' => 'expired', 'invitation' => $row];
        }

        return ['state' => 'valid', 'invitation' => $row];
    }

    /** Mark an invitation completed (single-use), recording the interview. */
    public function complete(string $token, ?string $interviewId = null): void
    {
        $this->connection->statement(
            "UPDATE interview_invitations SET status = 'completed', interview_id = ?, completed_at = ? WHERE token = ? AND status = 'pending'",
            [$interviewId, gmdate('Y-m-d H:i:s'), $token],
        );
    }

    /** @return list<array<string,mixed>> invitations for a job */
    public function listForJob(string $workspaceId, string $jobId): array
    {
        return $this->connection->select(
            'SELECT id, token, status, candidate_email, expires_at, completed_at, created_at
               FROM interview_invitations WHERE workspace_id = ? AND job_id = ? ORDER BY created_at DESC',
            [$workspaceId, $jobId],
        );
    }
}
