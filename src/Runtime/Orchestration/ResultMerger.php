<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration;

use Nizam\Runtime\Execution\Domain\ValueObject\EvidenceItem;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;
use Nizam\Runtime\Orchestration\ValueObject\MergedResult;

/**
 * Folds many {@see WorkerResult}s into one consolidated {@see MergedResult} for the manager to decide on.
 *
 * Workers each return their own structured output; the manager needs a single, coherent view. This
 * service is a pure function of its inputs — no I/O, no clock, no randomness — so a given set of results
 * always merges identically. It preserves the individual results in submission order, de-duplicates
 * evidence by {@see EvidenceItem::dedupeKey()} (keeping the first occurrence), concatenates and
 * de-duplicates recommendations/warnings/errors while preserving first-seen order, sums execution time
 * and cost, and computes the aggregate confidence as the mean of the contributing confidences (0.0 when
 * there are no results). It is a spec-mandated step on the orchestration timeline.
 */
final class ResultMerger
{
    /**
     * Merge a list of worker results into a single consolidated result.
     *
     * @param list<WorkerResult> $results The worker results to merge, in submission order.
     *
     * @return MergedResult The consolidated view.
     */
    public function merge(array $results): MergedResult
    {
        $evidence = $this->dedupeEvidence($results);
        $recommendations = $this->collectStrings($results, static fn (WorkerResult $r): array => $r->recommendations());
        $warnings = $this->collectStrings($results, static fn (WorkerResult $r): array => $r->warnings());
        $errors = $this->collectStrings($results, static fn (WorkerResult $r): array => $r->errors());

        $totalTimeMs = 0;
        $totalCostMicros = 0;
        $confidenceSum = 0.0;
        foreach ($results as $result) {
            $totalTimeMs += $result->executionTimeMs();
            $totalCostMicros += $result->executionCostMicros();
            $confidenceSum += $result->confidence();
        }

        $aggregateConfidence = $results === [] ? 0.0 : $confidenceSum / count($results);

        return new MergedResult(
            results: array_values($results),
            evidence: $evidence,
            recommendations: $recommendations,
            warnings: $warnings,
            errors: $errors,
            aggregateConfidence: $aggregateConfidence,
            totalTimeMs: $totalTimeMs,
            totalCostMicros: $totalCostMicros,
        );
    }

    /**
     * De-duplicate evidence across all results by dedupe key, keeping the first occurrence.
     *
     * @param list<WorkerResult> $results
     *
     * @return list<EvidenceItem>
     */
    private function dedupeEvidence(array $results): array
    {
        $seen = [];
        $unique = [];
        foreach ($results as $result) {
            foreach ($result->evidence() as $item) {
                $key = $item->dedupeKey();
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $unique[] = $item;
            }
        }

        return $unique;
    }

    /**
     * Collect a string projection across all results, de-duplicating while preserving first-seen order.
     *
     * @param list<WorkerResult>               $results
     * @param callable(WorkerResult): list<string> $project
     *
     * @return list<string>
     */
    private function collectStrings(array $results, callable $project): array
    {
        $seen = [];
        $collected = [];
        foreach ($results as $result) {
            foreach ($project($result) as $value) {
                if (isset($seen[$value])) {
                    continue;
                }
                $seen[$value] = true;
                $collected[] = $value;
            }
        }

        return $collected;
    }
}
