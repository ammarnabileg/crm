<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Contract;

use Nizam\Platform\Plugin\PluginInterface;

/**
 * The SDK contract for a worker plugin.
 *
 * A worker plugin performs concrete units of work assigned to it by a team leader or manager. Beyond
 * the base {@see PluginInterface}, it must describe the kinds of work it can perform so the platform
 * and higher-level agents can route tasks to it without knowing its concrete type.
 */
interface WorkerPlugin extends PluginInterface
{
    /**
     * A machine-readable description of the work this worker can perform.
     *
     * The shape is a plain associative array (JSON-serialisable) enumerating the worker's supported
     * task types, inputs and outputs, used for capability matching and routing.
     *
     * @return array<string, mixed>
     */
    public function describe(): array;
}
