<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Exception;

/**
 * Raised when a rollback or lookup targets a profile revision version that does not exist.
 *
 * A {@see \Nizam\Behavior\Domain\BehaviorProfile} keeps an append-only list of revisions; asking to
 * roll back to a version that was never recorded is a domain-rule violation, not an infrastructure
 * fault. Carries error code `BEHAVIOR.UNKNOWN_REVISION`.
 */
final class UnknownRevisionException extends BehaviorDomainException
{
    /**
     * The stable error code for this violation.
     */
    public const string CODE = 'BEHAVIOR.UNKNOWN_REVISION';

    /**
     * Build the exception for a specific missing version on a specific profile.
     *
     * @param int    $version   The revision version that was requested but not found.
     * @param string $profileId The string id of the profile queried.
     */
    public static function forVersion(int $version, string $profileId): self
    {
        return new self(
            self::CODE,
            sprintf('Revision version %d does not exist on behavior profile %s.', $version, $profileId),
        );
    }
}
