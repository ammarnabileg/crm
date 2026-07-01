<?php

declare(strict_types=1);

namespace Nizam\Behavior\Infrastructure\Persistence\InMemory;

use Nizam\Behavior\Domain\BehaviorProfile;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\Port\BehaviorProfileRepository;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\TenantId;

/**
 * An in-memory, tenant-scoped {@see BehaviorProfileRepository} for tests and local wiring.
 *
 * Profiles are held in a process-local map keyed by tenant and profile id. Every read is tenant
 * scoped — a profile is only ever returned to its owning tenant — so the adapter faithfully models
 * the isolation the PDO adapter enforces at the database. It is not persistent and not concurrency
 * safe; it exists so the module can run and be tested without a database.
 */
final class InMemoryBehaviorProfileRepository implements BehaviorProfileRepository
{
    /**
     * @var array<string, array<string, BehaviorProfile>> Profiles keyed by tenant id, then profile id.
     */
    private array $profiles = [];

    /**
     * Persist a profile, inserting or replacing the stored copy for its tenant.
     */
    public function save(BehaviorProfile $profile): void
    {
        $tenantKey = $profile->tenantId()->toString();
        $this->profiles[$tenantKey][$profile->profileId()->toString()] = $profile;
    }

    /**
     * Load a profile by id within a tenant, or null when none exists for that tenant.
     */
    public function ofId(TenantId $tenantId, BehaviorProfileId $id): ?BehaviorProfile
    {
        return $this->profiles[$tenantId->toString()][$id->toString()] ?? null;
    }

    /**
     * Load the profile bound to a role within a tenant, or null when none exists.
     */
    public function ofRole(TenantId $tenantId, RoleId $roleId): ?BehaviorProfile
    {
        foreach ($this->profiles[$tenantId->toString()] ?? [] as $profile) {
            if ($profile->roleId()->equals($roleId)) {
                return $profile;
            }
        }

        return null;
    }

    /**
     * Mint the next identity for a new profile.
     */
    public function nextIdentity(): BehaviorProfileId
    {
        return BehaviorProfileId::generate();
    }

    /**
     * Whether a profile already exists for a role within a tenant.
     */
    public function existsForRole(TenantId $tenantId, RoleId $roleId): bool
    {
        return $this->ofRole($tenantId, $roleId) !== null;
    }
}
