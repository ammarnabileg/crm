<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * The self-validating, structured output a worker plugin returns for one unit of work.
 *
 * Workers never speak to users, to each other, or to automation engines directly; they return this
 * value object to the Runtime, which merges results, evaluates confidence, and lets the manager
 * decide. Every mandated field is captured: the concrete {@see self::taskResult()} payload, the
 * {@see EvidenceItem}s backing it, a human {@see self::reasoningSummary()}, a normalized
 * {@see self::confidence()} in [0, 1], the time and cost consumed, the resources and tools used, an
 * optionally selected automation, and any warnings, errors, recommendations, and logs. The object is
 * immutable and validates its own invariants at construction — most importantly that confidence is
 * within [0, 1] and that time and cost are non-negative.
 */
final class WorkerResult implements ValueObject
{
    /** @var list<EvidenceItem> */
    private readonly array $evidence;

    /** @var list<string> */
    private readonly array $toolsUsed;

    /** @var list<string> */
    private readonly array $warnings;

    /** @var list<string> */
    private readonly array $errors;

    /** @var list<string> */
    private readonly array $recommendations;

    /** @var list<string> */
    private readonly array $logs;

    /**
     * @param array<string, mixed> $taskResult         The concrete result payload of the work.
     * @param list<EvidenceItem>   $evidence           The evidence backing the result.
     * @param string               $reasoningSummary   A human-readable summary of how the result was reached.
     * @param float                $confidence         The worker's confidence in the result, in [0, 1].
     * @param int                  $executionTimeMs    Wall-clock time the work took, in milliseconds (>= 0).
     * @param int                  $executionCostMicros Monetary cost of the work in currency micros (>= 0).
     * @param array<string, mixed> $resourcesUsed      Free-form map of resources consumed (e.g. token counts).
     * @param string|null          $automationSelected The automation the worker chose, if any.
     * @param list<string>         $toolsUsed          Names of tools the worker invoked.
     * @param list<string>         $warnings           Non-fatal warnings the worker raised.
     * @param list<string>         $errors             Recoverable errors the worker encountered.
     * @param list<string>         $recommendations    Recommendations the worker offers to the manager.
     * @param list<string>         $logs               Free-form log lines for audit/debug.
     */
    public function __construct(
        private readonly array $taskResult,
        array $evidence,
        private readonly string $reasoningSummary,
        private readonly float $confidence,
        private readonly int $executionTimeMs,
        private readonly int $executionCostMicros,
        private readonly array $resourcesUsed = [],
        private readonly ?string $automationSelected = null,
        array $toolsUsed = [],
        array $warnings = [],
        array $errors = [],
        array $recommendations = [],
        array $logs = [],
    ) {
        Assert::that(
            $confidence >= 0.0 && $confidence <= 1.0,
            'Worker confidence must be within the inclusive range [0, 1].',
        );
        Assert::that($executionTimeMs >= 0, 'Worker execution time must not be negative.');
        Assert::that($executionCostMicros >= 0, 'Worker execution cost must not be negative.');

        $this->evidence = $this->assertAllInstanceOf($evidence, EvidenceItem::class, 'evidence');
        $this->toolsUsed = $this->assertStringList($toolsUsed, 'toolsUsed');
        $this->warnings = $this->assertStringList($warnings, 'warnings');
        $this->errors = $this->assertStringList($errors, 'errors');
        $this->recommendations = $this->assertStringList($recommendations, 'recommendations');
        $this->logs = $this->assertStringList($logs, 'logs');
    }

    /**
     * Validate that every element of a list is an instance of the given class, returning a re-indexed list.
     *
     * @param array<array-key, mixed> $items
     * @param class-string            $class
     *
     * @return list<EvidenceItem>
     */
    private function assertAllInstanceOf(array $items, string $class, string $field): array
    {
        foreach ($items as $item) {
            Assert::that(
                $item instanceof $class,
                sprintf('%s must contain only %s instances.', $field, $class),
            );
        }

        /** @var list<EvidenceItem> $values */
        $values = array_values($items);

        return $values;
    }

