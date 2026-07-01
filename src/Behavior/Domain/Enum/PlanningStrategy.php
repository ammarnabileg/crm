<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * How a role plans work ahead of execution.
 *
 * A behavior style trait. String-backed for stable persistence in trait columns and read models.
 */
enum PlanningStrategy: string
{
    /** Plans emerge just before they are needed. */
    case JustInTime = 'just_in_time';

    /** Work is planned up front in a structured way. */
    case Structured = 'structured';

    /** Work is planned around defined milestones. */
    case MilestoneBased = 'milestone_based';
}
