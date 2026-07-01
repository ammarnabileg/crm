<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Exception;

use RuntimeException;

/**
 * Raised by the Behavior application layer when a use case cannot be carried out.
 *
 * These are orchestration-level failures — a referenced profile or proposal does not exist for the
 * tenant, a profile already exists for a role being drafted, or approved practice yields no
 * defensible change — as opposed to domain invariant violations (which surface as
 * {@see \Nizam\Behavior\Domain\Exception\BehaviorDomainException}). Each instance carries a stable,
 * dotted error code under the {@see self::CODE_PREFIX} namespace so callers can branch on the failure
 * kind without matching on messages. It extends the SPL {@see \RuntimeException} to keep the
 * application layer coupled only to PHP, the Kernel, and its own domain.
 */
final class BehaviorApplicationException extends RuntimeException
{
    /**
     * The stable prefix shared by every Behavior application error code.
     */
    public const string CODE_PREFIX = 'BEHAVIOR.APPLICATION';

    /**
     * @param string $errorCode The stable, dotted error code (e.g. `BEHAVIOR.APPLICATION.PROFILE_NOT_FOUND`).
     * @param string $message   A human-readable description of the failure.
     */
    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * The referenced profile does not exist within the acting tenant.
     */
    public static function profileNotFound(string $profileId): self
    {
        return new self(
            self::CODE_PREFIX . '.PROFILE_NOT_FOUND',
            sprintf('No behavior profile "%s" exists for this tenant.', $profileId),
        );
    }

    /**
     * No behavior profile is bound to the referenced role within the acting tenant.
     */
    public static function profileForRoleNotFound(string $roleId): self
    {
        return new self(
            self::CODE_PREFIX . '.PROFILE_FOR_ROLE_NOT_FOUND',
            sprintf('No behavior profile is bound to role "%s" for this tenant.', $roleId),
        );
    }

    /**
     * The referenced proposal does not exist within the acting tenant.
     */
    public static function proposalNotFound(string $proposalId): self
    {
        return new self(
            self::CODE_PREFIX . '.PROPOSAL_NOT_FOUND',
            sprintf('No behavior change proposal "%s" exists for this tenant.', $proposalId),
        );
    }

    /**
     * A profile already exists for the role a draft was requested for.
     */
    public static function profileAlreadyExistsForRole(string $roleId): self
    {
        return new self(
            self::CODE_PREFIX . '.PROFILE_ALREADY_EXISTS_FOR_ROLE',
            sprintf('A behavior profile already exists for role "%s"; draft is not permitted.', $roleId),
        );
    }

    /**
     * The approved practice for the role supports no change, so no proposal can be raised.
     */
    public static function noChangeToPropose(string $roleId): self
    {
        return new self(
            self::CODE_PREFIX . '.NO_CHANGE_TO_PROPOSE',
            sprintf(
                'Approved practice for role "%s" already matches the current profile; there is nothing to propose.',
                $roleId,
            ),
        );
    }

    /**
     * The stable, dotted error code identifying the kind of failure.
     */
    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
