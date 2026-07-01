<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Contract;

use Nizam\Platform\Plugin\PluginInterface;

/**
 * The SDK contract for an automation plugin.
 *
 * An automation plugin reacts to platform events or runs on a schedule to perform work without direct
 * human initiation. Beyond the base {@see PluginInterface}, it must declare the triggers (event names
 * or schedule expressions) that cause it to run, so the platform can wire it to the event bus or
 * scheduler.
 */
interface AutomationPlugin extends PluginInterface
{
    /**
     * The triggers that cause this automation to run (event names and/or schedule expressions).
     *
     * @return list<string>
     */
    public function triggers(): array;
}
