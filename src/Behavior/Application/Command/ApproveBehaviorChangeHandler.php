<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Command;

use Nizam\Behavior\Application\Dto\BehaviorProfileView;
use Nizam\Behavior\Application\Exception\BehaviorApplicationException;
use Nizam\Behavior\Domain\BehaviorChangeProposal;
use Nizam\Behavior\Domain\BehaviorProfile;
use Nizam\Behavior\Domain\Port\BehaviorChangeProposalRepository;
use Nizam\Behavior\Domain\Port\BehaviorEventPublisher;
use Nizam\Behavior\Domain\Port\BehaviorProfileRepository;
use Nizam\Behavior\Domain\ProposalId;
use Nizam\Behavior\Domain\ValueObject\ChangeLogEntry;
use Nizam\Kernel\Application\Command;
use Nizam\Kernel\Application\CommandHandler;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Support\Assert;

/**
 * Approves a pending proposal and applies it to its target profile in one unit of work.
 *
 * The handler loads the proposal, approves it (recording the decision), loads the profile it targets,
 * asserts the profile is still bound to the proposal's role, and applies the change: a rollback
 * proposal restores the referenced version, while an ordinary proposal activates the proposed traits
 * as a new appended revision built from the proposal's own audit trail and evidence. Both aggregates
 * are saved and the domain events pulled from both are published together.
 */
final class ApproveBehaviorChangeHandler implements CommandHandler
{
    /**
     * @param BehaviorChangeProposalRepository $proposals The proposal persistence port.
     * @param BehaviorProfileRepository        $profiles  The profile persistence port.
     * @param BehaviorEventPublisher           $publisher The port that dispatches pulled domain events.
     * @param Clock                            $clock     The time source handed to the aggregates.
     */
    public function __construct(
        private readonly BehaviorChangeProposalRepository $proposals,
        private readonly BehaviorProfileRepository $profiles,
        private readonly BehaviorEventPublisher $publisher,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Handle an {@see ApproveBehaviorChange} command.
     *
     * @throws BehaviorApplicationException When the proposal or its target profile cannot be found.
     *
     * @return BehaviorProfileView The profile view after the change has been applied.
     */
    public function handle(Command $command): BehaviorProfileView
    {
        Assert::that(
            $command instanceof ApproveBehaviorChange,
            'ApproveBehaviorChangeHandler can only handle ApproveBehaviorChange commands.',
        );

        $tenantId = TenantId::fromString($command->tenantId);
        $proposalId = ProposalId::fromString($command->proposalId);

        $proposal = $this->proposals->ofId($tenantId, $proposalId);
        if ($proposal === null) {
            throw BehaviorApplicationException::proposalNotFound($proposalId->toString());
        }

        $profile = $this->profiles->ofId($tenantId, $proposal->profileId());
        if ($profile === null) {
            throw BehaviorApplicationException::profileNotFound($proposal->profileId()->toString());
        }

        $profile->assertBoundToRole($proposal->roleId());

        $proposal->approve($command->approvedBy, $this->clock);
        $this->applyToProfile($proposal, $profile, $command->approvedBy);

        $this->proposals->save($proposal);
        $this->profiles->save($profile);

        $this->publisher->publish($this->mergeEvents($proposal, $profile));

        return BehaviorProfileView::fromDomain($profile);
    }

    /**
     * Apply an approved proposal to its profile, choosing rollback or a forward change.
     */
    private function applyToProfile(
        BehaviorChangeProposal $proposal,
        BehaviorProfile $profile,
        string $approvedBy,
    ): void {
        $rollbackToVersion = $proposal->rollbackToVersion();
        if ($rollbackToVersion !== null) {
            $profile->rollbackTo($rollbackToVersion, $approvedBy, $this->clock);

            return;
        }

        $newVersion = $profile->currentVersion() + 1;
        $changeLog = new ChangeLogEntry(
            version: $newVersion,
            changedAt: $this->clock->now(),
            changedBy: $approvedBy,
            summary: sprintf('Applied approved proposal %s.', $proposal->proposalId()->toString()),
            traitsDiff: $profile->currentTraits()->diff($proposal->proposedTraits()),
            businessImpact: $proposal->businessImpact(),
            rollbackToVersion: null,
        );

        $profile->applyApprovedChange(
            $proposal->proposedTraits(),
            $changeLog,
            $proposal->supportingEvidence(),
            $approvedBy,
            $this->clock,
        );
    }

    /**
     * Pull and concatenate the domain events recorded by both aggregates, proposal first.
     *
     * @return list<DomainEvent>
     */
    private function mergeEvents(BehaviorChangeProposal $proposal, BehaviorProfile $profile): array
    {
        return array_merge(
            $proposal->pullDomainEvents(),
            $profile->pullDomainEvents(),
        );
    }
}
