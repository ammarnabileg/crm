<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Testing;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Platform\Plugin\PermissionSet;
use Nizam\Platform\Plugin\PluginPermission;
use Nizam\Runtime\Orchestration\Port\PermissionProvider;

/**
 * A real, seedable in-memory {@see PermissionProvider} for tests and safe defaults.
 *
 * It resolves the {@see PermissionSet} granted for a tenant from an in-process map; an unseeded tenant
 * resolves to the empty set, so by default a worker requiring any permission is denied at the
 * coordinator's gate. Seeding a grant lets tests authorise dispatch. This is a working adapter, not a
 * stub — it returns exactly the granted set the coordinator checks a worker's required set against.
 */
final class InMemoryPermissionProvider implements PermissionProvider
{
    /** @var array<string, PermissionSet> */
    private array $grants = [];

    /**
     * Seed the permission set granted to a tenant.
     */
    public function seed(TenantId $tenantId, PermissionSet $granted): self
    {
        $this->grants[$tenantId->toString()] = $granted;

        return $this;
    }

    /**
     * Seed a tenant grant from a list of permission keys, a convenience over building a set.
     *
     * @param list<string> $keys
     */
    public function grantKeys(TenantId $tenantId, array $keys): self
    {
        $permissions = [];
        foreach ($keys as $key) {
            $permissions[] = new PluginPermission($key, sprintf('Granted permission "%s".', $key));
        }

        return $this->seed($tenantId, PermissionSet::of($permissions));
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(TenantId $tenantId, ?UserId $userId, string $intentRef): PermissionSet
    {
        return $this->grants[$tenantId->toString()] ?? PermissionSet::empty();
    }
}
