<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Port;

use DateTimeImmutable;
use Nizam\Behavior\Domain\BehaviorObservation;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\TenantId;

/**
 * The port through which the engine reads approved business practice for a role.
 *
 * Behavior is only ever evolved from approved observations; this port is the sole channel the domain
 * uses to obtain them. Concrete adapters gather observations from the wider platform (decisions,
 * task executions, reviews, policies, …) and MUST return only approved, tenant-scoped observations.
 */
interface ObservationSource
{
    /**
     * The approved observations for a role within a tenant, optionally since a point in time.
     *
     * Every returned observation must be approved ({@see BehaviorObservation::isApproved()} true) and
     * belong to the given tenant and role. When `$since` is provided, only observations whose evidence
     * occurred at or after that instant are returned.
     *
     * @return list<BehaviorObservation>
     */
    public function approvedObservationsForRole(
        TenantId $tenantId,
        RoleId $roleId,
        ?DateTimeImmutable $since = null,
    ): array;
}
