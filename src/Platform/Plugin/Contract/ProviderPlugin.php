<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Contract;

use Nizam\Platform\Plugin\PluginInterface;

/**
 * The SDK contract for a provider plugin.
 *
 * A provider plugin supplies an infrastructure capability the rest of the platform consumes — an LLM
 * backend, an object store, a vector index and so on. Beyond the base {@see PluginInterface}, it must
 * name the capability it provides so the platform can bind consumers to a chosen provider.
 */
interface ProviderPlugin extends PluginInterface
{
    /**
     * The identifier of the capability this provider supplies (e.g. `llm`, `object_store`).
     */
    public function providedCapability(): string;
}
