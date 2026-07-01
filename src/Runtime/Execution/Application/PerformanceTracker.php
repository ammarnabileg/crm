<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application;

use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ValueObject\PerformanceSnapshot;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;

/**
 * The application service that aggregates the timing and throughput of an execution's work.
 *
 * The {@see Execution} aggregate already folds each completed step's wall-clock time and bumps its
 * step and retry counters into its running {@see PerformanceSnapshot}; this service is the read-side
 * complement. It exposes the execution's accrued performance, and can independently total a set of
 * {@see WorkerResult}s — summing their reported execution times and counting them as steps — into a
 * single snapshot. It is pure and performs no I/O.
 */
final class PerformanceTracker
{
    /**
     * The performance an execution has accrued so far.
     */
    public function performanceOf(Execution $execution): PerformanceSnapshot
    {
        return $execution->performance();
    }

    /**
     * Total a batch of worker results into a single performance snapshot.
     *
     * Each result contributes its reported execution time as one step's wall-clock time; the step
     * count therefore equals the number of results. Retries are not inferred from results, so the
     * returned snapshot reports zero retries.
     *
     * @param list<WorkerResult> $results The worker results to total.
     */
    public function aggregate(array $results): PerformanceSnapshot
    {
        $snapshot = PerformanceSnapshot::zero();
        foreach ($results as $result) {
            $snapshot = $snapshot->recordStep($result->executionTimeMs());
        }

        return $snapshot;
    }
}
