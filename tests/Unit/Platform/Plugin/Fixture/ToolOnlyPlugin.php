<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin\Fixture;

use Nizam\Platform\Plugin\Contract\ToolPlugin;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\SemanticVersion;
use Nizam\Platform\Plugin\VersionConstraint;

/**
 * A test fixture plugin that implements only the {@see ToolPlugin} contract.
 *
 * Used by the validator tests to prove the kind-versus-entry-point reflection check: a manifest that
 * declares itself a Worker but points at this Tool-only class must be rejected, because the entry
 * point does not implement the Worker kind's contract.
 */
final class ToolOnlyPlugin implements ToolPlugin
{
    /**
     * The plugin's published descriptor.
     */
    public function manifest(): PluginManifest
    {
        return PluginManifest::create(
            name: 'test.tool-only',
            displayName: 'Tool Only',
            version: SemanticVersion::of(1, 0, 0),
            kind: PluginKind::Tool,
            description: 'A tool-only fixture plugin.',
            author: 'Test',
            license: 'MIT',
            entryPointClass: self::class,
            platformConstraint: VersionConstraint::parse('*'),
        );
    }

    /**
     * The stable, callable name of the tool.
     */
    public function toolName(): string
    {
        return 'noop';
    }

    /**
     * The declarative input schema describing the tool's parameters.
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array
    {
        return [];
    }
}
