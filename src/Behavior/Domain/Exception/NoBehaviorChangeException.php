<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Exception;

/**
 * Raised when an "approved change" would leave a profile's traits unchanged.
 *
 * Appending a new revision whose traits are identical to the current traits would pollute the
 * append-only history with a meaningless version. A change must actually differ from what it
 * replaces. Carries error code `BEHAVIOR.NO_CHANGE`.
 */
final class NoBehaviorChangeException extends BehaviorDomainException
{
    /**
     * The stable error code for this violation.
     */
    public const string CODE = 'BEHAVIOR.NO_CHANGE';

    /**
     * Build the exception for an attempted no-op change.
     */
    public static function create(): self
    {
        return new self(
            self::CODE,
            'A behavior change must differ from the current traits; the supplied traits are identical.',
        );
    }
}
