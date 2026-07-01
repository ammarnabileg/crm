<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain;

use Nizam\Kernel\Domain\Identifier;

/**
 * The identity of an {@see Execution} aggregate.
 *
 * An execution is one run of work through the Runtime, from request to a manager's decision. This
 * typed identifier keeps an execution id from being confused with an {@see ExecutionStepId} or a
 * {@see SessionId} at the type level, while reusing the shared UUID v7 implementation from the
 * Kernel. It is also the idempotency key the execution engine guards on and the stream key of the
 * append-only event store.
 */
final class ExecutionId extends Identifier
{
}
