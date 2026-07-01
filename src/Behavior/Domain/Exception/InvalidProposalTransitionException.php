<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Exception;

use Nizam\Behavior\Domain\Enum\ProposalStatus;

/**
 * Raised when a proposal decision is attempted on a proposal that is no longer pending.
 *
 * A {@see \Nizam\Behavior\Domain\BehaviorChangeProposal} may only be approved, rejected, or
 * withdrawn while it is {@see ProposalStatus::Pending}; a proposal that has already been decided is
 * terminal. Carries error code `BEHAVIOR.INVALID_PROPOSAL_TRANSITION`.
 */
final class InvalidProposalTransitionException extends BehaviorDomainException
{
    /**
     * The stable error code for this violation.
     */
    public const string CODE = 'BEHAVIOR.INVALID_PROPOSAL_TRANSITION';

    /**
     * Build the exception describing the operation and the offending current status.
     *
     * @param string         $operation The decision attempted (e.g. "approve").
     * @param ProposalStatus $current   The status the proposal was actually in.
     */
    public static function forOperation(string $operation, ProposalStatus $current): self
    {
        return new self(
            self::CODE,
            sprintf('Cannot %s a behavior change proposal in status "%s".', $operation, $current->value),
        );
    }
}
