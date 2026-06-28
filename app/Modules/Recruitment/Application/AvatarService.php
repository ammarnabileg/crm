<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * AI interviewer avatars/personas — workspace-scoped (recruitment spec #3). A job
 * can be paired with an avatar to make the AI interview feel more human.
 */
final class AvatarService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @param array{persona?:string,gender?:?string,language?:string,image_url?:?string,style_notes?:?string} $opts */
    public function create(string $workspaceId, string $name, array $opts = [], ?string $createdBy = null): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO ai_avatars (id, workspace_id, name, persona, gender, language, image_url, style_notes, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $workspaceId, $name, $opts['persona'] ?? 'professional', $opts['gender'] ?? null,
                $opts['language'] ?? 'en', $opts['image_url'] ?? null, $opts['style_notes'] ?? null, $createdBy, $now, $now,
            ],
        );

        return $id;
    }

    /** @param array{name?:string,persona?:string,gender?:?string,language?:string,image_url?:?string,style_notes?:?string} $fields */
    public function update(string $workspaceId, string $avatarId, array $fields): void
    {
        $this->connection->statement(
            'UPDATE ai_avatars SET name = ?, persona = ?, gender = ?, language = ?, image_url = ?, style_notes = ?, updated_at = ?
              WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [
                (string) ($fields['name'] ?? ''), (string) ($fields['persona'] ?? 'professional'), $fields['gender'] ?? null,
                (string) ($fields['language'] ?? 'en'), $fields['image_url'] ?? null, $fields['style_notes'] ?? null,
                gmdate('Y-m-d H:i:s'), $avatarId, $workspaceId,
            ],
        );
    }

    public function delete(string $workspaceId, string $avatarId): void
    {
        $this->connection->statement(
            'UPDATE ai_avatars SET deleted_at = ? WHERE id = ? AND workspace_id = ?',
            [gmdate('Y-m-d H:i:s'), $avatarId, $workspaceId],
        );
    }

    /** @return list<array<string,mixed>> */
    public function listForWorkspace(string $workspaceId): array
    {
        return $this->connection->select(
            'SELECT * FROM ai_avatars WHERE workspace_id = ? AND deleted_at IS NULL ORDER BY created_at DESC',
            [$workspaceId],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(string $workspaceId, string $avatarId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM ai_avatars WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$avatarId, $workspaceId],
        );
    }
}
