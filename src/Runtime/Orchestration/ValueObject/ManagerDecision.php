<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;
use Nizam\Runtime\Execution\Domain\ValueObject\EvidenceItem;

/**
 * The verdict a Manager plugin returns after reviewing the merged, scored worker output.
 *
 * A decision binds one {@see DecisionOutcome} to the {@see MergedResult} it was reached on, the
 * {@see ConfidenceAssessment} that informed it, a human {@see self::summary()}, and the supporting
 * {@see EvidenceItem}s. It is the terminal artefact of the {@see \Nizam\Runtime\Orchestration\MasterOrchestrator}'s
 * `handle()` — the manager's answer, never a worker's — and is projected into the execution's stored
 * outcome. The convenience constructors ({@see self::approve()}, {@see self::reject()},
 * {@see self::requestRetry()}, {@see self::requestMoreWorkers()}, {@see self::escalate()}) make the
 * five outcomes explicit at the call site. Being a value object it is immutable and self-validating.
 */
final class ManagerDecision implements ValueObject
{
    /** @var list<EvidenceItem> */
    private readonly array $evidence;

    /**
     * @param DecisionOutcome      $outcome    The decision the manager reached.
     * @param string               $summary    A human-readable statement of the decision.
     * @param list<EvidenceItem>   $evidence   The evidence supporting the decision.
     * @param ConfidenceAssessment $confidence The assessment that informed the decision.
     * @param MergedResult         $mergedResult The merged worker output the decision was reached on.
     */
    public function __construct(
        private readonly DecisionOutcome $outcome,
        private readonly string $summary,
        array $evidence,
        private readonly ConfidenceAssessment $confidence,
        private readonly MergedResult $mergedResult,
    ) {
        Assert::notEmpty($summary, 'A manager decision must carry a non-empty summary.');
        foreach ($evidence as $item) {
            Assert::that(
                $item instanceof EvidenceItem,
                'Manager decision evidence must contain only EvidenceItem instances.',
            );
        }
        /** @var list<EvidenceItem> $values */
        $values = array_values($evidence);
        $this->evidence = $values;
    }

    /**
     * Build an approval decision.
     *
     * @param string               $summary      A human-readable statement of the approval.
     * @param list<EvidenceItem>   $evidence     The supporting evidence.
     * @param ConfidenceAssessment $confidence   The informing assessment.
     * @param MergedResult         $mergedResult The merged output approved.
     */
    public static function approve(
        string $summary,
        array $evidence,
        ConfidenceAssessment $confidence,
        MergedResult $mergedResult,
    ): self {
        return new self(DecisionOutcome::Approved, $summary, $evidence, $confidence, $mergedResult);
    }

    /**
     * Build a rejection decision.
     *
     * @param string               $summary      A human-readable statement of the rejection.
     * @param list<EvidenceItem>   $evidence     The supporting evidence.
     * @param ConfidenceAssessment $confidence   The informing assessment.
     * @param MergedResult         $mergedResult The merged output rejected.
     */
    public static function reject(
        string $summary,
        array $evidence,
        ConfidenceAssessment $confidence,
        MergedResult $mergedResult,
    ): self {
        return new self(DecisionOutcome::Rejected, $summary, $evidence, $confidence, $mergedResult);
    }

    /**
     * Build a retry-requested decision.
     *
     * @param string               $summary      A human-readable statement of the request.
     * @param list<EvidenceItem>   $evidence     The supporting evidence.
     * @param ConfidenceAssessment $confidence   The informing assessment.
     * @param MergedResult         $mergedResult The merged output so far.
     */
    public static function requestRetry(
        string $summary,
        array $evidence,
        ConfidenceAssessment $confidence,
        MergedResult $mergedResult,
    ): self {
        return new self(DecisionOutcome::RetryRequested, $summary, $evidence, $confidence, $mergedResult);
    }

    /**
     * Build a more-workers-requested decision.
     *
     * @param string               $summary      A human-readable statement of the request.
     * @param list<EvidenceItem>   $evidence     The supporting evidence.
     * @param ConfidenceAssessment $confidence   The informing assessment.
     * @param MergedResult         $mergedResult The merged output so far.
     */
    public static function requestMoreWorkers(
        string $summary,
        array $evidence,
        ConfidenceAssessment $confidence,
        MergedResult $mergedResult,
    ): self {
        return new self(DecisionOutcome::MoreWorkersRequested, $summary, $evidence, $confidence, $mergedResult);
    }

    /**
     * Build an escalation decision.
     *
     * @param string               $summary      A human-readable statement of the escalation.
     * @param list<EvidenceItem>   $evidence     The supporting evidence.
     * @param ConfidenceAssessment $confidence   The informing assessment.
     * @param MergedResult         $mergedResult The merged output escalated.
     */
    public static function escalate(
        string $summary,
        array $evidence,
        ConfidenceAssessment $confidence,
        MergedResult $mergedResult,
    ): self {
        return new self(DecisionOutcome::Escalated, $summary, $evidence, $confidence, $mergedResult);
    }

    /**
     * The decision the manager reached.
     */
    public function outcome(): DecisionOutcome
    {
        return $this->outcome;
    }

    /**
     * The human-readable statement of the decision.
     */
    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * The evidence supporting the decision.
     *
     * @return list<EvidenceItem>
     */
    public function evidence(): array
    {
        return $this->evidence;
    }

    /**
     * The assessment that informed the decision.
     */
    public function confidence(): ConfidenceAssessment
    {
        return $this->confidence;
    }

    /**
     * The merged worker output the decision was reached on.
     */
    public function mergedResult(): MergedResult
    {
        return $this->mergedResult;
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
            $other->outcome !== $this->outcome
            || $other->summary !== $this->summary
            || !$other->confidence->equals($this->confidence)
            || !$other->mergedResult->equals($this->mergedResult)
            || count($other->evidence) !== count($this->evidence)
        ) {
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
     * A scalar-only representation suitable for persistence (the `manager_decisions` row) and transport.
     *
     * @return array{
     *     outcome: string,
     *     summary: string,
     *     evidence: list<array{kind: string, reference: string, summary: string, capturedAt: string}>,
     *     confidence: array{score: float, rationale: string, needsRetry: bool, needsMoreWorkers: bool},
     *     mergedResult: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'summary' => $this->summary,
            'evidence' => array_map(static fn (EvidenceItem $e): array => $e->toArray(), $this->evidence),
            'confidence' => $this->confidence->toArray(),
            'mergedResult' => $this->mergedResult->toArray(),
        ];
    }
}
