<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * An immutable, fully explainable suggestion to change one trait of a behavior profile.
 *
 * The Behavior engine only ever recommends; it never mutates production behavior on its own. Each
 * recommendation is self-justifying: it names the target trait, its current and recommended values,
 * the reason for the change, the approved evidence that supports it (never empty), a confidence in
 * [0, 1], and concrete guidance on how to apply the change. These invariants are what make the
 * engine's output reviewable rather than opaque.
 */
final class BehaviorRecommendation implements ValueObject
{
    /**
     * @param string                    $targetTrait        Name of the trait to change (a {@see BehaviorTraits} key).
     * @param string                    $currentValue       The trait's current string value.
     * @param string                    $recommendedValue   The proposed string value.
     * @param string                    $reason             Why the change is recommended.
     * @param list<EvidenceReference>   $supportingEvidence Approved evidence backing the change (non-empty).
     * @param float                     $confidence         Confidence in the recommendation, in [0, 1].
     * @param string                    $howToModify        Concrete guidance for applying the change.
     */
    public function __construct(
        private readonly string $targetTrait,
        private readonly string $currentValue,
        private readonly string $recommendedValue,
        private readonly string $reason,
        private readonly array $supportingEvidence,
        private readonly float $confidence,
        private readonly string $howToModify,
    ) {
        Assert::notEmpty($targetTrait, 'Recommendation targetTrait must not be empty.');
        Assert::notEmpty($currentValue, 'Recommendation currentValue must not be empty.');
        Assert::notEmpty($recommendedValue, 'Recommendation recommendedValue must not be empty.');
        Assert::notEmpty($reason, 'Recommendation reason must not be empty.');
        Assert::notEmpty($howToModify, 'Recommendation howToModify must not be empty.');
        Assert::that(
            $currentValue !== $recommendedValue,
            'Recommendation must change the value; currentValue and recommendedValue are identical.',
        );
        Assert::that(
            $supportingEvidence !== [],
            'Recommendation must cite at least one supporting evidence.',
        );
        foreach ($supportingEvidence as $evidence) {
            Assert::that(
                $evidence instanceof EvidenceReference,
                'Recommendation supportingEvidence must contain only EvidenceReference instances.',
            );
        }
        Assert::that(
            $confidence >= 0.0 && $confidence <= 1.0,
            'Recommendation confidence must be within the inclusive range [0, 1].',
        );
    }

    /**
     * The name of the trait the recommendation targets.
     */
    public function targetTrait(): string
    {
        return $this->targetTrait;
    }

    /**
     * The trait's current string value.
     */
    public function currentValue(): string
    {
        return $this->currentValue;
    }

    /**
     * The proposed string value for the trait.
     */
    public function recommendedValue(): string
    {
        return $this->recommendedValue;
    }

    /**
     * Why the change is recommended.
     */
    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * The approved evidence backing the recommendation.
     *
     * @return list<EvidenceReference>
     */
    public function supportingEvidence(): array
    {
        return $this->supportingEvidence;
    }

    /**
     * The confidence in the recommendation, in [0, 1].
     */
    public function confidence(): float
    {
        return $this->confidence;
    }

    /**
     * Concrete guidance for applying the change.
     */
    public function howToModify(): string
    {
        return $this->howToModify;
    }

    /**
     * Structural equality across every attribute, including ordered evidence.
     */
    public function equals(ValueObject $other): bool
    {
        if (!$other instanceof self
            || $other->targetTrait !== $this->targetTrait
            || $other->currentValue !== $this->currentValue
            || $other->recommendedValue !== $this->recommendedValue
            || $other->reason !== $this->reason
            || $other->confidence !== $this->confidence
            || $other->howToModify !== $this->howToModify
            || count($other->supportingEvidence) !== count($this->supportingEvidence)
        ) {
            return false;
        }

        foreach ($this->supportingEvidence as $index => $evidence) {
            if (!$evidence->equals($other->supportingEvidence[$index])) {
                return false;
            }
        }

        return true;
    }

    /**
     * A scalar-only representation suitable for JSON persistence and read models.
     *
     * @return array{
     *     targetTrait: string,
     *     currentValue: string,
     *     recommendedValue: string,
     *     reason: string,
     *     supportingEvidence: list<array{sourceType: string, referenceId: string, summary: string, occurredAt: string, weight: float}>,
     *     confidence: float,
     *     howToModify: string
     * }
     */
    public function toArray(): array
    {
        return [
            'targetTrait' => $this->targetTrait,
            'currentValue' => $this->currentValue,
            'recommendedValue' => $this->recommendedValue,
            'reason' => $this->reason,
            'supportingEvidence' => array_map(
                static fn (EvidenceReference $evidence): array => $evidence->toArray(),
                $this->supportingEvidence,
            ),
            'confidence' => $this->confidence,
            'howToModify' => $this->howToModify,
        ];
    }
}
