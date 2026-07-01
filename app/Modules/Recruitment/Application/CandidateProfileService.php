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

    /**
     * Every candidate in a workspace (the Candidates list), name order, with their
     * application count. Keeps query logic out of the controller (ARCHITECTURE §3).
     *
     * @return list<array<string, mixed>>
     */
    public function listForWorkspace(string $workspaceId): array
    {
        return $this->connection->select(
            'SELECT cp.user_id, u.name, u.email,
                    (SELECT COUNT(*) FROM applications a WHERE a.workspace_id = cp.workspace_id AND a.user_id = cp.user_id AND a.deleted_at IS NULL) AS applications
               FROM candidate_profiles cp JOIN users u ON u.id = cp.user_id
              WHERE cp.workspace_id = ? ORDER BY u.name',
            [$workspaceId],
        );
    }

    /**
     * Non-AI ATS keyword search: match a term against the candidate's name/email
     * and their CV-derived data (summary + structured details: skills, education,
     * …). Works without any AI key — details come from the deterministic résumé
     * parser or manual entry.
     *
     * @return list<array<string, mixed>>
     */
    public function searchByKeyword(string $workspaceId, string $q): array
    {
        $like = '%' . $q . '%';

        // Matches name/email/summary, the legacy JSON details, AND the normalised
        // candidate_profile_fields (the migration target) — so search keeps working
        // as readers move off the JSON blob.
        return $this->connection->select(
            "SELECT cp.user_id, u.name, u.email,
                    (SELECT COUNT(*) FROM applications a WHERE a.workspace_id = cp.workspace_id AND a.user_id = cp.user_id AND a.deleted_at IS NULL) AS applications
               FROM candidate_profiles cp JOIN users u ON u.id = cp.user_id
              WHERE cp.workspace_id = ?
                AND (u.name LIKE ? OR u.email LIKE ? OR cp.summary LIKE ? OR CAST(cp.details AS CHAR) LIKE ?
                     OR EXISTS (SELECT 1 FROM candidate_profile_fields f
                                 WHERE f.workspace_id = cp.workspace_id AND f.user_id = cp.user_id AND f.field_value LIKE ?))
              ORDER BY u.name",
            [$workspaceId, $like, $like, $like, $like, $like],
        );
    }

    /**
     * Structured candidate data (CV-derived) for the Decision Center — education,
     * languages, skills, certifications, salary, availability. Workspace-scoped.
     *
     * @return array<string, mixed>
     */
    public function details(string $workspaceId, string $userId): array
    {
        $row = $this->connection->selectOne(
            'SELECT details FROM candidate_profiles WHERE workspace_id = ? AND user_id = ?',
            [$workspaceId, $userId],
        );
        if ($row === null || empty($row['details'])) {
            return [];
        }

        return is_array($row['details']) ? $row['details'] : (json_decode((string) $row['details'], true) ?: []);
    }

    /** @param array<string,mixed> $details */
    public function saveDetails(string $workspaceId, string $userId, array $details): void
    {
        $this->getOrCreate($workspaceId, $userId);
        $this->connection->statement(
            'UPDATE candidate_profiles SET details = ?, updated_at = ? WHERE workspace_id = ? AND user_id = ?',
            [json_encode($details), gmdate('Y-m-d H:i:s'), $workspaceId, $userId],
        );
        // Gradual migration off the JSON blob: dual-write the normalised rows so the
        // new table always mirrors the JSON. Readers can move over incrementally;
        // the JSON column is dropped only in a later, approved step.
        $this->syncFields($workspaceId, $userId, $details);
    }

    /**
     * Mirror a details map into the normalised `candidate_profile_fields` table
     * (shared flatten logic with the backfill migration). Best-effort: never blocks
     * a details save if the normalised table is absent.
     *
     * @param  array<string,mixed>  $details
     */
    private function syncFields(string $workspaceId, string $userId, array $details): void
    {
        try {
            $this->connection->statement(
                'DELETE FROM candidate_profile_fields WHERE workspace_id = ? AND user_id = ?',
                [$workspaceId, $userId],
            );
            $now = gmdate('Y-m-d H:i:s');
            foreach (\HaHireAI\Modules\Recruitment\Domain\CandidateProfileFields::flatten($details) as $row) {
                $this->connection->statement(
                    'INSERT INTO candidate_profile_fields (id, workspace_id, user_id, field_key, field_value, position, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [Ulid::generate(), $workspaceId, $userId, $row['key'], $row['value'], $row['position'], $now],
                );
            }
        } catch (\Throwable) {
            // The normalised table may not exist yet (pre-migration) — the JSON
            // write above is the source of truth until every reader has moved.
        }
    }

    /**
     * Read the normalised fields for a candidate (the migration target). Grouped by
     * key: scalar keys → the single value, array keys → the list.
     *
     * @return array<string, string|list<string>>
     */
    public function fields(string $workspaceId, string $userId): array
    {
        $rows = $this->connection->select(
            'SELECT field_key, field_value FROM candidate_profile_fields WHERE workspace_id = ? AND user_id = ? ORDER BY field_key, position',
            [$workspaceId, $userId],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['field_key']][] = (string) $r['field_value'];
        }

        // Collapse single-value keys to a scalar for ergonomic reads.
        return array_map(static fn (array $v): string|array => count($v) === 1 ? $v[0] : $v, $out);
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
