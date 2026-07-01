<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Query;

use Nizam\Behavior\Application\Dto\ProposalView;
use Nizam\Behavior\Domain\BehaviorChangeProposal;
use Nizam\Behavior\Domain\Port\BehaviorChangeProposalRepository;
use Nizam\Kernel\Application\Query;
use Nizam\Kernel\Application\QueryHandler;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Support\Assert;

/**
 * Answers {@see ListPendingProposals} with the tenant's review queue as read-only views.
 *
 * The handler asks the repository for the tenant's pending proposals and projects each into a
 * {@see ProposalView}, preserving the repository's proposed-at ordering. It mutates nothing.
 */
final class ListPendingProposalsHandler implements QueryHandler
{
    /**
     * @param BehaviorChangeProposalRepository $proposals The proposal persistence port.
     */
    public function __construct(
        private readonly BehaviorChangeProposalRepository $proposals,
    ) {
    }

    /**
     * Handle a {@see ListPendingProposals} query.
     *
     * @return list<ProposalView> The tenant's pending proposals as read-only views.
     */
    public function handle(Query $query): array
    {
        Assert::that(
            $query instanceof ListPendingProposals,
            'ListPendingProposalsHandler can only handle ListPendingProposals queries.',
        );

        $tenantId = TenantId::fromString($query->tenantId);

        return array_map(
            static fn (BehaviorChangeProposal $proposal): ProposalView => ProposalView::fromDomain($proposal),
            $this->proposals->pendingForTenant($tenantId),
        );
    }
}
