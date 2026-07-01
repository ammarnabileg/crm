<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Command;

use Nizam\Kernel\Application\Command;

/**
 * Intent to reject a pending behavior change proposal so it will never be applied.
 *
 * Rejection is a terminal human decision that leaves the target profile untouched; it records who
 * rejected the proposal and why. Only a pending proposal may be rejected.
 */
final class RejectBehaviorChange implements Command
{
    /**
     * @param string $tenantId   The owning tenant's identifier.
     * @param string $proposalId The proposal to reject.
     * @param string $reason     Why the proposal is rejected.
     * @param string $rejectedBy Identity rejecting the proposal.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $proposalId,
        public readonly string $reason,
        public readonly string $rejectedBy,
    ) {
    }
}
