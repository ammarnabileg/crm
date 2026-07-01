<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Persistence\Pdo;

use DateTimeImmutable;
use Nizam\Platform\Support\Json;
use Nizam\Runtime\Execution\Domain\Execution;

/**
 * Translates an {@see Execution} aggregate into the scalar `executions` snapshot row.
 *
 * The `executions` table stores a *queryable projection* of each aggregate — its current state, tenant,
 * intent, denormalized metadata/cost/performance/timeline as JSON, audit and optimistic-version columns,
 * and a soft-delete marker — so callers can filter by `(tenant_id, state)` and list a tenant's live
 * executions without replaying the event stream. The authoritative state is always the append-only
 * history; this mapper only produces the snapshot columns written alongside it. Rehydration of the
 * aggregate is done by {@see PdoExecutionRepository} from the event stream, not from this row, so the
 * snapshot can never drift the aggregate's behaviour.
 */
final class ExecutionRowMapper
{
    /**
     * Project an execution into its snapshot-row columns.
     *
     * @return array{
     *     id: string,
     *     tenant_id: string,
     *     user_id: string|null,
     *     department_ref: string|null,
     *     manager_ref: string|null,
     *     intent_ref: string,
     *     state: string,
     *     metadata: string,
     *     cost: string,
     *     performance: string,
     *     timeline: string,
     *     attempts: int,
     *     created_at: string,
     *     updated_at: string,
     *     version: int
     * }
     */
    public function toRow(Execution $execution): array
    {
        $metadata = $execution->metadata();

        return [
            'id' => $execution->executionId()->toString(),
            'tenant_id' => $metadata->tenantId()->toString(),
            'user_id' => $metadata->userId()?->toString(),
            'department_ref' => $metadata->departmentRef(),
            'manager_ref' => $metadata->managerRef(),
            'intent_ref' => $metadata->intentRef(),
            'state' => $execution->state()->value,
            'metadata' => Json::encode($metadata->toArray()),
            'cost' => Json::encode($execution->cost()->toArray()),
            'performance' => Json::encode($execution->performance()->toArray()),
            'timeline' => Json::encode($execution->timeline()->toArray()),
            'attempts' => $execution->attempts(),
            'created_at' => $execution->createdAt()->format(DateTimeImmutable::ATOM),
            'updated_at' => $execution->updatedAt()->format(DateTimeImmutable::ATOM),
            'version' => $execution->version(),
        ];
    }
}
