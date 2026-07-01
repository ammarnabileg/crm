<?php

declare(strict_types=1);

namespace Nizam\Kernel\Tenancy;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;

/**
 * An immutable snapshot of who and what a single request/job is being handled on behalf of.
 *
 * Bundles the correlation id (for tracing a request across services and logs), the tenant it is
 * scoped to, and, when authenticated, the acting user. Being immutable, it can be passed freely
 * and safely captured in closures and log context.
 */
final class RequestContext
{
    /**
     * @param string        $correlationId A unique id used to correlate logs/events for this request.
     * @param TenantId|null $tenantId      The tenant in scope, if any.
     * @param UserId|null   $userId        The acting user, if authenticated.
     */
    public function __construct(
        private readonly string $correlationId,
        private readonly ?TenantId $tenantId = null,
        private readonly ?UserId $userId = null,
    ) {
    }

    /**
     * The correlation id for tracing this request.
     */
    public function correlationId(): string
    {
        return $this->correlationId;
    }

    /**
     * The tenant in scope, or null.
     */
    public function tenantId(): ?TenantId
    {
        return $this->tenantId;
    }

    /**
     * The acting user, or null.
     */
    public function userId(): ?UserId
    {
        return $this->userId;
    }

    /**
     * Whether a tenant is associated with this request.
     */
    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Whether an authenticated user is associated with this request.
     */
    public function isAuthenticated(): bool
    {
        return $this->userId !== null;
    }

    /**
     * Return a copy with the given user set (e.g. after authentication).
     */
    public function withUser(UserId $userId): self
    {
        return new self($this->correlationId, $this->tenantId, $userId);
    }

    /**
     * Return a copy with the given tenant set.
     */
    public function withTenant(TenantId $tenantId): self
    {
        return new self($this->correlationId, $tenantId, $this->userId);
    }
}
