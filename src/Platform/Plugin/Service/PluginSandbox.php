<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Service;

use Nizam\Kernel\Domain\Clock;
use Nizam\Platform\Plugin\Event\PluginFailed;
use Nizam\Platform\Plugin\Exception\PluginException;
use Nizam\Platform\Plugin\Port\PluginEventPublisher;
use Nizam\Platform\Plugin\RegisteredPlugin;
use Nizam\Platform\Support\Result;
use Throwable;

/**
 * Runs a plugin call inside a fault-catching boundary so a crash never propagates into the Core.
 *
 * The sandbox is the platform's software fault-isolation for plugin execution. It is *not* an OS-level
 * sandbox — it spawns no process and evaluates no code — but a disciplined boundary that invokes a
 * plugin callable, and if that callable throws *any* {@see Throwable}, converts the fault into a failure
 * {@see Result} (carrying the {@see PluginException::CODE_PREFIX} error code and the exception message)
 * and publishes a {@see PluginFailed} domain event so the failure is observable and auditable. A plugin
 * fault is therefore always contained: the caller receives a value it can branch on rather than an
 * exception it must catch, and the Core keeps running. A successful call is returned as a success
 * {@see Result} carrying whatever the callable produced.
 */
final class PluginSandbox
{
    /**
     * The error code carried by a failure {@see Result} produced from a caught plugin fault.
     */
    public const string CODE = 'PLUGIN.EXECUTION_FAILED';

    /**
     * @param PluginEventPublisher $publisher The egress for the {@see PluginFailed} event on a fault.
     * @param Clock                $clock     The time source stamping the failure event.
     */
    public function __construct(
        private readonly PluginEventPublisher $publisher,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Execute a plugin callable, containing any thrown fault as a failure {@see Result}.
     *
     * @param RegisteredPlugin      $plugin    The plugin whose call is being run (identifies the fault).
     * @param callable(): mixed     $operation The plugin operation to execute.
     *
     * @return Result A success carrying the operation's return value, or a failure describing the fault.
     */
    public function run(RegisteredPlugin $plugin, callable $operation): Result
    {
        try {
            return Result::ok($operation());
        } catch (Throwable $throwable) {
            $this->publishFailure($plugin, $throwable);

            return Result::err(
                self::CODE,
                sprintf('Plugin "%s" failed during execution: %s', $plugin->name(), $throwable->getMessage()),
                [
                    'plugin' => $plugin->name(),
                    'exception' => $throwable::class,
                ],
            );
        }
    }

    /**
     * Publish a {@see PluginFailed} event describing the caught fault.
     */
    private function publishFailure(RegisteredPlugin $plugin, Throwable $throwable): void
    {
        $reason = sprintf('%s: %s', $throwable::class, $throwable->getMessage());

        $this->publisher->publish([
            new PluginFailed(
                $plugin->pluginId(),
                $plugin->name(),
                $reason,
                $plugin->tenantId(),
                $this->clock->now(),
            ),
        ]);
    }
}
