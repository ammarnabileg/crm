<?php

declare(strict_types=1);

namespace Nizam\Behavior\Infrastructure\Persistence\Pdo;

use DateTimeImmutable;
use Nizam\Behavior\Domain\BehaviorObservation;
use Nizam\Behavior\Domain\Port\ObservationSource;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\TenantId;
use PDO;

/**
 * A PDO-backed {@see ObservationSource} reading approved practice from `behavior_observations`.
 *
 * The engine only ever learns from approved observations, so this read-side adapter filters on
 * `approved_at IS NOT NULL` in addition to `tenant_id`, `role_id`, `deleted_at IS NULL`, and — when
 * supplied — an `occurred_at` lower bound. Results are ordered by `occurred_at` for deterministic
 * consolidation. Each row is materialized into an approved {@see BehaviorObservation} by
 * {@see BehaviorMapper}. A companion writer, {@see self::record()}, lets fixtures and integration
 * tests seed approved observations through the same schema.
 */
final class PdoObservationSource implements ObservationSource
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
     * The approved observations for a role within a tenant, optionally since a point in time.
     *
     * @return list<BehaviorObservation>
     */
    public function approvedObservationsForRole(
        TenantId $tenantId,
        RoleId $roleId,
        ?DateTimeImmutable $since = null,
    ): array {
        $sql =
            'SELECT * FROM behavior_observations
              WHERE tenant_id = :tenant_id
                AND role_id = :role_id
                AND approved_at IS NOT NULL
                AND deleted_at IS NULL';

        $parameters = [
            'tenant_id' => $tenantId->toString(),
            'role_id' => $roleId->toString(),
        ];

        if ($since !== null) {
            $sql .= ' AND occurred_at >= :since';
            $parameters['since'] = $since->format(DateTimeImmutable::ATOM);
        }

        $sql .= ' ORDER BY occurred_at ASC, id ASC';

        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        $observations = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $observations[] = $this->mapper->observationFromRow($row);
        }

        return $observations;
    }

    /**
     * Persist an approved observation so it becomes available to the read side.
     *
     * The primary channel for observations is the wider platform; this writer exists so integration
     * tests and back-fills can seed approved practice through the same schema the reads use. Inserting
     * on the primary key with a portable "update, else insert" strategy makes the call idempotent.
     */
    public function record(BehaviorObservation $observation): void
    {
        $row = $this->mapper->observationToRow($observation);

        $update = $this->connection->prepare(
            'UPDATE behavior_observations
                SET source_type = :source_type,
                    reference_id = :reference_id,
                    summary = :summary,
                    occurred_at = :occurred_at,
                    weight = :weight,
                    observed_traits = :observed_traits,
                    approved_by = :approved_by,
                    approved_at = :approved_at
              WHERE id = :id
                AND tenant_id = :tenant_id',
        );
        $update->execute([
            'source_type' => $row['source_type'],
            'reference_id' => $row['reference_id'],
            'summary' => $row['summary'],
            'occurred_at' => $row['occurred_at'],
            'weight' => $row['weight'],
            'observed_traits' => $row['observed_traits'],
            'approved_by' => $row['approved_by'],
            'approved_at' => $row['approved_at'],
            'id' => $row['id'],
            'tenant_id' => $row['tenant_id'],
        ]);

        if ($update->rowCount() > 0) {
            return;
        }

        $insert = $this->connection->prepare(
            'INSERT INTO behavior_observations
                (id, tenant_id, role_id, source_type, reference_id, summary, occurred_at, weight,
                 observed_traits, approved_by, approved_at, created_at, created_by, deleted_at)
             VALUES
                (:id, :tenant_id, :role_id, :source_type, :reference_id, :summary, :occurred_at, :weight,
                 :observed_traits, :approved_by, :approved_at, :created_at, :created_by, NULL)',
        );
        $insert->execute([
            'id' => $row['id'],
            'tenant_id' => $row['tenant_id'],
            'role_id' => $row['role_id'],
            'source_type' => $row['source_type'],
            'reference_id' => $row['reference_id'],
            'summary' => $row['summary'],
            'occurred_at' => $row['occurred_at'],
            'weight' => $row['weight'],
            'observed_traits' => $row['observed_traits'],
            'approved_by' => $row['approved_by'],
            'approved_at' => $row['approved_at'],
            'created_at' => $row['created_at'],
            'created_by' => $row['created_by'],
        ]);
    }
}
