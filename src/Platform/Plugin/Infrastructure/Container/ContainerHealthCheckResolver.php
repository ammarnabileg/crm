<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Infrastructure\Container;

use Nizam\Platform\Container\Container;
use Nizam\Platform\Plugin\Exception\PluginValidationException;
use Nizam\Platform\Plugin\Port\HealthCheck;
use Nizam\Platform\Plugin\Port\HealthCheckResolver;
use Nizam\Platform\Plugin\PluginManifest;
use Throwable;

/**
 * A {@see HealthCheckResolver} that resolves a plugin's `healthCheckClass` through the container.
 *
 * A plugin advertises its health check as an FQCN in its manifest. This adapter turns that class name
 * into a live {@see HealthCheck} by resolving it via the platform {@see Container} (autowiring its
 * dependencies). When the manifest declares no health check it returns null, so the checker reports a
 * sensible default rather than treating the absence as a failure. A resolved object that is not a
 * {@see HealthCheck}, or a construction fault, is surfaced as a {@see PluginValidationException} naming
 * the plugin.
 */
final class ContainerHealthCheckResolver implements HealthCheckResolver
{
    /**
     * @param Container $container The platform container used to resolve health-check classes.
     */
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Resolve the health check declared by the manifest, if any.
     *
     * @param PluginManifest $manifest The manifest whose `healthCheckClass` is to be resolved.
     *
     * @return HealthCheck|null The resolved check, or null when the manifest declares none.
     *
     * @throws PluginValidationException When the declared class cannot be constructed or is not a check.
     */
    public function resolve(PluginManifest $manifest): ?HealthCheck
    {
        $class = $manifest->healthCheckClass();
        if ($class === null) {
            return null;
        }

        try {
            $instance = $this->container->make($class);
        } catch (Throwable $e) {
            throw PluginValidationException::forReason(sprintf(
                'Plugin "%s" health check "%s" could not be instantiated: %s',
                $manifest->name(),
                $class,
                $e->getMessage(),
            ));
        }

        if (!$instance instanceof HealthCheck) {
            throw PluginValidationException::forReason(sprintf(
                'Plugin "%s" health check "%s" did not resolve to a %s.',
                $manifest->name(),
                $class,
                HealthCheck::class,
            ));
        }

        return $instance;
    }
}
