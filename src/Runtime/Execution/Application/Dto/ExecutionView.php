<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Dto;

use DateTimeInterface;
use Nizam\Runtime\Execution\Domain\Execution;

/**
 * A flat, read-only projection of an {@see Execution} aggregate for callers outside the domain.
 *
 * The view exposes an execution's identity, its {@see \Nizam\Runtime\Execution\Domain\ValueObject\ExecutionMetadata}
 * (tenant, user, department, manager, intent, labels), current lifecycle state, its steps, its
 * human-readable timeline, its cost and performance metrics, attempt counter, timestamps, and version
 * — all as scalars and plain arrays. It is what queries return: a transport-safe snapshot that never
 * leaks a domain object or lets a caller mutate an execution.
 */
final class ExecutionView
{
    /**
     * @param string                     $executionId The execution's identity.
     * @param string                     $tenantId    The owning tenant.
     * @param string|null                $userId      The acting user, if any.
     * @param string|null                $departmentRef The department reference, if resolved.
     * @param string|null                $managerRef  The manager plugin reference, if resolved.
     * @param string                     $intentRef   The intent the execution fulfils.
     * @param array<string, string>      $labels      The routing/reporting labels.
     * @param string                     $state       The current lifecycle state value.
     * @param list<ExecutionStepView>    $steps       The execution's steps as views.
     * @param ExecutionTimelineView      $timeline    The human-readable path taken.
     * @param ExecutionMetricsView       $metrics     The cost and performance metrics.
     * @param int                        $attempts    How many attempts have been made (1-based).
     * @param string                     $createdAt   When the execution was created (ISO-8601).
     * @param string                     $updatedAt   When it last changed (ISO-8601).
     * @param int                        $version     The optimistic-concurrency version.
     */
    public function __construct(
        public readonly string $executionId,
        public readonly string $tenantId,
        public readonly ?string $userId,
        public readonly ?string $departmentRef,
        public readonly ?string $managerRef,
        public readonly string $intentRef,
        public readonly array $labels,
        public readonly string $state,
        public readonly array $steps,
        public readonly ExecutionTimelineView $timeline,
        public readonly ExecutionMetricsView $metrics,
        public readonly int $attempts,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly int $version,
    ) {
    }

    /**
     * Project a domain execution into its read-only view.
     */
    public static function fromDomain(Execution $execution): self
    {
        $metadata = $execution->metadata();

        $steps = [];
        foreach ($execution->steps() as $step) {
            $steps[] = ExecutionStepView::fromDomain($step);
        }

        return new self(
            executionId: $execution->executionId()->toString(),
            tenantId: $metadata->tenantId()->toString(),
            userId: $metadata->userId()?->toString(),
            departmentRef: $metadata->departmentRef(),
            managerRef: $metadata->managerRef(),
            intentRef: $metadata->intentRef(),
            labels: $metadata->labels(),
            state: $execution->state()->value,
            steps: $steps,
            timeline: ExecutionTimelineView::fromDomain($execution->executionId(), $execution->timeline()),
            metrics: ExecutionMetricsView::fromExecution($execution),
            attempts: $execution->attempts(),
            createdAt: $execution->createdAt()->format(DateTimeInterface::ATOM),
            updatedAt: $execution->updatedAt()->format(DateTimeInterface::ATOM),
            version: $execution->version(),
        );
    }

    /**
     * A scalar-only representation suitable for JSON serialization and transport.
     *
     * @return array{
     *     executionId: string,
     *     tenantId: string,
     *     userId: string|null,
     *     departmentRef: string|null,
     *     managerRef: string|null,
     *     intentRef: string,
     *     labels: array<string, string>,
     *     state: string,
     *     steps: list<array<string, mixed>>,
     *     timeline: array{executionId: string, entries: list<array{state: string, at: string, note: string}>},
     *     metrics: array<string, mixed>,
     *     attempts: int,
     *     createdAt: string,
     *     updatedAt: string,
     *     version: int
     * }
     */
    public function toArray(): array
    {
        return [
            'executionId' => $this->executionId,
            'tenantId' => $this->tenantId,
            'userId' => $this->userId,
            'departmentRef' => $this->departmentRef,
            'managerRef' => $this->managerRef,
            'intentRef' => $this->intentRef,
            'labels' => $this->labels,
            'state' => $this->state,
            'steps' => array_map(
                static fn (ExecutionStepView $step): array => $step->toArray(),
                $this->steps,
            ),
            'timeline' => $this->timeline->toArray(),
            'metrics' => $this->metrics->toArray(),
            'attempts' => $this->attempts,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'version' => $this->version,
        ];
    }
}
