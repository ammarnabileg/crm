<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Persistence\Pdo;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Support\Uuid;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventStore;
use PDO;

/**
 * A PDO-backed, append-only, tenant-scoped {@see ExecutionEventStore} for SQLite and PostgreSQL.
 *
 * Every event an execution records is inserted as one immutable row in `execution_history`, carrying a
 * monotonically increasing per-execution sequence number, the owning tenant, the event's stable name,
 * its JSON payload (JSONB in Postgres, TEXT-encoded JSON in SQLite), and its occurrence instant. The
 * store never updates or deletes a row — it is strictly append-only, the audit-grade substrate for
 * {@see \Nizam\Runtime\Execution\Domain\Execution::replay()}, recovery, and replay. A stream reads the
 * rows back ordered by sequence and rebuilds each concrete event through the
 * {@see ExecutionEventSerializer}. The tenant an execution's history belongs to is resolved from its
 * first (birth) event so appends stay tenant-scoped without the caller passing a tenant.
 */
final class PdoExecutionEventStore implements ExecutionEventStore
{
    /**
     * @param PDO                      $connection The database connection (SQLite or PostgreSQL).
     * @param ExecutionEventSerializer $serializer The event <-> payload translator.
     */
    public function __construct(
        private readonly PDO $connection,
        private readonly ExecutionEventSerializer $serializer,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function append(ExecutionId $executionId, array $events): void
    {
        if ($events === []) {
            return;
        }

        $executionKey = $executionId->toString();
        $tenantId = $this->resolveTenantId($executionKey, $events);
        $sequence = $this->nextSequence($executionKey);

        $insert = $this->connection->prepare(
            'INSERT INTO execution_history
                (id, tenant_id, execution_id, sequence_no, event_name, payload, occurred_at, recorded_at)
             VALUES
                (:id, :tenant_id, :execution_id, :sequence_no, :event_name, :payload, :occurred_at, :recorded_at)',
        );

        $recordedAt = (new DateTimeImmutable())->format(DateTimeImmutable::ATOM);

        foreach ($events as $event) {
            $serialized = $this->serializer->serialize($event);
            $insert->execute([
                'id' => Uuid::v7(),
                'tenant_id' => $tenantId,
                'execution_id' => $executionKey,
                'sequence_no' => $sequence,
                'event_name' => $serialized['name'],
                'payload' => $this->serializer->encode($event),
                'occurred_at' => $event->occurredAt()->format(DateTimeImmutable::ATOM),
                'recorded_at' => $recordedAt,
            ]);
            ++$sequence;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function stream(ExecutionId $executionId): array
    {
        $statement = $this->connection->prepare(
            'SELECT event_name, payload, occurred_at
               FROM execution_history
              WHERE execution_id = :execution_id
              ORDER BY sequence_no ASC',
        );
        $statement->execute(['execution_id' => $executionId->toString()]);

        /** @var list<array{event_name: string, payload: string, occurred_at: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        foreach ($rows as $row) {
            $events[] = $this->serializer->deserialize(
                (string) $row['event_name'],
                (string) $row['payload'],
                new DateTimeImmutable((string) $row['occurred_at']),
            );
        }

        return $events;
    }

    /**
     * Resolve the tenant an execution's history belongs to, preferring an already-stored birth event.
     *
     * On the first append the birth {@see \Nizam\Runtime\Execution\Domain\Event\ExecutionStarted} carries
     * the tenant in its metadata; on later appends the tenant is read back from the stored history so every
     * row for an execution shares one tenant id.
     *
     * @param list<DomainEvent> $events
     */
    private function resolveTenantId(string $executionKey, array $events): string
    {
        $existing = $this->connection->prepare(
            'SELECT tenant_id FROM execution_history
              WHERE execution_id = :execution_id
              ORDER BY sequence_no ASC
              LIMIT 1',
        );
        $existing->execute(['execution_id' => $executionKey]);
        $storedTenant = $existing->fetchColumn();
        if (is_string($storedTenant) && $storedTenant !== '') {
            return $storedTenant;
        }

        return $this->serializer->serialize($events[0])['payload']['metadata']['tenantId']
            ?? TenantId::generate()->toString();
    }

    /**
     * Compute the next per-execution sequence number (the current max plus one, or zero when empty).
     */
    private function nextSequence(string $executionKey): int
    {
        $statement = $this->connection->prepare(
            'SELECT MAX(sequence_no) FROM execution_history WHERE execution_id = :execution_id',
        );
        $statement->execute(['execution_id' => $executionKey]);
        $max = $statement->fetchColumn();

        return $max === null || $max === false ? 0 : ((int) $max) + 1;
    }
}
