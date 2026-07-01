<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Service;

use Nizam\Kernel\Domain\Clock;
use Nizam\Platform\Plugin\Port\HealthCheck;
use Nizam\Platform\Plugin\Port\HealthCheckResolver;
use Nizam\Platform\Plugin\PluginContext;
use Nizam\Platform\Plugin\PluginHealthStatus;
use Nizam\Platform\Plugin\RegisteredPlugin;
use Throwable;

/**
 * Runs a plugin's self-reported health check and yields a {@see PluginHealthStatus}.
 *
 * A plugin may declare a {@see HealthCheck} implementation in its manifest; this service resolves that
 * check through the {@see HealthCheckResolver} port and invokes it with the plugin's
 * {@see PluginContext}, producing a Healthy / Degraded / Unhealthy status with a message and the
 * instant it was taken. The check runs inside a fault-catching boundary: if the plugin's check throws,
 * the fault is contained and reported as an *unhealthy* status rather than propagating — a broken
 * health check is itself a health signal, not a crash. When a plugin declares no health check, the
 * service reports it healthy by default (there is nothing to fail). The resulting status is recorded on
 * the {@see RegisteredPlugin} as its last-known health and also returned to the caller.
 */
final class PluginHealthChecker
{
    /**
     * @param HealthCheckResolver $resolver Resolves a manifest's declared health-check class to an instance.
     * @param Clock               $clock    The time source stamping each health status.
     */
    public function __construct(
        private readonly HealthCheckResolver $resolver,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Run the plugin's health check and record the outcome on the plugin.
     *
     * @param RegisteredPlugin $plugin  The plugin to check.
     * @param PluginContext    $context The context the check runs within.
     *
     * @return PluginHealthStatus The health status produced (also recorded on the plugin).
     */
    public function run(RegisteredPlugin $plugin, PluginContext $context): PluginHealthStatus
    {
        $status = $this->evaluate($plugin, $context);
        $plugin->recordHealth($status);

        return $status;
    }

    /**
     * Evaluate the plugin's health, containing any fault as an unhealthy status.
     */
    private function evaluate(RegisteredPlugin $plugin, PluginContext $context): PluginHealthStatus
    {
        $check = $this->resolveCheck($plugin);
        if ($check === null) {
            return PluginHealthStatus::healthy(
                $this->clock->now(),
                sprintf('Plugin "%s" declares no health check; assumed healthy.', $plugin->name()),
            );
        }

        try {
            return $check->check($context);
        } catch (Throwable $throwable) {
            return PluginHealthStatus::unhealthy(
                $this->clock->now(),
                sprintf(
                    'Health check for plugin "%s" threw %s: %s',
                    $plugin->name(),
                    $throwable::class,
                    $throwable->getMessage(),
                ),
            );
        }
    }

    /**
     * Resolve the plugin's declared health check, containing a resolution fault as no check.
     *
     * A resolver that itself throws is treated as "no check available" so that resolution failure does
     * not crash the caller; the absence is then reported as healthy by {@see self::evaluate()}.
     */
    private function resolveCheck(RegisteredPlugin $plugin): ?HealthCheck
    {
        try {
            return $this->resolver->resolve($plugin->manifest());
        } catch (Throwable) {
            return null;
        }
    }
}
