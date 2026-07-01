<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * How much risk a role is willing to accept.
 *
 * A behavior style trait. The {@see self::Elevated} level is privileged: a profile may only adopt
 * it when tenant/role policy explicitly permits it (enforced by the elevated-risk factory guard on
 * {@see \Nizam\Behavior\Domain\ValueObject\BehaviorTraits}). String-backed for stable persistence.
 */
enum RiskTolerance: string
{
    /** Actively avoids risk; prefers the safest available option. */
    case Averse = 'averse';

    /** Accepts only low, well-understood risk. */
    case Low = 'low';

    /** Balances risk against reward pragmatically. */
    case Balanced = 'balanced';

    /** Accepts elevated risk — permitted only when policy allows. */
    case Elevated = 'elevated';

    /**
     * Whether this level is the privileged elevated level that requires policy approval.
     */
    public function requiresElevatedRiskPolicy(): bool
    {
        return $this === self::Elevated;
    }
}
