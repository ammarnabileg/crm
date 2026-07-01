<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain;

use Nizam\Kernel\Domain\Identifier;

/**
 * The identity of an {@see ExecutionStep} within an {@see Execution}.
 *
 * A step is one assigned unit of work (typically dispatched to a worker plugin). This typed
 * identifier prevents mixing a step id with an {@see ExecutionId} or {@see SessionId} at the type
 * level while reusing the shared UUID v7 implementation from the Kernel.
 */
final class ExecutionStepId extends Identifier
{
}
