<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * How a role reaches decisions.
 *
 * A behavior style trait: one of several axes that together describe how a role performs work.
 * String-backed so the value persists stably in JSONB trait columns and read models.
 */
enum DecisionStyle: string
{
    /** Decisions are grounded in measured data and analysis. */
    case DataDriven = 'data_driven';

    /** Decisions are reached after seeking input from relevant stakeholders. */
    case Consultative = 'consultative';

    /** Decisions are made unilaterally and communicated as direction. */
    case Directive = 'directive';

    /** Decisions favor caution, preferring safe options under uncertainty. */
    case Cautious = 'cautious';

    /** Decision authority is pushed down to the most appropriate level. */
    case Delegative = 'delegative';
}
