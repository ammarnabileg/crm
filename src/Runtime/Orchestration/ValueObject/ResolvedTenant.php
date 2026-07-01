<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\ValueObject;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * The tenant record the {@see \Nizam\Runtime\Orchestration\Port\TenantProvider} resolves for a request.
 *
 * The orchestrator resolves the tenant before it does anything else: a request for an unknown or
 * inactive tenant is rejected before an execution begins. This value object carries the resolved
 * {@see TenantId}, a display name for logs and read models, and whether the tenant is currently active.
 * Being a value object it is immutable.
 */
final class ResolvedTenant implements ValueObject
{
    /**
     * @param TenantId $tenantId The resolved tenant identity.
     * @param string   $name     A human-readable tenant name.
     * @param bool     $active   Whether the tenant is currently active.
     */
    public function __construct(
        private readonly TenantId $tenantId,
        private readonly string $name,
        private readonly bool $active,
    ) {
        Assert::notEmpty($name, 'A resolved tenant must carry a non-empty name.');
    }

    /**
     * The resolved tenant identity.
     */
    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * The human-readable tenant name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Whether the tenant is currently active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->tenantId->equals($this->tenantId)
            && $other->name === $this->name
            && $other->active === $this->active;
    }
}
