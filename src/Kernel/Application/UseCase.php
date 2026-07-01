<?php

declare(strict_types=1);

namespace Nizam\Kernel\Application;

/**
 * Marker interface for an application use case.
 *
 * A use case is a single, named application operation that orchestrates domain objects and ports
 * to fulfil a user goal. Command/query handlers are the most common form; this marker lets the
 * platform discover and describe use cases generically.
 */
interface UseCase
{
}
