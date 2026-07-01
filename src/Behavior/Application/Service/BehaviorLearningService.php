<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Service;

use Nizam\Behavior\Application\Dto\ProposalView;
use Nizam\Behavior\Application\Exception\BehaviorApplicationException;
use Nizam\Behavior\Domain\BehaviorChangeProposal;
use Nizam\Behavior\Domain\BehaviorObservation;
use Nizam\Behavior\Domain\BehaviorProfile;
use Nizam\Behavior\Domain\Port\BehaviorChangeProposalRepository;
use Nizam\Behavior\Domain\Port\BehaviorEventPublisher;
use Nizam\Behavior\Domain\Port\BehaviorProfileRepository;
use Nizam\Behavior\Domain\Port\ObservationSource;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Behavior\Domain\Service\BehaviorProfileConsolidator;
use Nizam\Behavior\Domain\Service\BehaviorRecommendationService;
use Nizam\Behavior\Domain\ValueObject\BehaviorRecommendation;
use Nizam\Behavior\Domain\ValueObject\EvidenceReference;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;

/**
 * Turns a role's approved practice into a reviewable proposal — and nothing more.
 *
 * This application service is the engine's learning loop made explicit: it *observes* the role's
 * approved observations through the {@see ObservationSource} port, *consolidates* the trait set they
 * support with the deterministic {@see BehaviorProfileConsolidator}, *recommends* the per-trait
 * changes with the explainable {@see BehaviorRecommendationService}, and — when a defensible change
 * exists — *creates a single pending proposal only*. It never activates a version, never rolls back,
 * never touches the {@see BehaviorProfile}: the profile is read but never saved here. The whole point
 * of the engine is that a human approves before anything changes, so the service returns a
 * {@see ProposalView} for review and stops.
 */
final class BehaviorLearningService
{
    /**
     * @param BehaviorProfileRepository        $profiles     The profile persistence port (read-only here).
     * @param BehaviorChangeProposalRepository $proposals    The proposal persistence port.
     * @param ObservationSource                $observations The approved-practice source port.
     * @param BehaviorProfileConsolidator      $consolidator The role-vs-person consolidation service.
     * @param BehaviorRecommendationService    $recommender  The explainable recommendation service.
     * @param BehaviorEventPublisher           $publisher    The port that dispatches pulled domain events.
     * @param Clock                            $clock        The time source handed to the aggregate.
     */
    public function __construct(
        private readonly BehaviorProfileRepository $profiles,
        private readonly BehaviorChangeProposalRepository $proposals,
        private readonly ObservationSource $observations,
        private readonly BehaviorProfileConsolidator $consolidator,
        private readonly BehaviorRecommendationService $recommender,
        private readonly BehaviorEventPublisher $publisher,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Learn from a role's approved practice and raise a proposal only when a change is warranted.
     *
     * @param string      $tenantId   The owning tenant's identifier.
     * @param string      $roleId     The role whose approved practice should drive the proposal.
     * @param string      $proposedBy Identity raising the proposal.
     * @param string|null $since      Optional ISO-8601 lower bound; only later approved practice is considered.
     *
     * @throws BehaviorApplicationException When no profile exists for the role or the practice warrants no change.
     *
     * @return ProposalView The pending proposal raised for human review.
     */
    public function learn(string $tenantId, string $roleId, string $proposedBy, ?string $since = null): ProposalView
    {
        $tenant = TenantId::fromString($tenantId);
        $role = RoleId::fromString($roleId);

        $profile = $this->profiles->ofRole($tenant, $role);
        if ($profile === null) {
            throw BehaviorApplicationException::profileForRoleNotFound($role->toString());
        }

        $sinceInstant = $since !== null ? new \DateTimeImmutable($since) : null;
        $approved = $this->observations->approvedObservationsForRole($tenant, $role, $sinceInstant);

        $recommendations = $this->recommender->recommend($profile, $approved);
        if ($recommendations === []) {
            throw BehaviorApplicationException::noChangeToPropose($role->toString());
        }

        $proposedTraits = $this->consolidator->consolidate($role, $approved, $profile->currentTraits());
        $confidence = $this->averageConfidence($recommendations);
        $rationale = $this->rationaleFrom($recommendations);
        $businessImpact = $this->businessImpactFrom($profile, $recommendations);

        $proposal = BehaviorChangeProposal::propose(
            $this->proposals->nextIdentity(),
            $tenant,
            $role,
            $profile->profileId(),
            $proposedTraits,
            $rationale,
            $this->evidenceFrom($approved),
            $confidence,
            $businessImpact,
            $proposedBy,
            $this->clock,
        );

        $this->proposals->save($proposal);
        $this->publisher->publish($proposal->pullDomainEvents());

        return ProposalView::fromDomain($proposal);
    }

    /**
     * The distinct approved evidence references backing the recommendations, in a stable order.
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
     * The mean confidence across the recommendations, in [0, 1].
     *
     * @param list<BehaviorRecommendation> $recommendations Non-empty recommendation list.
     */
    private function averageConfidence(array $recommendations): float
    {
        $sum = 0.0;
        foreach ($recommendations as $recommendation) {
            $sum += $recommendation->confidence();
        }

        $average = $sum / count($recommendations);

        return max(0.0, min(1.0, $average));
    }

    /**
     * A human-readable rationale summarizing every recommended trait change.
     *
     * @param list<BehaviorRecommendation> $recommendations Non-empty recommendation list.
     */
    private function rationaleFrom(array $recommendations): string
    {
        $lines = array_map(
            static fn (BehaviorRecommendation $recommendation): string => sprintf(
                '- %s: %s -> %s (%s)',
                $recommendation->targetTrait(),
                $recommendation->currentValue(),
                $recommendation->recommendedValue(),
                $recommendation->reason(),
            ),
            $recommendations,
        );

        return "Consolidated from approved practice across the role:\n" . implode("\n", $lines);
    }

    /**
     * A stated business impact for the proposal derived from the number of trait changes.
     *
     * @param list<BehaviorRecommendation> $recommendations Non-empty recommendation list.
     */
    private function businessImpactFrom(BehaviorProfile $profile, array $recommendations): string
    {
        return sprintf(
            'Aligns the "%s" role behavior with %d approved practice change(s), superseding version %d.',
            $profile->roleId()->toString(),
            count($recommendations),
            $profile->currentVersion(),
        );
    }
}
