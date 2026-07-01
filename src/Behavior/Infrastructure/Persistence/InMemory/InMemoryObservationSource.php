<?php

declare(strict_types=1);

namespace Nizam\Behavior\Infrastructure\Persistence\InMemory;

use DateTimeImmutable;
use Nizam\Behavior\Domain\BehaviorObservation;
use Nizam\Behavior\Domain\Port\ObservationSource;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\TenantId;

/**
 * A seedable, in-memory {@see ObservationSource} for tests and local wiring.
 *
 * Callers seed approved observations up front; the source then answers
 * {@see self::approvedObservationsForRole()} with exactly the observations that are approved, belong
 * to the requested tenant and role, and — when a lower bound is given — occurred at or after it. This
 * mirrors the guarantees the PDO adapter provides, so code depending on the port behaves identically
 * against either. Unapproved seeds are retained but never returned, faithfully modelling the rule
 * that only approved practice drives behavior.
 */
final class InMemoryObservationSource implements ObservationSource
{
    /**
     * @var list<BehaviorObservation> All seeded observations, approved or not.
     */
    private array $observations = [];

    /**
     * Seed the source with observations.
     *
     * @param list<BehaviorObservation> $observations The observations to make available.
     */
    public function seed(array $observations): void
    {
        foreach ($observations as $observation) {
            $this->add($observation);
        }
    }

    /**
     * Add a single observation to the source.
     */
    public function add(BehaviorObservation $observation): void
    {
        $this->observations[] = $observation;
    }

    /**
     * Remove every seeded observation.
     */
    public function clear(): void
    {
        $this->observations = [];
    }

    /**
     * The approved observations for a role within a tenant, optionally since a point in time.
     *
     * @return list<BehaviorObservation>
     */
    public function approvedObservationsForRole(
        TenantId $tenantId,
        RoleId $roleId,
        ?DateTimeImmutable $since = null,
    ): array {
        $matches = [];
        foreach ($this->observations as $observation) {
            if (!$observation->isApproved()) {
                continue;
            }
            if (!$observation->tenantId()->equals($tenantId)) {
                continue;
            }
            if (!$observation->roleId()->equals($roleId)) {
                continue;
            }
            if ($since !== null && $observation->evidence()->occurredAt() < $since) {
                continue;
            }
            $matches[] = $observation;
        }

        usort(
            $matches,
            static fn (BehaviorObservation $a, BehaviorObservation $b): int
                => $a->evidence()->occurredAt() <=> $b->evidence()->occurredAt(),
        );

        return $matches;
    }
}
