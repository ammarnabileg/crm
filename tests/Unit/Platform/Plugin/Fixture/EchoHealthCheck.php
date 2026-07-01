<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin\Fixture;

use DateTimeImmutable;
use Nizam\Platform\Plugin\Port\HealthCheck;
use Nizam\Platform\Plugin\PluginContext;
use Nizam\Platform\Plugin\PluginHealthStatus;

/**
 * A concrete {@see HealthCheck} test fixture that reports healthy.
 *
 * Used to prove the validator accepts a well-formed `healthCheckClass` and that the health checker can
 * resolve and invoke a real check. It reads no external state, honouring the port's purity contract.
 */
final class EchoHealthCheck implements HealthCheck
{
    /**
     * Report the plugin as healthy at a fixed instant.
     */
    public function check(PluginContext $context): PluginHealthStatus
    {
        return PluginHealthStatus::healthy(
            new DateTimeImmutable('2026-07-01T12:00:00+00:00'),
            'Echo health check: OK',
        );
    }
}
