<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain;

use Nizam\Kernel\Domain\Identifier;

/**
 * The identity of a role to which a professional behavior profile is bound.
 *
 * Behavior in the Nizam platform is modeled per-role, never per-person: a {@see BehaviorProfile}
 * belongs to a {@see RoleId} and consolidates the approved practice of every employee performing
 * that role. Being its own type (rather than a bare string or another {@see Identifier}) prevents
 * accidentally binding a profile, proposal, or observation to the wrong role.
 */
final class RoleId extends Identifier
{
}
