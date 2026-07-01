<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;
use Nizam\Runtime\Execution\Domain\ValueObject\EvidenceItem;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;

/**
 * The single, consolidated view the {@see \Nizam\Runtime\Orchestration\ResultMerger} produces from many
 * {@see WorkerResult}s.
 *
 * Multiple workers each return their own structured result; the manager cannot decide on a scattered
 * pile of them. The merger folds them into this value object: the individual results are preserved, the
 * evidence is de-duplicated, recommendations/warnings/errors are combined, cost and time are summed, and
 * an aggregate confidence (the mean of the contributing confidences) is computed. The
 * {@see \Nizam\Runtime\Orchestration\ConfidenceEvaluator} scores this, and the manager decides on it.
 * Being a value object it is immutable and self-validating.
 */
final class MergedResult implements ValueObject
{
    /** @var list<WorkerResult> */
    private readonly array $results;

    /** @var list<EvidenceItem> */
    private readonly array $evidence;

    /** @var list<string> */
    private readonly array $recommendations;

    /** @var list<string> */
    private readonly array $warnings;

    /** @var list<string> */
    private readonly array $errors;

    /**
     * @param list<WorkerResult> $results         The contributing worker results, in submission order.
     * @param list<EvidenceItem> $evidence        The de-duplicated evidence across all results.
     * @param list<string>       $recommendations The combined, de-duplicated recommendations.
     * @param list<string>       $warnings        The combined, de-duplicated warnings.
     * @param list<string>       $errors          The combined, de-duplicated errors.
     * @param float              $aggregateConfidence The mean confidence across results, in [0, 1].
     * @param int                $totalTimeMs     The summed execution time across results (>= 0).
     * @param int                $totalCostMicros The summed execution cost across results (>= 0).
     */
    public function __construct(
        array $results,
        array $evidence,
        array $recommendations,
        array $warnings,
        array $errors,
        private readonly float $aggregateConfidence,
        private readonly int $totalTimeMs,
        private readonly int $totalCostMicros,
    ) {
        Assert::that(
            $aggregateConfidence >= 0.0 && $aggregateConfidence <= 1.0,
            'Aggregate confidence must be within the inclusive range [0, 1].',
        );
        Assert::that($totalTimeMs >= 0, 'Total time must not be negative.');
        Assert::that($totalCostMicros >= 0, 'Total cost must not be negative.');

        $this->results = $this->assertList($results, WorkerResult::class, 'results');
        $this->evidence = $this->assertList($evidence, EvidenceItem::class, 'evidence');
        $this->recommendations = $this->assertStrings($recommendations, 'recommendations');
        $this->warnings = $this->assertStrings($warnings, 'warnings');
        $this->errors = $this->assertStrings($errors, 'errors');
    }

    /**
     * Validate that a list contains only instances of a class, returning a re-indexed list.
     *
     * @template T of object
     * @param array<array-key, mixed> $items
     * @param class-string<T>         $class
     *
     * @return list<T>
     */
    private function assertList(array $items, string $class, string $field): array
    {
        foreach ($items as $item) {
            Assert::that($item instanceof $class, sprintf('%s must contain only %s instances.', $field, $class));
        }

        /** @var list<T> $values */
        $values = array_values($items);

        return $values;
    }

    /**
     * Validate that a list contains only strings, returning a re-indexed list.
     *
     * @param array<array-key, mixed> $items
     *
     * @return list<string>
     */
    private function assertStrings(array $items, string $field): array
    {
        foreach ($items as $item) {
            Assert::that(is_string($item), sprintf('%s must contain only strings.', $field));
        }

        /** @var list<string> $values */
        $values = array_values($items);

        return $values;
    }

    /**
     * The contributing worker results, in submission order.
     *
     * @return list<WorkerResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * The number of contributing results.
     */
    public function resultCount(): int
    {
        return count($this->results);
    }

    /**
     * The de-duplicated evidence across all results.
     *
     * @return list<EvidenceItem>
     */
    public function evidence(): array
    {
        return $this->evidence;
    }

    /**
     * The combined, de-duplicated recommendations.
     *
     * @return list<string>
     */
    public function recommendations(): array
    {
        return $this->recommendations;
    }

    /**
     * The combined, de-duplicated warnings.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * The combined, de-duplicated errors.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Whether any contributing result reported an error.
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * The mean confidence across results, in [0, 1].
     */
    public function aggregateConfidence(): float
    {
        return $this->aggregateConfidence;
    }

    /**
     * The summed execution time across results, in milliseconds.
     */
    public function totalTimeMs(): int
    {
        return $this->totalTimeMs;
    }

    /**
     * The summed execution cost across results, in currency micros.
     */
    public function totalCostMicros(): int
    {
        return $this->totalCostMicros;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        if (
            $other->aggregateConfidence !== $this->aggregateConfidence
            || $other->totalTimeMs !== $this->totalTimeMs
            || $other->totalCostMicros !== $this->totalCostMicros
            || $other->recommendations !== $this->recommendations
            || $other->warnings !== $this->warnings
            || $other->errors !== $this->errors
        ) {
            return false;
        }

        return $this->resultsEqual($other) && $this->evidenceEqual($other);
    }

    /**
     * Compare the contributing results pairwise by value.
     */
    private function resultsEqual(self $other): bool
    {
        if (count($this->results) !== count($other->results)) {
            return false;
        }

        foreach ($this->results as $index => $result) {
            if (!$result->equals($other->results[$index])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compare the evidence pairwise by value.
     */
    private function evidenceEqual(self $other): bool
    {
        if (count($this->evidence) !== count($other->evidence)) {
            return false;
        }

        foreach ($this->evidence as $index => $item) {
            if (!$item->equals($other->evidence[$index])) {
                return false;
            }
        }

        return true;
    }

    /**
     * A scalar-only representation suitable for persistence and read models.
     *
     * @return array{
     *     results: list<array<string, mixed>>,
     *     evidence: list<array{kind: string, reference: string, summary: string, capturedAt: string}>,
     *     recommendations: list<string>,
     *     warnings: list<string>,
     *     errors: list<string>,
     *     aggregateConfidence: float,
     *     totalTimeMs: int,
     *     totalCostMicros: int
     * }
     */
    public function toArray(): array
    {
        return [
            'results' => array_map(static fn (WorkerResult $r): array => $r->toArray(), $this->results),
            'evidence' => array_map(static fn (EvidenceItem $e): array => $e->toArray(), $this->evidence),
            'recommendations' => $this->recommendations,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'aggregateConfidence' => $this->aggregateConfidence,
            'totalTimeMs' => $this->totalTimeMs,
            'totalCostMicros' => $this->totalCostMicros,
        ];
    }
}
