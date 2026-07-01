<?php

declare(strict_types=1);

namespace Nizam\Behavior\Infrastructure\Persistence\Pdo;

use Nizam\Behavior\Domain\BehaviorChangeProposal;
use Nizam\Behavior\Domain\Enum\ProposalStatus;
use Nizam\Behavior\Domain\Port\BehaviorChangeProposalRepository;
use Nizam\Behavior\Domain\ProposalId;
use Nizam\Kernel\Domain\TenantId;
use PDO;

/**
 * A PDO-backed, tenant-scoped {@see BehaviorChangeProposalRepository} for SQLite and PostgreSQL.
 *
 * Proposals are stored one row per aggregate in `behavior_change_proposals`; the proposed trait set
 * and the supporting evidence are persisted as JSON documents via {@see BehaviorMapper}. Every
 * statement is parameterized and filtered by `tenant_id` and `deleted_at IS NULL`.
 * {@see self::pendingForTenant()} returns only still-pending proposals, ordered by `proposed_at`.
 * Writes upsert on the primary key with a portable "update, else insert" strategy.
 */
final class PdoBehaviorChangeProposalRepository implements BehaviorChangeProposalRepository
{
    /**
     * @param PDO           $connection The database connection (SQLite or PostgreSQL).
     * @param BehaviorMapper $mapper     The row <-> aggregate translator.
     */
    public function __construct(
        private readonly PDO $connection,
        private readonly BehaviorMapper $mapper,
    ) {
    }

    /**
     * Persist a proposal, inserting when new and updating in place otherwise.
     */
    public function save(BehaviorChangeProposal $proposal): void
    {
        $row = $this->mapper->proposalToRow($proposal);

        $update = $this->connection->prepare(
            'UPDATE behavior_change_proposals
                SET status = :status,
                    decided_by = :decided_by,
                    decided_at = :decided_at,
                    updated_at = :updated_at,
                    updated_by = :updated_by,
                    version = :version
              WHERE id = :id
                AND tenant_id = :tenant_id',
        );
        $update->execute([
            'status' => $row['status'],
            'decided_by' => $row['decided_by'],
            'decided_at' => $row['decided_at'],
            'updated_at' => $row['updated_at'],
            'updated_by' => $row['updated_by'],
            'version' => $row['version'],
            'id' => $row['id'],
            'tenant_id' => $row['tenant_id'],
        ]);

        if ($update->rowCount() > 0) {
            return;
        }

        $insert = $this->connection->prepare(
            'INSERT INTO behavior_change_proposals
                (id, tenant_id, role_id, profile_id, proposed_traits, rationale, supporting_evidence,
                 confidence, business_impact, rollback_to_version, status, proposed_by, proposed_at,
                 decided_by, decided_at, created_at, updated_at, created_by, updated_by, deleted_at, version)
             VALUES
                (:id, :tenant_id, :role_id, :profile_id, :proposed_traits, :rationale, :supporting_evidence,
                 :confidence, :business_impact, :rollback_to_version, :status, :proposed_by, :proposed_at,
                 :decided_by, :decided_at, :created_at, :updated_at, :created_by, :updated_by, NULL, :version)',
        );
        $insert->execute([
            'id' => $row['id'],
            'tenant_id' => $row['tenant_id'],
            'role_id' => $row['role_id'],
            'profile_id' => $row['profile_id'],
            'proposed_traits' => $row['proposed_traits'],
            'rationale' => $row['rationale'],
            'supporting_evidence' => $row['supporting_evidence'],
            'confidence' => $row['confidence'],
            'business_impact' => $row['business_impact'],
            'rollback_to_version' => $row['rollback_to_version'],
            'status' => $row['status'],
            'proposed_by' => $row['proposed_by'],
            'proposed_at' => $row['proposed_at'],
            'decided_by' => $row['decided_by'],
            'decided_at' => $row['decided_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'created_by' => $row['created_by'],
            'updated_by' => $row['updated_by'],
            'version' => $row['version'],
        ]);
    }

    /**
     * Load a proposal by id within a tenant, or null when none exists (or it is soft-deleted).
     */
    public function ofId(TenantId $tenantId, ProposalId $id): ?BehaviorChangeProposal
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM behavior_change_proposals
              WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL
              LIMIT 1',
        );
        $statement->execute([
            'id' => $id->toString(),
            'tenant_id' => $tenantId->toString(),
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->mapper->proposalFromRow($row);
    }

    /**
     * List the pending proposals within a tenant, ordered by when they were proposed.
     *
     * @return list<BehaviorChangeProposal>
     */
    public function pendingForTenant(TenantId $tenantId): array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM behavior_change_proposals
              WHERE tenant_id = :tenant_id AND status = :status AND deleted_at IS NULL
              ORDER BY proposed_at ASC, id ASC',
        );
        $statement->execute([
            'tenant_id' => $tenantId->toString(),
            'status' => ProposalStatus::Pending->value,
        ]);

        $proposals = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $proposals[] = $this->mapper->proposalFromRow($row);
        }

        return $proposals;
    }

    /**
     * Mint the next identity for a new proposal.
     */
    public function nextIdentity(): ProposalId
    {
        return ProposalId::generate();
    }
}
