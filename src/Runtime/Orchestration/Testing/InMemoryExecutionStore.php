<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Testing;

use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventPublisher;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventStore;
use Nizam\Runtime\Execution\Domain\Port\ExecutionRepository;

/**
 * A real, single-process backing store combining the execution snapshot repository, the append-only
 * event store, and the event publisher for the Runtime's own tests and safe defaults.
 *
 * The production Runtime splits these into separate PDO adapters (a future phase); for isolation tests
 * of the orchestration layer a single in-process store that faithfully implements all three domain ports
 * is enough, and keeps the wiring one object. Snapshots are upserted by {@see ExecutionId} and returned
 * only to their owning tenant; events are appended in order and streamable back in that order; published
 * events are captured so a test can assert what the engine announced. This is a working store, not a
 * stub — it honours the tenant-scoping and append-only contracts the ports require.
 */
final class InMemoryExecutionStore implements ExecutionRepository, ExecutionEventStore, ExecutionEventPublisher
{
    /** @var array<string, Execution> */
    private array $executions = [];

    /** @var array<string, list<DomainEvent>> */
    private array $events = [];

    /** @var list<DomainEvent> */
    private array $published = [];

    /**
     * {@inheritDoc}
     */
    public function save(Execution $execution): void
    {
        $this->executions[$execution->executionId()->toString()] = $execution;
    }

    /**
     * {@inheritDoc}
     */
    public function ofId(ExecutionId $id): ?Execution
    {
        return $this->executions[$id->toString()] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function ofTenant(TenantId $tenantId): array
    {
        $matches = [];
        foreach ($this->executions as $execution) {
            if ($execution->metadata()->tenantId()->equals($tenantId)) {
                $matches[] = $execution;
            }
        }

        return array_reverse($matches);
    }

    /**
     * {@inheritDoc}
     */
    public function append(ExecutionId $executionId, array $events): void
    {
        if ($events === []) {
            return;
        }

        $key = $executionId->toString();
        $this->events[$key] = [...($this->events[$key] ?? []), ...array_values($events)];
    }

    /**
     * {@inheritDoc}
     */
    public function stream(ExecutionId $executionId): array
    {
        return $this->events[$executionId->toString()] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->published[] = $event;
        }
    }

    /**
     * Every event the engine published through this store, in publication order.
     *
     * @return list<DomainEvent>
     */
    public function publishedEvents(): array
    {
        return $this->published;
    }
}
