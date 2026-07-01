<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\ValueObject;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * The user record the {@see \Nizam\Runtime\Orchestration\Port\UserProvider} resolves for a request.
 *
 * When a request names an acting user, the orchestrator resolves that user (scoped to the request's
 * tenant) so a request on behalf of an unknown or inactive user is rejected before an execution begins.
 * This value object carries the resolved {@see UserId}, the {@see TenantId} the user belongs to, a
 * display name, and whether the user is currently active. Being a value object it is immutable.
 */
final class ResolvedUser implements ValueObject
{
    /**
     * @param UserId   $userId   The resolved user identity.
     * @param TenantId $tenantId The tenant the user belongs to.
     * @param string   $name     A human-readable user name.
     * @param bool     $active   Whether the user is currently active.
     */
    public function __construct(
        private readonly UserId $userId,
        private readonly TenantId $tenantId,
        private readonly string $name,
        private readonly bool $active,
    ) {
        Assert::notEmpty($name, 'A resolved user must carry a non-empty name.');
    }

    /**
     * The resolved user identity.
     */
    public function userId(): UserId
    {
        return $this->userId;
    }

    /**
     * The tenant the user belongs to.
     */
    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * The human-readable user name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Whether the user is currently active.
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
            && $other->userId->equals($this->userId)
            && $other->tenantId->equals($this->tenantId)
            && $other->name === $this->name
            && $other->active === $this->active;
    }
}
