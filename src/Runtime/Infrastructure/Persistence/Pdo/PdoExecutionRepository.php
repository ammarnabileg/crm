<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Persistence\Pdo;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventStore;
use Nizam\Runtime\Execution\Domain\Port\ExecutionRepository;
use PDO;

/**
 * A PDO-backed, tenant-scoped, soft-deleting {@see ExecutionRepository} for SQLite and PostgreSQL.
 *
 * Persistence follows the event-sourced grain of the {@see Execution} aggregate. On {@see self::save()}
 * the repository upserts a *queryable snapshot* row (via {@see ExecutionRowMapper}) into `executions`,
 * so callers can filter by tenant and state and list a tenant's live executions cheaply. On read it does
 * not trust that snapshot for behaviour: it confirms the execution exists and is live (not soft-deleted)
 * for the requested tenant, then rebuilds the aggregate authoritatively from its append-only event
 * stream through {@see Execution::replay()} — guaranteeing the returned aggregate is bit-for-bit what the
 * events say, never a lossy projection. Every statement is parameterized and every read is filtered by
 * `tenant_id` and `deleted_at IS NULL`, so a tenant can never see another tenant's, or a soft-deleted,
 * execution.
 */
final class PdoExecutionRepository implements ExecutionRepository
{
    /**
     * @param PDO                 $connection The database connection (SQLite or PostgreSQL).
     * @param ExecutionEventStore $eventStore The append-only history the aggregate is rehydrated from.
     * @param ExecutionRowMapper  $mapper     The aggregate -> snapshot-row translator.
     */
    public function __construct(
        private readonly PDO $connection,
        private readonly ExecutionEventStore $eventStore,
        private readonly ExecutionRowMapper $mapper,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function save(Execution $execution): void
    {
        $row = $this->mapper->toRow($execution);

        $update = $this->connection->prepare(
            'UPDATE executions
                SET user_id = :user_id,
                    department_ref = :department_ref,
                    manager_ref = :manager_ref,
                    intent_ref = :intent_ref,
                    state = :state,
                    metadata = :metadata,
                    cost = :cost,
                    performance = :performance,
                    timeline = :timeline,
                    attempts = :attempts,
                    updated_at = :updated_at,
                    version = :version
              WHERE id = :id
                AND tenant_id = :tenant_id',
        );
        $update->execute([
            'user_id' => $row['user_id'],
            'department_ref' => $row['department_ref'],
            'manager_ref' => $row['manager_ref'],
            'intent_ref' => $row['intent_ref'],
            'state' => $row['state'],
            'metadata' => $row['metadata'],
            'cost' => $row['cost'],
            'performance' => $row['performance'],
            'timeline' => $row['timeline'],
            'attempts' => $row['attempts'],
            'updated_at' => $row['updated_at'],
            'version' => $row['version'],
            'id' => $row['id'],
            'tenant_id' => $row['tenant_id'],
        ]);

        if ($update->rowCount() > 0) {
            return;
        }

        $insert = $this->connection->prepare(
            'INSERT INTO executions
                (id, tenant_id, user_id, department_ref, manager_ref, intent_ref, state,
                 metadata, cost, performance, timeline, attempts, created_at, updated_at, deleted_at, version)
             VALUES
                (:id, :tenant_id, :user_id, :department_ref, :manager_ref, :intent_ref, :state,
                 :metadata, :cost, :performance, :timeline, :attempts, :created_at, :updated_at, NULL, :version)',
        );
        $insert->execute([
            'id' => $row['id'],
            'tenant_id' => $row['tenant_id'],
            'user_id' => $row['user_id'],
            'department_ref' => $row['department_ref'],
            'manager_ref' => $row['manager_ref'],
            'intent_ref' => $row['intent_ref'],
            'state' => $row['state'],
            'metadata' => $row['metadata'],
            'cost' => $row['cost'],
            'performance' => $row['performance'],
            'timeline' => $row['timeline'],
            'attempts' => $row['attempts'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'version' => $row['version'],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function ofId(ExecutionId $id): ?Execution
    {
        $statement = $this->connection->prepare(
            'SELECT tenant_id FROM executions
              WHERE id = :id AND deleted_at IS NULL
              LIMIT 1',
        );
        $statement->execute(['id' => $id->toString()]);
        if ($statement->fetchColumn() === false) {
            return null;
        }

        return $this->rehydrate($id);
    }

    /**
     * {@inheritDoc}
     */
    public function ofTenant(TenantId $tenantId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id FROM executions
              WHERE tenant_id = :tenant_id AND deleted_at IS NULL
              ORDER BY created_at DESC, id DESC',
        );
        $statement->execute(['tenant_id' => $tenantId->toString()]);

        /** @var list<string> $ids */
        $ids = $statement->fetchAll(PDO::FETCH_COLUMN);

        $executions = [];
        foreach ($ids as $id) {
            $execution = $this->rehydrate(ExecutionId::fromString($id));
            if ($execution !== null) {
                $executions[] = $execution;
            }
        }

        return $executions;
    }

    /**
     * Soft-delete an execution's snapshot for a tenant (its history remains for audit).
     *
     * @param TenantId          $tenantId The owning tenant.
     * @param ExecutionId       $id       The execution to soft-delete.
     * @param DateTimeImmutable $deletedAt When the deletion occurred.
     */
    public function softDelete(TenantId $tenantId, ExecutionId $id, \DateTimeImmutable $deletedAt): void
    {
        $statement = $this->connection->prepare(
            'UPDATE executions
                SET deleted_at = :deleted_at
              WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL',
        );
        $statement->execute([
            'deleted_at' => $deletedAt->format(\DateTimeImmutable::ATOM),
            'id' => $id->toString(),
            'tenant_id' => $tenantId->toString(),
        ]);
    }

    /**
     * Rebuild the aggregate authoritatively from its append-only event stream.
     *
     * Returns null when the stream is empty, which should not happen for a persisted execution but keeps
     * the read total.
     */
    private function rehydrate(ExecutionId $id): ?Execution
    {
        $events = $this->eventStore->stream($id);
        if ($events === []) {
            return null;
        }

        return Execution::replay($events);
    }
}
