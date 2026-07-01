<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Testing;

use Nizam\Platform\Plugin\Contract\WorkerPlugin;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\PluginPermission;
use Nizam\Platform\Plugin\SemanticVersion;
use Nizam\Platform\Plugin\VersionConstraint;

/**
 * A real, minimal {@see WorkerPlugin} the Runtime's Infrastructure and integration tests wire against.
 *
 * This is a genuine plugin — it publishes a valid {@see PluginManifest} declaring itself a
 * {@see PluginKind::Worker} and fulfils the contract by describing the capability it performs — not a
 * stub of the platform. Integration tests dispatch it through the real
 * {@see \Nizam\Runtime\Orchestration\WorkerCoordinator} to prove that workers are only ever invoked
 * through the Runtime and never reach one another. Its constructor is side-effect free, honouring the SDK
 * guidance; the declared required permission lets tests exercise the coordinator's permission gate. It
 * lives in Infrastructure/Testing because it is used only by tests and as a safe default.
 */
final class FakeWorkerPlugin implements WorkerPlugin
{
    /**
     * The permission key every fake worker requires, matching the grant tests seed.
     */
    public const string REQUIRED_PERMISSION = 'worker.execute';

    /**
     * @param string $name       The stable manifest name this worker publishes.
     * @param string $capability The single capability this worker describes and performs.
     */
    public function __construct(
        private readonly string $name = 'runtime.fake-worker',
        private readonly string $capability = 'runtime.echo',
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function manifest(): PluginManifest
    {
        return PluginManifest::create(
            name: $this->name,
            displayName: 'Runtime Fake Worker',
            version: SemanticVersion::of(1, 0, 0),
            kind: PluginKind::Worker,
            description: 'A minimal worker plugin used by the Runtime integration tests and safe defaults.',
            author: 'Nizam Runtime',
            license: 'MIT',
            entryPointClass: self::class,
            platformConstraint: VersionConstraint::parse('>=1.0.0'),
            requiredPermissions: [
                new PluginPermission(self::REQUIRED_PERMISSION, 'Perform assigned units of work.'),
            ],
            requiredCapabilities: [],
            dependencies: [],
            configSchema: [],
            healthCheckClass: null,
            tags: ['runtime', 'worker', 'test'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function describe(): array
    {
        return [
            'capability' => $this->capability,
            'taskTypes' => [$this->capability],
            'inputs' => ['payload' => 'array'],
            'outputs' => ['taskResult' => 'array'],
        ];
    }

    /**
     * The single capability this worker performs.
     */
    public function capability(): string
    {
        return $this->capability;
    }
}
