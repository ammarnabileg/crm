<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * The lifecycle status of a {@see \Nizam\Behavior\Domain\BehaviorProfile}.
 *
 * A profile is drafted, activated, superseded by a newer active profile for the same role, or
 * archived. String-backed for stable persistence in the profile table and read models.
 */
enum ProfileStatus: string
{
    /** Being defined; not yet governing behavior. */
    case Draft = 'draft';

    /** The live, governing profile for its role. */
    case Active = 'active';

    /** Replaced by a newer profile; retained for history. */
    case Superseded = 'superseded';

    /** Retired; no longer in use. */
    case Archived = 'archived';

    /**
     * Whether the profile is in a state that accepts approved trait changes.
     *
     * Changes may be applied while a profile is {@see self::Draft} or {@see self::Active}.
     */
    public function acceptsChanges(): bool
    {
        return $this === self::Draft || $this === self::Active;
    }
}
