<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Infrastructure\Testing;

use Nizam\Platform\Plugin\Contract\WorkerPlugin;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\PluginPermission;
use Nizam\Platform\Plugin\SemanticVersion;
use Nizam\Platform\Plugin\VersionConstraint;

/**
 * A single, real, minimal {@see WorkerPlugin} used exclusively by the test suite.
 *
 * This is a genuine plugin, not a stub of the platform: it publishes a valid {@see PluginManifest}
 * declaring itself a {@see PluginKind::Worker}, and it fulfils the worker contract by describing the
 * echo work it can perform. The tests use it to exercise discovery, validation (its entry point really
 * does implement the declared kind's contract), the container instantiator and the full install →
 * enable → disable → uninstall lifecycle against a concrete plugin. It performs no I/O in its
 * constructor, honouring the SDK guidance that construction be side-effect free.
 */
final class ReferencePlugin implements WorkerPlugin
{
    /**
     * The stable manifest name this reference plugin publishes.
     */
    public const string NAME = 'nizam.reference-worker';

    /**
     * Build the reference plugin's manifest.
     *
     * Exposed as a static factory so tests can obtain the manifest (for array sources, `plugin.json`
     * fixtures and validation) without instantiating the plugin.
     */
    public static function manifestDescriptor(): PluginManifest
    {
        return PluginManifest::create(
            name: self::NAME,
            displayName: 'Nizam Reference Worker',
            version: SemanticVersion::of(1, 0, 0),
            kind: PluginKind::Worker,
            description: 'A minimal reference worker plugin used by the platform test suite.',
            author: 'Nizam Platform',
            license: 'MIT',
            entryPointClass: self::class,
            platformConstraint: VersionConstraint::parse('>=1.0.0'),
            requiredPermissions: [
                new PluginPermission('worker.execute', 'Perform assigned units of work.'),
            ],
            requiredCapabilities: [],
            dependencies: [],
            configSchema: [
                'prefix' => ['type' => 'string', 'default' => ''],
            ],
            healthCheckClass: null,
            tags: ['reference', 'worker', 'test'],
        );
    }

    /**
     * The plugin's published descriptor.
     */
    public function manifest(): PluginManifest
    {
        return self::manifestDescriptor();
    }

    /**
     * A machine-readable description of the work this reference worker can perform.
     *
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        return [
            'taskTypes' => ['echo'],
            'inputs' => ['message' => 'string'],
            'outputs' => ['message' => 'string'],
        ];
    }
}
