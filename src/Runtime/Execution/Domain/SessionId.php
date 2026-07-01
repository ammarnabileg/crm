<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain;

use Nizam\Kernel\Domain\Identifier;

/**
 * The identity of a conversational/interaction session that an {@see Execution} may belong to.
 *
 * A single session (for example a user's ongoing request thread) can span several executions. This
 * typed identifier keeps a session id distinct from an {@see ExecutionId} or {@see ExecutionStepId}
 * at the type level while reusing the shared UUID v7 implementation from the Kernel.
 */
final class SessionId extends Identifier
{
}
