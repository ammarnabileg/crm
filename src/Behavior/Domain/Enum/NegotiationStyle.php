<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * How a role negotiates.
 *
 * A behavior style trait. String-backed for stable persistence in trait columns and read models.
 */
enum NegotiationStyle: string
{
    /** Accommodates the counterparty to preserve the relationship. */
    case Accommodating = 'accommodating';

    /** Negotiates on principle and objective criteria. */
    case Principled = 'principled';

    /** Negotiates competitively to maximize own position. */
    case Competitive = 'competitive';
}
