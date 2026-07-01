<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain;

use Nizam\Kernel\Domain\Identifier;

/**
 * The identity of a {@see BehaviorChangeProposal} aggregate.
 *
 * A proposal captures a recommended change to a role's behavior profile, awaiting explicit human
 * approval; nothing about production behavior changes until a proposal is approved and applied.
 * This typed identifier keeps proposal ids distinct from profile, observation, and role ids.
 */
final class ProposalId extends Identifier
{
}
