<?php

declare(strict_types=1);

namespace Nizam\Behavior\Infrastructure\Persistence\InMemory;

use Nizam\Behavior\Domain\BehaviorChangeProposal;
use Nizam\Behavior\Domain\Enum\ProposalStatus;
use Nizam\Behavior\Domain\Port\BehaviorChangeProposalRepository;
use Nizam\Behavior\Domain\ProposalId;
use Nizam\Kernel\Domain\TenantId;

/**
 * An in-memory, tenant-scoped {@see BehaviorChangeProposalRepository} for tests and local wiring.
 *
 * Proposals are held in a process-local map keyed by tenant and proposal id. Reads are tenant scoped
 * and {@see self::pendingForTenant()} returns only still-pending proposals, ordered by when they were
 * proposed — mirroring the ordering the PDO adapter guarantees. Not persistent, not concurrency safe.
 */
final class InMemoryBehaviorChangeProposalRepository implements BehaviorChangeProposalRepository
{
    /**
     * @var array<string, array<string, BehaviorChangeProposal>> Proposals keyed by tenant id, then proposal id.
     */
    private array $proposals = [];

    /**
     * Persist a proposal, inserting or replacing the stored copy for its tenant.
     */
    public function save(BehaviorChangeProposal $proposal): void
    {
        $tenantKey = $proposal->tenantId()->toString();
        $this->proposals[$tenantKey][$proposal->proposalId()->toString()] = $proposal;
    }

    /**
     * Load a proposal by id within a tenant, or null when none exists for that tenant.
     */
    public function ofId(TenantId $tenantId, ProposalId $id): ?BehaviorChangeProposal
    {
        return $this->proposals[$tenantId->toString()][$id->toString()] ?? null;
    }

    /**
     * List the pending proposals within a tenant, ordered by when they were proposed.
     *
     * @return list<BehaviorChangeProposal>
     */
    public function pendingForTenant(TenantId $tenantId): array
    {
        $pending = [];
        foreach ($this->proposals[$tenantId->toString()] ?? [] as $proposal) {
            if ($proposal->status() === ProposalStatus::Pending) {
                $pending[] = $proposal;
            }
        }

        usort(
            $pending,
            static fn (BehaviorChangeProposal $a, BehaviorChangeProposal $b): int
                => $a->proposedAt() <=> $b->proposedAt(),
        );

        return $pending;
    }

    /**
     * Mint the next identity for a new proposal.
     */
    public function nextIdentity(): ProposalId
    {
        return ProposalId::generate();
    }
}
