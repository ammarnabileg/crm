<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Exception;

/**
 * Raised when traits adopt {@see \Nizam\Behavior\Domain\Enum\RiskTolerance::Elevated} without policy.
 *
 * Elevated risk tolerance is privileged: {@see \Nizam\Behavior\Domain\ValueObject\BehaviorTraits}
 * may only be constructed with it through the elevated-risk factory guard, and only when the caller
 * asserts that tenant/role policy permits it. Carries error code `BEHAVIOR.ELEVATED_RISK_NOT_ALLOWED`.
 */
final class ElevatedRiskNotAllowedException extends BehaviorDomainException
{
    /**
     * The stable error code for this violation.
     */
    public const string CODE = 'BEHAVIOR.ELEVATED_RISK_NOT_ALLOWED';

    /**
     * Build the exception for an unauthorized attempt to adopt elevated risk tolerance.
     */
    public static function create(): self
    {
        return new self(
            self::CODE,
            'Elevated risk tolerance requires an explicit policy allowance and cannot be adopted otherwise.',
        );
    }
}
