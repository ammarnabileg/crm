<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Exception;

/**
 * Raised on any attempt to re-bind a profile, proposal, or observation to a different role.
 *
 * Behavior is modeled per-role; the {@see \Nizam\Behavior\Domain\RoleId} of a profile is fixed at
 * creation and immutable thereafter. Applying a change, proposal, or observation whose role does not
 * match the target's role would violate that binding. Carries error code
 * `BEHAVIOR.IMMUTABLE_ROLE_BINDING`.
 */
final class ImmutableRoleBindingException extends BehaviorDomainException
{
    /**
     * The stable error code for this violation.
     */
    public const string CODE = 'BEHAVIOR.IMMUTABLE_ROLE_BINDING';

    /**
     * Build the exception describing the mismatch between the bound and the offered role.
     *
     * @param string $boundRoleId    The role the target is permanently bound to.
     * @param string $offeredRoleId  The role that was offered and rejected.
     */
    public static function forMismatch(string $boundRoleId, string $offeredRoleId): self
    {
        return new self(
            self::CODE,
            sprintf(
                'This behavior aggregate is bound to role %s and cannot be re-bound to role %s.',
                $boundRoleId,
                $offeredRoleId,
            ),
        );
    }
}
