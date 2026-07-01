<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin;

use Nizam\Platform\Plugin\Contract\WorkerPlugin;
use Nizam\Platform\Plugin\PluginDependency;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\SemanticVersion;
use Nizam\Platform\Plugin\VersionConstraint;
use Nizam\Platform\Plugin\Infrastructure\Testing\ReferencePlugin;

/**
 * A small factory of valid plugin manifests for the unit tests.
 *
 * The Plugin Platform's validator requires an entry-point class that really implements the declared
 * kind's SDK contract; the {@see ReferencePlugin} is that genuine class. This factory builds manifests
 * around it — with a caller-chosen name, version and dependency list — so tests exercising discovery,
 * dependency resolution and the lifecycle share one canonical, always-valid manifest shape rather than
 * repeating the (long) manifest construction in every test.
 */
final class PluginTestFactory
{
    /**
     * Build a valid {@see PluginKind::Worker} manifest around the reference plugin's entry point.
     *
     * @param string                 $name         The manifest name.
     * @param string                 $version      The plugin version, as a SemVer string.
     * @param list<PluginDependency> $dependencies The plugin's declared dependencies.
     * @param string                 $platform     The platform constraint, as an expression.
     * @param class-string           $entryPoint   The entry-point class (defaults to the reference plugin).
     */
    public static function manifest(
        string $name = 'nizam.reference-worker',
        string $version = '1.0.0',
        array $dependencies = [],
        string $platform = '>=1.0.0',
        string $entryPoint = ReferencePlugin::class,
    ): PluginManifest {
        return PluginManifest::create(
            name: $name,
            displayName: 'Test Plugin ' . $name,
            version: SemanticVersion::parse($version),
            kind: PluginKind::Worker,
            description: 'A manifest built for the plugin test suite.',
            author: 'Nizam Platform',
            license: 'MIT',
            entryPointClass: $entryPoint,
            platformConstraint: VersionConstraint::parse($platform),
            dependencies: $dependencies,
        );
    }

    /**
     * A required dependency on another plugin at the given constraint.
     */
    public static function requires(string $pluginName, string $constraint = '*'): PluginDependency
    {
        return new PluginDependency($pluginName, VersionConstraint::parse($constraint), false);
    }

    /**
     * An optional dependency on another plugin at the given constraint.
     */
    public static function optionally(string $pluginName, string $constraint = '*'): PluginDependency
    {
        return new PluginDependency($pluginName, VersionConstraint::parse($constraint), true);
    }

    /**
     * The entry-point class every factory-built manifest points at.
     *
     * @return class-string<WorkerPlugin>
     */
    public static function entryPoint(): string
    {
        return ReferencePlugin::class;
    }
}
