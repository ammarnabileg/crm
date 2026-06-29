<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;

/**
 * Named AI provider profiles (Phase B): a workspace defines reusable
 * {provider, model, key} profiles and selects one as default, instead of wiring a
 * raw key per interview/workflow. Keys are encrypted at rest and never returned
 * to the UI. The customer always brings their own key (Constitution section 8).
 */
final class ProviderProfileService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Encrypter $encrypter,
    ) {
    }

    public function create(string $workspaceId, string $name, string $provider, ?string $model, string $plaintextKey, bool $makeDefault = false): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $first = $this->connection->selectOne('SELECT 1 AS x FROM ai_provider_profiles WHERE workspace_id = ?', [$workspaceId]) === null;
        $default = $makeDefault || $first;
        if ($default) {
            $this->connection->statement('UPDATE ai_provider_profiles SET is_default = 0 WHERE workspace_id = ?', [$workspaceId]);
        }
        $this->connection->statement(
            'INSERT INTO ai_provider_profiles (id, workspace_id, name, provider, model, encrypted_key, key_hint, is_default, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $name, $provider, $model, $this->encrypter->encrypt($plaintextKey), $this->encrypter->hint($plaintextKey), (int) $default, $now, $now],
        );

        return $id;
    }

    /** @return list<array<string,mixed>> hints only — never the secret */
    public function list(string $workspaceId): array
    {
        return $this->connection->select(
            'SELECT id, name, provider, model, key_hint, is_default, created_at FROM ai_provider_profiles WHERE workspace_id = ? ORDER BY created_at ASC',
            [$workspaceId],
        );
    }

    public function setDefault(string $workspaceId, string $id): bool
    {
        $row = $this->connection->selectOne('SELECT id FROM ai_provider_profiles WHERE id = ? AND workspace_id = ?', [$id, $workspaceId]);
        if ($row === null) {
            return false;
        }
        $this->connection->statement('UPDATE ai_provider_profiles SET is_default = 0 WHERE workspace_id = ?', [$workspaceId]);
        $this->connection->statement('UPDATE ai_provider_profiles SET is_default = 1, updated_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $id]);

        return true;
    }

    public function delete(string $workspaceId, string $id): bool
    {
        return $this->connection->statement('DELETE FROM ai_provider_profiles WHERE id = ? AND workspace_id = ?', [$id, $workspaceId]) > 0;
    }

    /**
     * The default profile resolved for use (decrypts the key). Null if none.
     *
     * @return array{id:string,name:string,provider:string,model:?string,key:?string}|null
     */
    public function getDefault(string $workspaceId): ?array
    {
        $row = $this->connection->selectOne(
            'SELECT * FROM ai_provider_profiles WHERE workspace_id = ? ORDER BY is_default DESC, created_at ASC LIMIT 1',
            [$workspaceId],
        );
        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'provider' => (string) $row['provider'],
            'model' => $row['model'] ?? null,
            'key' => $row['encrypted_key'] !== null ? $this->encrypter->decrypt((string) $row['encrypted_key']) : null,
        ];
    }
}
