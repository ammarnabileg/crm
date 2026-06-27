<?php

declare(strict_types=1);

namespace App\Services\Plugin;

use App\Contracts\Plugin\PluginInterface;

/**
 * Convenience base for plugins (docs/51 Plugin SDK): no-op lifecycle hooks so a
 * plugin need only implement key(), manifest() and register().
 */
abstract class AbstractPlugin implements PluginInterface
{
    public function onInstall(): void
    {
    }

    public function onEnable(): void
    {
    }

    public function onDisable(): void
    {
    }

    public function onUninstall(): void
    {
    }
}
