<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * The lifecycle status of a {@see \Nizam\Behavior\Domain\BehaviorChangeProposal}.
 *
 * A proposal is pending until a human explicitly approves or rejects it, or the proposer withdraws
 * it. Only a pending proposal may transition. String-backed for stable persistence and read models.
 */
enum ProposalStatus: string
{
    /** Awaiting a human decision. */
    case Pending = 'pending';

    /** Approved; may be applied to the target profile. */
    case Approved = 'approved';

    /** Rejected; will not be applied. */
    case Rejected = 'rejected';

    /** Withdrawn by the proposer before a decision. */
    case Withdrawn = 'withdrawn';

    /**
     * Whether the proposal is still awaiting a decision and may transition.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}
