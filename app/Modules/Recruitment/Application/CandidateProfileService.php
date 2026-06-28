<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Candidate Profile = a per-(User, Workspace) VIEW (never an account, never
 * cross-workspace). A workspace only ever sees the data produced by that user's
 * interaction with it (docs/DOMAIN_MODEL.md, docs/CANDIDATE_PROFILE...).
 */
final class CandidateProfileService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getOrCreate(string $workspaceId, string $userId): string
    {
        $existing = $this->connection->selectOne(
            'SELECT id FROM candidate_profiles WHERE workspace_id = ? AND user_id = ?',
            [$workspaceId, $userId],
        );

        if ($existing !== null) {
            return (string) $existing['id'];
        }

        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO candidate_profiles (id, workspace_id, user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$id, $workspaceId, $userId, $now, $now],
        );

        return $id;
    }

    /** @return array<string, mixed>|null the profile + the user identity, workspace-scoped */
    public function profile(string $workspaceId, string $userId): ?array
    {
        return $this->connection->selectOne(
            'SELECT cp.id AS profile_id, cp.summary, u.id AS user_id, u.name, u.email
               FROM candidate_profiles cp JOIN users u ON u.id = cp.user_id
              WHERE cp.workspace_id = ? AND cp.user_id = ?',
            [$workspaceId, $userId],
        );
    }

    /** @return list<array<string, mixed>> this user's applications IN THIS workspace only */
    public function applications(string $workspaceId, string $userId): array
    {
        return $this->connection->select(
            'SELECT a.id, a.status, a.applied_at, j.title AS job_title, s.name AS stage
               FROM applications a
               JOIN jobs j ON j.id = a.job_id
               LEFT JOIN pipeline_stages s ON s.id = a.current_stage_id
              WHERE a.workspace_id = ? AND a.user_id = ? AND a.deleted_at IS NULL
              ORDER BY a.applied_at DESC',
            [$workspaceId, $userId],
        );
    }

    public function addNote(string $workspaceId, string $profileId, ?string $authorId, string $body): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO candidate_notes (id, workspace_id, candidate_profile_id, author_user_id, body, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $profileId, $authorId, $body, $now, $now],
        );
    }

    /** @return list<array<string, mixed>> */
    public function notes(string $workspaceId, string $profileId): array
    {
        return $this->connection->select(
            'SELECT n.body, n.created_at, u.name AS author
               FROM candidate_notes n LEFT JOIN users u ON u.id = n.author_user_id
              WHERE n.workspace_id = ? AND n.candidate_profile_id = ? AND n.deleted_at IS NULL
              ORDER BY n.created_at DESC',
            [$workspaceId, $profileId],
        );
    }

    public function addTag(string $workspaceId, string $profileId, string $name): void
    {
        $tag = $this->connection->selectOne('SELECT id FROM tags WHERE workspace_id = ? AND name = ?', [$workspaceId, $name]);
        $now = gmdate('Y-m-d H:i:s');

        if ($tag === null) {
            $tagId = Ulid::generate();
            $this->connection->statement('INSERT INTO tags (id, workspace_id, name, created_at, updated_at) VALUES (?, ?, ?, ?, ?)', [$tagId, $workspaceId, $name, $now, $now]);
        } else {
            $tagId = (string) $tag['id'];
        }

        $this->connection->statement(
            'INSERT IGNORE INTO candidate_profile_tags (id, candidate_profile_id, tag_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [Ulid::generate(), $profileId, $tagId, $now, $now],
        );
    }

    /** @return list<string> */
    public function tags(string $profileId): array
    {
        $rows = $this->connection->select(
            'SELECT t.name FROM candidate_profile_tags cpt JOIN tags t ON t.id = cpt.tag_id WHERE cpt.candidate_profile_id = ? ORDER BY t.name',
            [$profileId],
        );

        return array_map(static fn (array $r): string => (string) $r['name'], $rows);
    }
}
