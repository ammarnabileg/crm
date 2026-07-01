<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Command;

use Nizam\Behavior\Application\Dto\ProposalView;
use Nizam\Behavior\Application\Exception\BehaviorApplicationException;
use Nizam\Behavior\Domain\Port\BehaviorChangeProposalRepository;
use Nizam\Behavior\Domain\Port\BehaviorEventPublisher;
use Nizam\Behavior\Domain\ProposalId;
use Nizam\Kernel\Application\Command;
use Nizam\Kernel\Application\CommandHandler;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Support\Assert;

/**
 * Rejects a pending behavior change proposal.
 *
 * The handler loads the proposal, asks the aggregate to reject it (recording the reason and the
 * deciding identity, and emitting a rejected event), saves it, and publishes the pulled events. The
 * target profile is never touched — a rejection changes no behavior.
 */
final class RejectBehaviorChangeHandler implements CommandHandler
{
    /**
     * @param BehaviorChangeProposalRepository $proposals The proposal persistence port.
     * @param BehaviorEventPublisher           $publisher The port that dispatches pulled domain events.
     * @param Clock                            $clock     The time source handed to the aggregate.
     */
    public function __construct(
        private readonly BehaviorChangeProposalRepository $proposals,
        private readonly BehaviorEventPublisher $publisher,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Handle a {@see RejectBehaviorChange} command.
     *
     * @throws BehaviorApplicationException When the proposal cannot be found for the tenant.
     *
     * @return ProposalView The proposal view after rejection.
     */
    public function handle(Command $command): ProposalView
    {
        Assert::that(
            $command instanceof RejectBehaviorChange,
            'RejectBehaviorChangeHandler can only handle RejectBehaviorChange commands.',
        );

        $tenantId = TenantId::fromString($command->tenantId);
        $proposalId = ProposalId::fromString($command->proposalId);

        $proposal = $this->proposals->ofId($tenantId, $proposalId);
        if ($proposal === null) {
            throw BehaviorApplicationException::proposalNotFound($proposalId->toString());
        }

        $proposal->reject($command->reason, $command->rejectedBy, $this->clock);

        $this->proposals->save($proposal);
        $this->publisher->publish($proposal->pullDomainEvents());

        return ProposalView::fromDomain($proposal);
    }
}
