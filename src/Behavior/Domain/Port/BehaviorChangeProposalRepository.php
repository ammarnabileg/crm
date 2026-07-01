<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Port;

use Nizam\Behavior\Domain\BehaviorChangeProposal;
use Nizam\Behavior\Domain\ProposalId;
use Nizam\Kernel\Domain\TenantId;

/**
 * The persistence port for {@see BehaviorChangeProposal} aggregates.
 *
 * A domain-facing contract implemented by Infrastructure adapters. All operations are tenant-scoped;
 * implementations must never expose a proposal to a tenant other than its owner.
 */
interface BehaviorChangeProposalRepository
{
    /**
     * Persist a proposal, inserting or updating as appropriate.
     */
    public function save(BehaviorChangeProposal $proposal): void;

    /**
     * Load a proposal by id within a tenant, or null when none exists for that tenant.
     */
    public function ofId(TenantId $tenantId, ProposalId $id): ?BehaviorChangeProposal;

    /**
     * List the proposals awaiting a decision within a tenant, ordered by when they were proposed.
     *
     * @return list<BehaviorChangeProposal>
     */
    public function pendingForTenant(TenantId $tenantId): array;

    /**
     * Mint the next identity for a new proposal.
     */
    public function nextIdentity(): ProposalId;
}
