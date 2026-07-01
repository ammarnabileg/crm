<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin\Fixture;

use Nizam\Platform\Plugin\Exception\PluginValidationException;
use Nizam\Platform\Plugin\Port\PluginInstantiator;
use Nizam\Platform\Plugin\PluginInterface;
use Nizam\Platform\Plugin\PluginManifest;

/**
 * A {@see PluginInstantiator} test double mapping manifest names to pre-built plugin instances.
 *
 * The lifecycle manager instantiates a plugin to run its hooks; in unit tests we hand it fixed
 * instances (a hooked worker, a throwing one) keyed by name, so the tests control exactly what code
 * the sandbox runs without wiring the container. A name with no registered instance yields a
 * {@see PluginValidationException}, mirroring the real instantiator's fault behaviour so the manager's
 * construction-fault path can be exercised too.
 */
final class MapPluginInstantiator implements PluginInstantiator
{
    /**
     * @param array<string, PluginInterface> $instances Plugin instances keyed by manifest name.
     */
    public function __construct(private array $instances = [])
    {
    }

    /**
     * Register (or replace) the instance returned for a manifest name.
     */
    public function register(string $name, PluginInterface $instance): void
    {
        $this->instances[$name] = $instance;
    }

    /**
     * Construct the plugin described by the manifest, from the registered instances.
     *
     * @throws PluginValidationException When no instance is registered for the manifest name.
     */
    public function make(PluginManifest $manifest): PluginInterface
    {
        $instance = $this->instances[$manifest->name()] ?? null;
        if ($instance === null) {
            throw PluginValidationException::forReason(
                sprintf('No test instance registered for plugin "%s".', $manifest->name()),
            );
        }

        return $instance;
    }
}
