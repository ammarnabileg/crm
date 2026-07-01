<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Port;

use Nizam\Platform\Plugin\PluginContext;
use Nizam\Platform\Plugin\PluginHealthStatus;

/**
 * A plugin-supplied check of its own operational health.
 *
 * A plugin that declares a `healthCheckClass` in its manifest provides an implementation of this port;
 * the platform resolves and invokes it, passing the plugin's {@see PluginContext}, and stores the
 * returned {@see PluginHealthStatus} as the plugin's last-known health. The check must be pure with
 * respect to platform state — it inspects the plugin's own dependencies and reports a level. The
 * platform runs it inside a fault-catching sandbox, so an implementation that throws is treated as an
 * unhealthy result rather than propagating.
 */
interface HealthCheck
{
    /**
     * Check the plugin's operational health.
     *
     * @param PluginContext $context The context the check runs within.
     *
     * @return PluginHealthStatus The health level, message and check instant.
     */
    public function check(PluginContext $context): PluginHealthStatus;
}
