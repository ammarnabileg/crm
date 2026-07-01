<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Exception;

/**
 * Raised when a change is applied with fewer approved evidences than the profile requires.
 *
 * A {@see \Nizam\Behavior\Domain\ValueObject\BehaviorTraits} declares an `evidenceRequirements`
 * minimum; applying a trait change backed by too little approved evidence would break the
 * evidence-count guard that keeps behavior evolution defensible. Carries error code
 * `BEHAVIOR.INSUFFICIENT_EVIDENCE`.
 */
final class InsufficientEvidenceException extends BehaviorDomainException
{
    /**
     * The stable error code for this violation.
     */
    public const string CODE = 'BEHAVIOR.INSUFFICIENT_EVIDENCE';

    /**
     * Build the exception describing how much evidence was provided versus required.
     *
     * @param int $provided The number of approved evidences supplied.
     * @param int $required The minimum number of approved evidences the profile requires.
     */
    public static function forCounts(int $provided, int $required): self
    {
        return new self(
            self::CODE,
            sprintf(
                'A behavior change requires at least %d approved evidence(s) but only %d were supplied.',
                $required,
                $provided,
            ),
        );
    }
}
