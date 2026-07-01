<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Service;

use Nizam\Behavior\Domain\BehaviorObservation;
use Nizam\Behavior\Domain\BehaviorProfile;
use Nizam\Behavior\Domain\ValueObject\BehaviorRecommendation;
use Nizam\Behavior\Domain\ValueObject\EvidenceReference;
use Nizam\Platform\Support\Assert;

/**
 * Turns approved practice into explainable, per-trait recommendations for a behavior profile.
 *
 * The engine recommends; it never mutates behavior. This stateless domain service compares a
 * profile's current traits against the traits the role's approved observations support — computed by
 * the same deterministic {@see BehaviorProfileConsolidator} used everywhere else — and emits one
 * {@see BehaviorRecommendation} per trait that should change. Every recommendation is fully
 * self-justifying: the reason for the change, the approved evidence that backs it (never empty), a
 * confidence derived from the strength of that evidence, and concrete guidance on how to apply it.
 * Recommendations are returned in a stable order for reproducibility.
 */
final class BehaviorRecommendationService
{
    /**
     * @param BehaviorProfileConsolidator $consolidator The consolidation service that derives supported traits.
     */
    public function __construct(
        private readonly BehaviorProfileConsolidator $consolidator,
    ) {
    }

    /**
     * Recommend the trait changes the approved evidence supports for a profile.
     *
     * @param BehaviorProfile           $profile  The profile to advise.
     * @param list<BehaviorObservation> $approved The approved observations for the profile's role.
     *
     * @return list<BehaviorRecommendation> One recommendation per trait that should change; empty when
     *                                      the current traits already match what the evidence supports.
     */
    public function recommend(BehaviorProfile $profile, array $approved): array
    {
        foreach ($approved as $observation) {
            Assert::that(
                $observation instanceof BehaviorObservation,
                'Recommendation input must contain only BehaviorObservation instances.',
            );
            Assert::that(
                $observation->isApproved(),
                'Recommendations may only be drawn from approved observations.',
            );
        }

        $evidence = $this->evidenceFrom($approved);
        if ($evidence === []) {
            // With no approved evidence there is nothing defensible to recommend.
            return [];
        }

        $current = $profile->currentTraits();
        $supported = $this->consolidator->consolidate($profile->roleId(), $approved, $current);

        $diff = $current->diff($supported);
        if ($diff === []) {
            return [];
        }

        $confidence = $this->confidenceFrom($evidence);

        $recommendations = [];
        foreach ($diff as $trait => $change) {
            $recommendations[] = new BehaviorRecommendation(
                targetTrait: $trait,
                currentValue: $change['from'],
                recommendedValue: $change['to'],
                reason: sprintf(
                    'Approved practice across the role supports changing "%s" from "%s" to "%s".',
                    $trait,
                    $change['from'],
                    $change['to'],
                ),
                supportingEvidence: $evidence,
                confidence: $confidence,
                howToModify: sprintf(
                    'Raise a behavior change proposal setting "%s" to "%s", then have it approved and applied to the profile.',
                    $trait,
                    $change['to'],
                ),
            );
        }

        return $recommendations;
    }

    /**
     * Collect the distinct approved evidence references, in a stable order.
     *
     * @param list<BehaviorObservation> $approved
     *
     * @return list<EvidenceReference>
     */
    private function evidenceFrom(array $approved): array
    {
        $byReference = [];
        foreach ($approved as $observation) {
            $evidence = $observation->evidence();
            $byReference[$evidence->referenceId()] = $evidence;
        }

        return array_values($byReference);
    }

    /**
     * Derive a confidence in [0, 1] from the average weight of the supporting evidence.
     *
     * @param list<EvidenceReference> $evidence Non-empty supporting evidence.
     */
    private function confidenceFrom(array $evidence): float
    {
        $sum = 0.0;
        foreach ($evidence as $reference) {
            $sum += $reference->weight();
        }

        $average = $sum / count($evidence);

        return max(0.0, min(1.0, $average));
    }
}
