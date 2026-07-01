<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Contract;

use Nizam\Platform\Plugin\PluginInterface;

/**
 * The SDK contract for a team-leader plugin.
 *
 * A team-leader plugin coordinates a group of worker plugins, distributing tasks and consolidating
 * their results. Beyond the base {@see PluginInterface}, it must declare the worker capabilities it
 * expects its team to provide, so the platform can verify a coherent team can be assembled.
 */
interface TeamLeaderPlugin extends PluginInterface
{
    /**
     * The worker capabilities this team leader coordinates.
     *
     * @return list<string>
     */
    public function coordinatedCapabilities(): array;
}
