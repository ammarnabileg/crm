<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Contract;

use Nizam\Platform\Plugin\PluginInterface;

/**
 * The SDK contract for a manager plugin.
 *
 * A manager plugin plans and oversees the work of subordinate team leaders and workers. Beyond the
 * base {@see PluginInterface}, it must expose the set of roles it is capable of managing so the
 * platform can assemble an org structure and route escalations to the right manager.
 */
interface ManagerPlugin extends PluginInterface
{
    /**
     * The role identifiers this manager is capable of managing.
     *
     * @return list<string>
     */
    public function managedRoles(): array;
}
