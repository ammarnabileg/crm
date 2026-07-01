<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Infrastructure\Container;

use Nizam\Platform\Container\Container;
use Nizam\Platform\Plugin\Exception\PluginValidationException;
use Nizam\Platform\Plugin\Port\PluginInstantiator;
use Nizam\Platform\Plugin\PluginInterface;
use Nizam\Platform\Plugin\PluginManifest;
use Throwable;

/**
 * A {@see PluginInstantiator} that resolves a plugin's entry-point class through the platform container.
 *
 * The platform never news a plugin's entry-point class directly. This adapter takes the manifest's
 * `entryPointClass`, resolves it via the {@see Container} (autowiring its constructor dependencies),
 * and returns the constructed instance — isolating any construction fault behind the port so a broken
 * plugin cannot crash the Core. Two invariants are enforced defensively: the resolved object must be a
 * {@see PluginInterface}, and any {@see Throwable} thrown during construction is converted into a
 * {@see PluginValidationException} naming the plugin, rather than propagating a raw container or plugin
 * error. Construction is expected to be side-effect free; plugins that need I/O do it in lifecycle
 * hooks, not their constructor.
 */
final class ContainerPluginInstantiator implements PluginInstantiator
{
    /**
     * @param Container $container The platform container used to resolve entry-point classes.
     */
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Construct the plugin described by the manifest via the container.
     *
     * @param PluginManifest $manifest The manifest whose `entryPointClass` is to be instantiated.
     *
     * @return PluginInterface The constructed plugin instance.
     *
     * @throws PluginValidationException When the class cannot be constructed or is not a plugin.
     */
    public function make(PluginManifest $manifest): PluginInterface
    {
        $entryPoint = $manifest->entryPointClass();

        try {
            $instance = $this->container->make($entryPoint);
        } catch (Throwable $e) {
            throw PluginValidationException::forReason(sprintf(
                'Plugin "%s" could not be instantiated from entry point "%s": %s',
                $manifest->name(),
                $entryPoint,
                $e->getMessage(),
            ));
        }

        if (!$instance instanceof PluginInterface) {
            throw PluginValidationException::forReason(sprintf(
                'Plugin "%s" entry point "%s" did not resolve to a %s.',
                $manifest->name(),
                $entryPoint,
                PluginInterface::class,
            ));
        }

        return $instance;
    }
}
