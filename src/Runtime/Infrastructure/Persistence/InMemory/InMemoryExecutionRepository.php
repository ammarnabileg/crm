<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Persistence\InMemory;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\Port\ExecutionRepository;

/**
 * A real, single-process {@see ExecutionRepository} for tests and safe defaults.
 *
 * It keeps the current snapshot of each {@see Execution} in an in-memory map keyed by
 * {@see ExecutionId}, upserting on {@see self::save()} and returning a stored aggregate only to its
 * owning tenant on {@see self::ofTenant()}. Unlike the combined orchestration test store, this adapter
 * implements exactly one domain port, matching the production PDO split so the same wiring works with
 * either driver. Saves are idempotent upserts; tenant lookups return most-recent-first. It is a working
 * repository, not a stub — the durable equivalent is {@see \Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionRepository}.
 */
final class InMemoryExecutionRepository implements ExecutionRepository
{
    /** @var array<string, Execution> */
    private array $executions = [];

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
}
