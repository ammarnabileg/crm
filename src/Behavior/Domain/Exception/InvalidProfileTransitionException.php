<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Exception;

use Nizam\Behavior\Domain\Enum\ProfileStatus;

/**
 * Raised when a profile lifecycle operation is attempted from a status that forbids it.
 *
 * The {@see \Nizam\Behavior\Domain\BehaviorProfile} state machine only permits certain transitions
 * (for example, only a {@see ProfileStatus::Draft} profile may be activated, and only a
 * {@see ProfileStatus::Draft} or {@see ProfileStatus::Active} profile may accept changes). Carries
 * error code `BEHAVIOR.INVALID_PROFILE_TRANSITION`.
 */
final class InvalidProfileTransitionException extends BehaviorDomainException
{
    /**
     * The stable error code for this violation.
     */
    public const string CODE = 'BEHAVIOR.INVALID_PROFILE_TRANSITION';

    /**
     * Build the exception describing the operation and the offending current status.
     *
     * @param string        $operation The lifecycle operation attempted (e.g. "activate").
     * @param ProfileStatus $current   The status the profile was actually in.
     */
    public static function forOperation(string $operation, ProfileStatus $current): self
    {
        return new self(
            self::CODE,
            sprintf('Cannot %s a behavior profile in status "%s".', $operation, $current->value),
        );
    }
}
