<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Command;

use Nizam\Behavior\Application\Dto\ProposalView;
use Nizam\Behavior\Application\Exception\BehaviorApplicationException;
use Nizam\Behavior\Application\Service\BehaviorLearningService;
use Nizam\Kernel\Application\Command;
use Nizam\Kernel\Application\CommandHandler;
use Nizam\Platform\Support\Assert;

/**
 * Raises a behavior change proposal from a role's approved practice.
 *
 * The handler delegates the observe-consolidate-recommend-propose loop to
 * {@see BehaviorLearningService}, which reads the role's approved observations, derives the trait set
 * they support, and — only when a defensible change exists — creates a single pending proposal and
 * publishes its recorded events. Nothing about production behavior is mutated; a human must approve.
 */
final class ProposeBehaviorChangeHandler implements CommandHandler
{
    /**
     * @param BehaviorLearningService $learning The application service that produces the proposal.
     */
    public function __construct(
        private readonly BehaviorLearningService $learning,
    ) {
    }

    /**
     * Handle a {@see ProposeBehaviorChange} command.
     *
     * @throws BehaviorApplicationException When no profile exists for the role or the practice warrants no change.
     *
     * @return ProposalView The pending proposal raised for human review.
     */
    public function handle(Command $command): ProposalView
    {
        Assert::that(
            $command instanceof ProposeBehaviorChange,
            'ProposeBehaviorChangeHandler can only handle ProposeBehaviorChange commands.',
        );

        return $this->learning->learn(
            $command->tenantId,
            $command->roleId,
            $command->proposedBy,
        );
    }
}
