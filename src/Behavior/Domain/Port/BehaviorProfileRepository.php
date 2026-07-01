<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Port;

use Nizam\Behavior\Domain\BehaviorProfile;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\TenantId;

/**
 * The persistence port for {@see BehaviorProfile} aggregates.
 *
 * A domain-facing contract: the application layer depends on this interface, while concrete
 * adapters (in-memory, PDO) live in Infrastructure. Every operation is tenant-scoped — a profile is
 * only ever visible to its owning tenant — so implementations must never leak data across tenants.
 */
interface BehaviorProfileRepository
{
    /**
     * Persist a profile, inserting or updating as appropriate.
     *
     * Implementations should honor the aggregate's optimistic-concurrency version and its
     * append-only revision history.
     */
    public function save(BehaviorProfile $profile): void;

    /**
     * Load a profile by id within a tenant, or null when none exists for that tenant.
     */
    public function ofId(TenantId $tenantId, BehaviorProfileId $id): ?BehaviorProfile;

    /**
     * Load the profile bound to a role within a tenant, or null when none exists.
     */
    public function ofRole(TenantId $tenantId, RoleId $roleId): ?BehaviorProfile;

    /**
     * Mint the next identity for a new profile.
     */
    public function nextIdentity(): BehaviorProfileId;

    /**
     * Whether a profile already exists for a role within a tenant.
     */
    public function existsForRole(TenantId $tenantId, RoleId $roleId): bool;
}
