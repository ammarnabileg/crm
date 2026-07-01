<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Dto;

use Nizam\Behavior\Domain\ValueObject\BehaviorRecommendation;

/**
 * A flat, read-only projection of a {@see BehaviorRecommendation} for callers outside the domain.
 *
 * Explainability is the point: the view carries the target trait, its current and recommended
 * values, the reason, the approved evidence that backs the change, a confidence, and concrete
 * guidance on how to apply it — everything a reviewer needs to understand and act on the engine's
 * suggestion, all as scalars and plain arrays.
 */
final class RecommendationView
{
    /**
     * @param string                     $targetTrait        Name of the trait to change.
     * @param string                     $currentValue       The trait's current value.
     * @param string                     $recommendedValue   The proposed value.
     * @param string                     $reason             Why the change is recommended.
     * @param list<array<string, mixed>> $supportingEvidence Approved evidence backing the change.
     * @param float                      $confidence         Confidence in the recommendation, in [0, 1].
     * @param string                     $howToModify        Concrete guidance for applying the change.
     */
    public function __construct(
        public readonly string $targetTrait,
        public readonly string $currentValue,
        public readonly string $recommendedValue,
        public readonly string $reason,
        public readonly array $supportingEvidence,
        public readonly float $confidence,
        public readonly string $howToModify,
    ) {
    }

    /**
     * Project a domain recommendation into its read-only view.
     */
    public static function fromDomain(BehaviorRecommendation $recommendation): self
    {
        $data = $recommendation->toArray();

        return new self(
            targetTrait: $data['targetTrait'],
            currentValue: $data['currentValue'],
            recommendedValue: $data['recommendedValue'],
            reason: $data['reason'],
            supportingEvidence: $data['supportingEvidence'],
            confidence: $data['confidence'],
            howToModify: $data['howToModify'],
        );
    }

    /**
     * A scalar-only representation suitable for JSON serialization and transport.
     *
     * @return array{
     *     targetTrait: string,
     *     currentValue: string,
     *     recommendedValue: string,
     *     reason: string,
     *     supportingEvidence: list<array<string, mixed>>,
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
            'supportingEvidence' => $this->supportingEvidence,
            'confidence' => $this->confidence,
            'howToModify' => $this->howToModify,
        ];
    }
}
