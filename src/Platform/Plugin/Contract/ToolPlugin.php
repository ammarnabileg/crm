<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Contract;

use Nizam\Platform\Plugin\PluginInterface;

/**
 * The SDK contract for a tool plugin.
 *
 * A tool plugin is a discrete, invocable capability an agent may call to act on the world (for
 * example, send an email or query a database). Beyond the base {@see PluginInterface}, it must expose
 * its callable tool name and input schema so agents can discover and invoke it safely.
 */
interface ToolPlugin extends PluginInterface
{
    /**
     * The stable, callable name of the tool (unique within the plugin).
     */
    public function toolName(): string;

    /**
     * The declarative input schema describing the tool's parameters.
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;
}
