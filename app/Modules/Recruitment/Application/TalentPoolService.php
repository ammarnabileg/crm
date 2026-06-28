<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Talent pools — workspace-scoped saved lists of candidates for future roles
 * (recruitment spec #14). A candidate can belong to multiple pools.
 */
final class TalentPoolService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function createPool(string $workspaceId, string $name, ?string $description = null, ?string $createdBy = null): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO talent_pools (id, workspace_id, name, description, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $name, $description, $createdBy, $now, $now],
        );

        return $id;
    }

    /** @return list<array<string,mixed>> pools with member counts */
    public function listPools(string $workspaceId): array
    {
        return $this->connection->select(
            'SELECT p.id, p.name, p.description, p.created_at,
                    (SELECT COUNT(*) FROM talent_pool_members m WHERE m.pool_id = p.id) AS members
               FROM talent_pools p WHERE p.workspace_id = ? AND p.deleted_at IS NULL ORDER BY p.created_at DESC',
            [$workspaceId],
        );
    }

    public function addCandidate(string $workspaceId, string $poolId, string $candidateUserId, ?string $addedBy = null, ?string $note = null): void
    {
        // Guard the pool belongs to this workspace.
        if ($this->connection->selectOne('SELECT id FROM talent_pools WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL', [$poolId, $workspaceId]) === null) {
            return;
        }

        $this->connection->statement(
            'INSERT IGNORE INTO talent_pool_members (id, workspace_id, pool_id, candidate_user_id, note, added_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $poolId, $candidateUserId, $note, $addedBy, gmdate('Y-m-d H:i:s')],
        );
    }

    /** Add several candidates to a pool at once (bulk). Returns how many were added. */
    public function addCandidates(string $workspaceId, string $poolId, array $candidateUserIds, ?string $addedBy = null): int
    {
        if ($this->connection->selectOne('SELECT id FROM talent_pools WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL', [$poolId, $workspaceId]) === null) {
            return 0;
        }
        $n = 0;
        foreach (array_unique(array_filter(array_map('strval', $candidateUserIds))) as $uid) {
            $this->addCandidate($workspaceId, $poolId, $uid, $addedBy);
            $n++;
        }

        return $n;
    }

    /**
     * Dynamic "smart lists" — auto-computed candidate segments for re-engagement.
     * Each: key, label, description, candidates[] (user_id, name, email, signal).
     *
     * @return list<array<string, mixed>>
     */
    public function smartLists(string $workspaceId): array
    {
        $ws = [$workspaceId];

        // Strong AI screen but not hired — prime for re-contact on the next role.
        $strong = $this->connection->select(
            "SELECT ca.candidate_user_id AS user_id, u.name, u.email, MAX(ca.fit_score) AS `signal`
               FROM candidate_assessments ca
               JOIN users u ON u.id = ca.candidate_user_id
              WHERE ca.workspace_id = ? AND ca.fit_score >= 75
                AND NOT EXISTS (SELECT 1 FROM applications a WHERE a.workspace_id = ca.workspace_id AND a.user_id = ca.candidate_user_id AND a.status = 'hired')
              GROUP BY ca.candidate_user_id, u.name, u.email
              ORDER BY `signal` DESC LIMIT 50",
            $ws,
        );

        // Previously rejected/disqualified — a passive pool to revisit.
        $rejected = $this->connection->select(
            "SELECT DISTINCT a.user_id, u.name, u.email, a.status AS `signal`
               FROM applications a JOIN users u ON u.id = a.user_id
              WHERE a.workspace_id = ? AND a.status IN ('rejected','disqualified') AND a.deleted_at IS NULL
              ORDER BY u.name LIMIT 50",
            $ws,
        );

        // Interviewed but never offered — strong pipeline that stalled.
        $noOffer = $this->connection->select(
            "SELECT DISTINCT i.candidate_user_id AS user_id, u.name, u.email, i.score AS `signal`
               FROM interviews i JOIN users u ON u.id = i.candidate_user_id
              WHERE i.workspace_id = ? AND i.status = 'completed' AND i.deleted_at IS NULL
                AND NOT EXISTS (SELECT 1 FROM offers o JOIN applications a ON a.id = o.application_id WHERE a.workspace_id = i.workspace_id AND a.user_id = i.candidate_user_id AND o.deleted_at IS NULL)
              ORDER BY u.name LIMIT 50",
            $ws,
        );

        return [
            ['key' => 'strong', 'label' => 'Strong AI, not hired', 'description' => 'Candidates the AI rated ≥75 who weren’t hired — worth re-engaging.', 'candidates' => $strong],
            ['key' => 'rejected', 'label' => 'Previously rejected', 'description' => 'Rejected or disqualified candidates to revisit for a better-fit role.', 'candidates' => $rejected],
            ['key' => 'no_offer', 'label' => 'Interviewed, no offer', 'description' => 'Completed an interview but never received an offer.', 'candidates' => $noOffer],
        ];
    }

    public function removeCandidate(string $workspaceId, string $poolId, string $candidateUserId): void
    {
        $this->connection->statement(
            'DELETE FROM talent_pool_members WHERE workspace_id = ? AND pool_id = ? AND candidate_user_id = ?',
            [$workspaceId, $poolId, $candidateUserId],
        );
    }

    /** @return list<array<string,mixed>> candidates in a pool */
    public function members(string $workspaceId, string $poolId): array
    {
        return $this->connection->select(
            'SELECT m.candidate_user_id AS user_id, u.name, u.email, m.note, m.created_at
               FROM talent_pool_members m JOIN users u ON u.id = m.candidate_user_id
              WHERE m.workspace_id = ? AND m.pool_id = ? ORDER BY m.created_at DESC',
            [$workspaceId, $poolId],
        );
    }

    /** @return list<array<string,mixed>> pools a candidate is in (for the profile) */
    public function poolsForCandidate(string $workspaceId, string $candidateUserId): array
    {
        return $this->connection->select(
            'SELECT p.id, p.name FROM talent_pool_members m JOIN talent_pools p ON p.id = m.pool_id
              WHERE m.workspace_id = ? AND m.candidate_user_id = ? AND p.deleted_at IS NULL ORDER BY p.name',
            [$workspaceId, $candidateUserId],
        );
    }

    /** @return array<string,mixed>|null */
    public function findPool(string $workspaceId, string $poolId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM talent_pools WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$poolId, $workspaceId],
        );
    }
}
