<?php

declare(strict_types=1);

namespace Nizam\Kernel\Domain;

use Psr\Clock\ClockInterface;

/**
 * The domain's time port.
 *
 * Extends the PSR-20 {@see ClockInterface} so any PSR-compatible consumer works, while giving the
 * Kernel a namespaced type the domain can depend on without reaching into the platform. Domain and
 * application code request the current instant through this port; the concrete
 * {@see \Nizam\Platform\Support\SystemClock} (or a fake in tests) is injected at the boundary.
 */
interface Clock extends ClockInterface
{
}
