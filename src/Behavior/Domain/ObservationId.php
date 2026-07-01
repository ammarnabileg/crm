<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain;

use Nizam\Kernel\Domain\Identifier;

/**
 * The identity of a {@see BehaviorObservation} entity.
 *
 * An observation records one approved piece of business practice (a decision, task execution,
 * policy, review, …) that may drive the evolution of a role's behavior profile. This typed
 * identifier keeps observation ids distinct from profile, proposal, and role ids.
 */
final class ObservationId extends Identifier
{
}
