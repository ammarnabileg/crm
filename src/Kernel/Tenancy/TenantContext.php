<?php

declare(strict_types=1);

namespace Nizam\Kernel\Tenancy;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Exception\PlatformException;
use Throwable;

/**
 * Holds the tenant currently in scope for a unit of work (typically one request or job).
 *
 * Tenant-scoped code reads the active tenant from here rather than threading a {@see TenantId}
 * through every call. The context is a single mutable holder, set at the boundary (e.g. by an HTTP
 * middleware that resolves the tenant) and cleared afterwards. {@see self::runWithTenant()} scopes a
 * tenant to a callback and always restores the previous tenant, even on exception, which makes it
 * safe for nesting and for long-lived workers that process many tenants in turn.
 */
final class TenantContext
{
    /**
     * The tenant currently in scope, or null when none is set.
     */
    private ?TenantId $tenantId = null;

    /**
     * Set the active tenant.
     */
    public function set(TenantId $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    /**
     * The active tenant, or null when none is set.
     */
    public function get(): ?TenantId
    {
        return $this->tenantId;
    }

    /**
     * The active tenant, requiring one to be set.
     *
     * @throws PlatformException When no tenant is currently in scope.
     */
    public function require(): TenantId
    {
        if ($this->tenantId === null) {
            throw new PlatformException('No tenant is currently set in the tenant context.');
        }

        return $this->tenantId;
    }

    /**
     * Whether a tenant is currently in scope.
     */
    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Clear the active tenant.
     */
    public function forget(): void
    {
        $this->tenantId = null;
    }

    /**
     * Run a callback with the given tenant in scope, restoring the previous tenant afterwards.
     *
     * The previous tenant (which may be null) is always restored, even when the callback throws,
     * so nested scopes and per-tenant loops never leak state.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     *
     * @throws Throwable Re-throws whatever the callback throws, after restoring the previous tenant.
     */
    public function runWithTenant(TenantId $tenantId, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->tenantId = $tenantId;

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
