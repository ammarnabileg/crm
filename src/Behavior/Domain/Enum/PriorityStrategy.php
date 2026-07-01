<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * How a role orders competing work.
 *
 * A behavior style trait. String-backed for stable persistence in trait columns and read models.
 */
enum PriorityStrategy: string
{
    /** Work is handled in arrival order. */
    case FIFO = 'fifo';

    /** Work with the nearest deadline is handled first. */
    case DeadlineFirst = 'deadline_first';

    /** Work with the highest business value is handled first. */
    case ValueFirst = 'value_first';

    /** Work with the highest risk is handled first. */
    case RiskFirst = 'risk_first';
}
