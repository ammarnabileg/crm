<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Testing;

use Nizam\Platform\Plugin\Contract\ManagerPlugin;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\SemanticVersion;
use Nizam\Platform\Plugin\VersionConstraint;

/**
 * A real, minimal {@see ManagerPlugin} the Runtime's own tests and safe defaults use.
 *
 * This is a genuine plugin — it publishes a valid {@see PluginManifest} declaring itself a
 * {@see PluginKind::Manager} and fulfils the contract by naming the roles it manages — not a stub of the
 * platform. It carries no planning or decision logic itself: those runtime behaviours are supplied by
 * the {@see \Nizam\Runtime\Orchestration\Port\ManagerAgent} the orchestrator drives it through, keeping
 * the SDK contract minimal exactly as the platform intends. Its constructor is side-effect free.
 */
final class FakeManagerPlugin implements ManagerPlugin
{
    /**
     * @param string       $name         The stable manifest name this manager publishes.
     * @param list<string> $managedRoles The roles this manager governs.
     */
    public function __construct(
        private readonly string $name = 'runtime.fake-manager',
        private readonly array $managedRoles = ['runtime.worker'],
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function manifest(): PluginManifest
    {
        return PluginManifest::create(
            name: $this->name,
            displayName: 'Runtime Fake Manager',
            version: SemanticVersion::of(1, 0, 0),
            kind: PluginKind::Manager,
            description: 'A minimal manager plugin used by the Runtime test suite and safe defaults.',
            author: 'Nizam Runtime',
            license: 'MIT',
            entryPointClass: self::class,
            platformConstraint: VersionConstraint::parse('>=1.0.0'),
            requiredPermissions: [],
            requiredCapabilities: [],
            dependencies: [],
            configSchema: [],
            healthCheckClass: null,
            tags: ['runtime', 'manager', 'test'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function managedRoles(): array
    {
        return $this->managedRoles;
    }
}
