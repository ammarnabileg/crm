<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * How a role follows up on open items.
 *
 * A behavior style trait. String-backed for stable persistence in trait columns and read models.
 */
enum FollowUpStrategy: string
{
    /** No systematic follow-up. */
    case None = 'none';

    /** Follow-up happens on a set schedule. */
    case Scheduled = 'scheduled';

    /** Follow-up continues until the item is resolved. */
    case UntilResolved = 'until_resolved';
}
