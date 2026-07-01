<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application;

use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ValueObject\CostSnapshot;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;

/**
 * The application service that aggregates the monetary and token cost of an execution's work.
 *
 * The {@see Execution} aggregate already folds each completed step's cost into its running
 * {@see CostSnapshot}; this service is the read-side complement. It exposes the execution's accrued
 * cost, and can independently total a set of {@see WorkerResult}s — for example the results the
 * coordinator collected before they are committed to the aggregate — into a single snapshot with a
 * per-provider breakdown keyed by the automation each worker selected. It is pure and performs no I/O.
 */
final class CostTracker
{
    /**
     * The cost an execution has accrued so far.
     */
    public function costOf(Execution $execution): CostSnapshot
    {
        return $execution->cost();
    }

    /**
     * Total a batch of worker results into a single cost snapshot.
     *
     * Token counts are read from each result's `tokens` resource entry (defaulting to zero when absent
     * or invalid); currency micros sum across results; the per-provider breakdown accumulates each
     * result's cost under the automation it selected (results with no selected automation contribute to
     * the total but not to any provider bucket).
     *
     * @param list<WorkerResult> $results The worker results to total.
     */
    public function aggregate(array $results): CostSnapshot
    {
        $snapshot = CostSnapshot::zero();
        foreach ($results as $result) {
            $provider = $result->automationSelected();
            $breakdown = $provider !== null ? [$provider => $result->executionCostMicros()] : [];
            $snapshot = $snapshot->add(
                $this->tokensFrom($result),
                $result->executionCostMicros(),
                $breakdown,
            );
        }

        return $snapshot;
    }

    /**
     * The non-negative token count a result reports under its resource map, defaulting to zero.
     */
    private function tokensFrom(WorkerResult $result): int
    {
        $tokens = $result->resourcesUsed()['tokens'] ?? 0;

        return is_int($tokens) && $tokens >= 0 ? $tokens : 0;
    }
}
