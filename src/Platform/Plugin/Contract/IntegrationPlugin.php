<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Contract;

use Nizam\Platform\Plugin\PluginInterface;

/**
 * The SDK contract for an integration plugin.
 *
 * An integration plugin connects the platform to an external system or third-party service. Beyond
 * the base {@see PluginInterface}, it must identify the external system it integrates with so the
 * platform can group integrations, surface them in the marketplace and reason about outbound scope.
 */
interface IntegrationPlugin extends PluginInterface
{
    /**
     * The identifier of the external system this plugin integrates with (e.g. `salesforce`).
     */
    public function externalSystem(): string;
}
