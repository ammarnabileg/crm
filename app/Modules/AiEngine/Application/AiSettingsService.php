<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;

/**
 * Per-workspace AI configuration + encrypted provider keys. Keys are stored
 * ciphertext-only and never returned to the UI (docs/TENANT_AI.md, docs/AI_SECURITY.md).
 */
final class AiSettingsService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Encrypter $encrypter,
    ) {
    }

    /** @return array{provider: string, model: ?string, fallback_provider: ?string, use_platform_key: bool} */
    public function forWorkspace(string $workspaceId): array
    {
        $row = $this->connection->selectOne('SELECT * FROM ai_settings WHERE workspace_id = ?', [$workspaceId]);

        return [
            'provider' => (string) ($row['provider'] ?? 'echo'),
            'model' => $row['model'] ?? null,
            'fallback_provider' => $row['fallback_provider'] ?? null,
            'use_platform_key' => $row === null ? true : (bool) $row['use_platform_key'],
        ];
    }

    public function setProvider(string $workspaceId, string $provider, ?string $model = null, ?string $fallback = null, bool $usePlatformKey = false): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->connection->selectOne('SELECT id FROM ai_settings WHERE workspace_id = ?', [$workspaceId]);

        if ($existing === null) {
            $this->connection->statement(
                'INSERT INTO ai_settings (id, workspace_id, provider, model, fallback_provider, use_platform_key, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $workspaceId, $provider, $model, $fallback, (int) $usePlatformKey, $now, $now],
            );

            return;
        }

        $this->connection->statement(
            'UPDATE ai_settings SET provider = ?, model = ?, fallback_provider = ?, use_platform_key = ?, updated_at = ? WHERE workspace_id = ?',
            [$provider, $model, $fallback, (int) $usePlatformKey, $now, $workspaceId],
        );
    }

    public function setKey(string $workspaceId, string $provider, string $plaintextKey): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('DELETE FROM ai_keys WHERE workspace_id = ? AND provider = ?', [$workspaceId, $provider]);
        $this->connection->statement(
            'INSERT INTO ai_keys (id, workspace_id, provider, encrypted_key, key_hint, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $provider, $this->encrypter->encrypt($plaintextKey), $this->encrypter->hint($plaintextKey), $now, $now],
        );
    }

    public function getKey(string $workspaceId, string $provider): ?string
    {
        $row = $this->connection->selectOne('SELECT encrypted_key FROM ai_keys WHERE workspace_id = ? AND provider = ?', [$workspaceId, $provider]);

        return $row === null ? null : $this->encrypter->decrypt((string) $row['encrypted_key']);
    }

    /** True if this workspace has its own key for the provider (no decryption). */
    public function hasKey(string $workspaceId, string $provider): bool
    {
        $row = $this->connection->selectOne('SELECT 1 AS x FROM ai_keys WHERE workspace_id = ? AND provider = ?', [$workspaceId, $provider]);

        return $row !== null;
    }

    /** @return list<array{provider: string, key_hint: ?string}> never exposes the secret */
    public function keyHints(string $workspaceId): array
    {
        return $this->connection->select('SELECT provider, key_hint FROM ai_keys WHERE workspace_id = ? ORDER BY provider', [$workspaceId]);
    }
}
