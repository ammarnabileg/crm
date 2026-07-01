<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Port;

use Nizam\Platform\Plugin\PluginManifest;

/**
 * The port that resolves a plugin's declared `healthCheckClass` into a live {@see HealthCheck}.
 *
 * A plugin advertises its health check as an FQCN in its manifest; the platform must turn that class
 * name into an instance without the domain knowing how (container autowiring, reflection, a registry).
 * The {@see \Nizam\Platform\Plugin\Service\PluginHealthChecker} depends on this port so that resolution
 * — the only I/O in the health path — lives in an Infrastructure adapter, keeping the checker pure.
 * Returns null when the manifest declares no health check, so the checker can report a sensible
 * default rather than treating the absence as a failure.
 */
interface HealthCheckResolver
{
    /**
     * Resolve the health check declared by the manifest, if any.
     *
     * @param PluginManifest $manifest The manifest whose `healthCheckClass` is to be resolved.
     *
     * @return HealthCheck|null The resolved check, or null when the manifest declares none.
     */
    public function resolve(PluginManifest $manifest): ?HealthCheck;
}
