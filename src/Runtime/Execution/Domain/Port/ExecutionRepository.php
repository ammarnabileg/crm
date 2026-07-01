<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Port;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionId;

/**
 * The persistence port for {@see Execution} aggregates.
 *
 * The domain declares this interface; infrastructure adapters (in-memory, PDO) implement it. It
 * stores and retrieves the current snapshot of an execution. All lookups are tenant-aware — an
 * execution is only ever returned to its owning tenant — so callers cannot read across tenant
 * boundaries. Implementations must treat saves as idempotent upserts keyed by {@see ExecutionId}.
 */
interface ExecutionRepository
{
    /**
     * Persist the current state of an execution (insert or update).
     */
    public function save(Execution $execution): void;

    /**
     * Retrieve an execution by its identity, or null when none exists (or it is soft-deleted).
     */
    public function ofId(ExecutionId $id): ?Execution;

    /**
     * Retrieve all live executions belonging to a tenant, most-recent first.
     *
     * @return list<Execution>
     */
    public function ofTenant(TenantId $tenantId): array;
}
