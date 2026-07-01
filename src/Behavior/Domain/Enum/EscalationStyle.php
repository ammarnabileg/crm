<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * When a role escalates issues to higher authority.
 *
 * A behavior style trait. String-backed for stable persistence in trait columns and read models.
 */
enum EscalationStyle: string
{
    /** Issues are surfaced early, before they compound. */
    case Early = 'early';

    /** Issues are escalated when a defined threshold is crossed. */
    case OnThreshold = 'on_threshold';

    /** Escalation is a last resort after other avenues are exhausted. */
    case LastResort = 'last_resort';
}
