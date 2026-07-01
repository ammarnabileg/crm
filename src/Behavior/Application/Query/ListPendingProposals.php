<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Query;

use Nizam\Kernel\Application\Query;

/**
 * Request for the behavior change proposals awaiting a decision within a tenant.
 *
 * Answered with a list of {@see \Nizam\Behavior\Application\Dto\ProposalView}s — the review queue for
 * the "Behavior Review" experience — ordered by when each was proposed. Tenant-scoped: only the
 * owning tenant's pending proposals are returned.
 */
final class ListPendingProposals implements Query
{
    /**
     * @param string $tenantId The owning tenant's identifier.
     */
    public function __construct(
        public readonly string $tenantId,
    ) {
    }
}
