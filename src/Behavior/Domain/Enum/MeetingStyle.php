<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * How a role conducts coordination and meetings.
 *
 * A behavior style trait. String-backed for stable persistence in trait columns and read models.
 */
enum MeetingStyle: string
{
    /** Coordination happens asynchronously, avoiding live meetings. */
    case Async = 'async';

    /** Short synchronous check-ins. */
    case BriefSync = 'brief_sync';

    /** Meetings run against a structured agenda. */
    case StructuredAgenda = 'structured_agenda';
}
