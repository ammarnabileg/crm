<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Testing;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Runtime\Orchestration\Port\UserProvider;
use Nizam\Runtime\Orchestration\ValueObject\ResolvedUser;

/**
 * A real, seedable in-memory {@see UserProvider} for tests and safe defaults.
 *
 * It resolves users from an in-process map keyed by (tenant, user); an unseeded or cross-tenant user
 * resolves to null, letting the orchestrator reject it exactly as a missing production user would. This
 * is a working adapter, not a stub — it enforces the same tenant-scoping and active/inactive contract
 * the production directory does.
 */
final class InMemoryUserProvider implements UserProvider
{
    /** @var array<string, ResolvedUser> */
    private array $users = [];

    /**
     * Seed a resolvable user within a tenant.
     *
     * @param string $name   A human-readable user name.
     * @param bool   $active Whether the user is active.
     */
    public function seed(TenantId $tenantId, UserId $userId, string $name = 'Test User', bool $active = true): self
    {
        $this->users[$this->key($tenantId, $userId)] = new ResolvedUser($userId, $tenantId, $name, $active);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(TenantId $tenantId, UserId $userId): ?ResolvedUser
    {
        return $this->users[$this->key($tenantId, $userId)] ?? null;
    }

    /**
     * The composite map key scoping a user to its tenant.
     */
    private function key(TenantId $tenantId, UserId $userId): string
    {
        return $tenantId->toString() . "\0" . $userId->toString();
    }
}
