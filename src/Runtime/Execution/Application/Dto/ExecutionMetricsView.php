<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Dto;

use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ValueObject\CostSnapshot;
use Nizam\Runtime\Execution\Domain\ValueObject\PerformanceSnapshot;

/**
 * A flat, read-only projection of an execution's cost and performance for dashboards and reporting.
 *
 * The view flattens an execution's {@see CostSnapshot} (tokens, total currency micros, per-provider
 * breakdown) and {@see PerformanceSnapshot} (wall-clock and CPU time, step and retry counts) into
 * scalars. It is what the cost/performance trackers surface and what the Execution Monitor renders as
 * "how much did this run cost and how long did it take", never leaking a domain value object.
 */
final class ExecutionMetricsView
{
    /**
     * @param string             $executionId       The execution the metrics belong to.
     * @param int                $tokens            The total tokens consumed.
     * @param int                $currencyMicros    The total monetary cost in currency micros.
     * @param array<string, int> $providerBreakdown The per-provider currency-micros breakdown.
     * @param int                $wallMs            The total wall-clock time in milliseconds.
     * @param int|null           $cpuMs             The total CPU time in milliseconds, if measured.
     * @param int                $stepCount         The number of steps executed.
     * @param int                $retryCount        The number of retries performed.
     */
    public function __construct(
        public readonly string $executionId,
        public readonly int $tokens,
        public readonly int $currencyMicros,
        public readonly array $providerBreakdown,
        public readonly int $wallMs,
        public readonly ?int $cpuMs,
        public readonly int $stepCount,
        public readonly int $retryCount,
    ) {
    }

    /**
     * Project an execution's snapshots into a metrics view.
     */
    public static function fromExecution(Execution $execution): self
    {
        return self::fromSnapshots(
            $execution->executionId()->toString(),
            $execution->cost(),
            $execution->performance(),
        );
    }

    /**
     * Project raw cost and performance snapshots into a metrics view.
     */
    public static function fromSnapshots(
        string $executionId,
        CostSnapshot $cost,
        PerformanceSnapshot $performance,
    ): self {
        return new self(
            executionId: $executionId,
            tokens: $cost->tokens(),
            currencyMicros: $cost->currencyMicros(),
            providerBreakdown: $cost->providerBreakdown(),
            wallMs: $performance->wallMs(),
            cpuMs: $performance->cpuMs(),
            stepCount: $performance->stepCount(),
            retryCount: $performance->retryCount(),
        );
    }

    /**
     * A scalar-only representation suitable for JSON serialization and transport.
     *
     * @return array{
     *     executionId: string,
     *     tokens: int,
     *     currencyMicros: int,
     *     providerBreakdown: array<string, int>,
     *     wallMs: int,
     *     cpuMs: int|null,
     *     stepCount: int,
     *     retryCount: int
     * }
     */
    public function toArray(): array
    {
        return [
            'executionId' => $this->executionId,
            'tokens' => $this->tokens,
            'currencyMicros' => $this->currencyMicros,
            'providerBreakdown' => $this->providerBreakdown,
            'wallMs' => $this->wallMs,
            'cpuMs' => $this->cpuMs,
            'stepCount' => $this->stepCount,
            'retryCount' => $this->retryCount,
        ];
    }
}
