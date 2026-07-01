<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain;

use Nizam\Kernel\Domain\Identifier;

/**
 * The identity of a {@see BehaviorProfile} aggregate.
 *
 * A profile groups the versioned, append-only history of how a role performs work. This typed
 * identifier prevents mixing a profile id with a {@see ProposalId}, {@see ObservationId}, or
 * {@see RoleId} at the type level while reusing the shared UUID v7 implementation.
 */
final class BehaviorProfileId extends Identifier
{
}