    /**
     * Validate that every element of a list is a string, returning a re-indexed list.
     *
     * @param array<array-key, mixed> $items
     *
     * @return list<string>
     */
    private function assertStringList(array $items, string $field): array
    {
        foreach ($items as $item) {
            Assert::that(is_string($item), sprintf('%s must contain only strings.', $field));
        }

        /** @var list<string> $values */
        $values = array_values($items);

        return $values;
    }

    /**
     * The concrete result payload of the work.
     *
     * @return array<string, mixed>
     */
    public function taskResult(): array
    {
        return $this->taskResult;
    }

    /**
     * The evidence backing the result.
     *
     * @return list<EvidenceItem>
     */
    public function evidence(): array
    {
        return $this->evidence;
    }

    /**
     * The human-readable summary of how the result was reached.
     */
    public function reasoningSummary(): string
    {
        return $this->reasoningSummary;
    }

    /**
     * The worker's confidence in the result, in [0, 1].
     */
    public function confidence(): float
    {
        return $this->confidence;
    }

    /**
     * The wall-clock time the work took, in milliseconds.
     */
    public function executionTimeMs(): int
    {
        return $this->executionTimeMs;
    }

    /**
     * The monetary cost of the work in currency micros.
     */
    public function executionCostMicros(): int
    {
        return $this->executionCostMicros;
    }

    /**
     * The free-form map of resources consumed.
     *
     * @return array<string, mixed>
     */
    public function resourcesUsed(): array
    {
        return $this->resourcesUsed;
    }

    /**
     * The automation the worker chose, if any.
     */
    public function automationSelected(): ?string
    {
        return $this->automationSelected;
    }

    /**
     * The names of tools the worker invoked.
     *
     * @return list<string>
     */
    public function toolsUsed(): array
    {
        return $this->toolsUsed;
    }

    /**
     * The non-fatal warnings the worker raised.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * The recoverable errors the worker encountered.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Whether the worker reported any errors.
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * The recommendations the worker offers to the manager.
     *
     * @return list<string>
     */
    public function recommendations(): array
    {
        return $this->recommendations;
    }

    /**
     * The free-form log lines for audit/debug.
     *
     * @return list<string>
     */
    public function logs(): array
    {
        return $this->logs;
    }

    /**
     * Structural equality across every field.
     */
    public function equals(ValueObject $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        if (
            $other->taskResult !== $this->taskResult
            || $other->reasoningSummary !== $this->reasoningSummary
            || $other->confidence !== $this->confidence
            || $other->executionTimeMs !== $this->executionTimeMs
            || $other->executionCostMicros !== $this->executionCostMicros
            || $other->resourcesUsed !== $this->resourcesUsed
            || $other->automationSelected !== $this->automationSelected
            || $other->toolsUsed !== $this->toolsUsed
            || $other->warnings !== $this->warnings
            || $other->errors !== $this->errors
            || $other->recommendations !== $this->recommendations
            || $other->logs !== $this->logs
        ) {
            return false;
        }

        if (count($other->evidence) !== count($this->evidence)) {
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
     * A scalar-only representation suitable for JSON persistence and read models.
     *
     * @return array{
     *     taskResult: array<string, mixed>,
     *     evidence: list<array{kind: string, reference: string, summary: string, capturedAt: string}>,
     *     reasoningSummary: string,
     *     confidence: float,
     *     executionTimeMs: int,
     *     executionCostMicros: int,
     *     resourcesUsed: array<string, mixed>,
     *     automationSelected: string|null,
     *     toolsUsed: list<string>,
     *     warnings: list<string>,
     *     errors: list<string>,
     *     recommendations: list<string>,
     *     logs: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'taskResult' => $this->taskResult,
            'evidence' => array_map(
                static fn (EvidenceItem $item): array => $item->toArray(),
                $this->evidence,
            ),
            'reasoningSummary' => $this->reasoningSummary,
            'confidence' => $this->confidence,
            'executionTimeMs' => $this->executionTimeMs,
            'executionCostMicros' => $this->executionCostMicros,
            'resourcesUsed' => $this->resourcesUsed,
            'automationSelected' => $this->automationSelected,
            'toolsUsed' => $this->toolsUsed,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'recommendations' => $this->recommendations,
            'logs' => $this->logs,
        ];
    }
}
