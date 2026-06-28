<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Issues and verifies workspace-scoped API tokens for the gateway. Tokens are
 * stored only as a SHA-256 hash; the plaintext is returned exactly once at issue
 * time and never persisted (docs/INTEGRATION_PLATFORM.md §2, SECURITY_GUIDE.md).
 */
final class ApiTokenService
{
    private const PREFIX = 'hh_';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{id: string, plaintext: string, prefix: string, last_four: string}
     */
    public function issue(string $workspaceId, string $userId, string $name, ?string $expiresAt = null): array
    {
        $plaintext = self::PREFIX . bin2hex(random_bytes(20));
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->statement(
            'INSERT INTO api_tokens (id, workspace_id, user_id, name, token_hash, token_prefix, last_four, expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $workspaceId, $userId, $name,
                $this->hash($plaintext), substr($plaintext, 0, 7), substr($plaintext, -4),
                $expiresAt, $now, $now,
            ],
        );

        return ['id' => $id, 'plaintext' => $plaintext, 'prefix' => substr($plaintext, 0, 7), 'last_four' => substr($plaintext, -4)];
    }

    /**
     * Resolve a presented plaintext token to its active row, or null. Touches
     * last_used_at on success. Revoked or expired tokens never authenticate.
     *
     * @return array<string, mixed>|null
     */
    public function authenticate(string $plaintext): ?array
    {
        if (! str_starts_with($plaintext, self::PREFIX)) {
            return null;
        }

        $row = $this->connection->selectOne(
            'SELECT * FROM api_tokens
              WHERE token_hash = ?
                AND revoked_at IS NULL
                AND (expires_at IS NULL OR expires_at > ?)',
            [$this->hash($plaintext), gmdate('Y-m-d H:i:s')],
        );

        if ($row === null) {
            return null;
        }

        $this->connection->statement(
            'UPDATE api_tokens SET last_used_at = ? WHERE id = ?',
            [gmdate('Y-m-d H:i:s'), (string) $row['id']],
        );

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function listForWorkspace(string $workspaceId): array
    {
        return $this->connection->select(
            'SELECT id, name, token_prefix, last_four, last_used_at, expires_at, revoked_at, created_at
               FROM api_tokens WHERE workspace_id = ? ORDER BY created_at DESC',
            [$workspaceId],
        );
    }

    /** Revoke a token within its workspace (idempotent). */
    public function revoke(string $workspaceId, string $tokenId): void
    {
        $this->connection->statement(
            'UPDATE api_tokens SET revoked_at = ? WHERE id = ? AND workspace_id = ? AND revoked_at IS NULL',
            [gmdate('Y-m-d H:i:s'), $tokenId, $workspaceId],
        );
    }

    private function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }
}
