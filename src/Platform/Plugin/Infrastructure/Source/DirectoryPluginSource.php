<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Infrastructure\Source;

use Nizam\Platform\Exception\PlatformException;
use Nizam\Platform\Plugin\Exception\PluginManifestException;
use Nizam\Platform\Plugin\Port\DiscoveredPlugin;
use Nizam\Platform\Plugin\Port\PluginSource;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Support\Json;

/**
 * A {@see PluginSource} that discovers plugins by scanning a base directory for manifest files.
 *
 * Each installable plugin lives in its own sub-directory of the base directory and publishes a
 * `plugin.json` file — the on-disk form of a {@see PluginManifest}. This source scans the immediate
 * children of the base directory (one level deep, the conventional layout) for that file, decodes and
 * validates each into a manifest, and yields a {@see DiscoveredPlugin} whose locator is the absolute
 * path of the containing directory. The scan is read-only: it never loads, requires or executes plugin
 * code — only its declarative manifest — keeping discovery safe. A base directory that does not exist
 * yields no plugins; a malformed or invalid `plugin.json` is surfaced as a manifest exception so the
 * operator learns which plugin is broken rather than silently skipping it.
 */
final class DirectoryPluginSource implements PluginSource
{
    /**
     * The conventional manifest file name a plugin directory must contain.
     */
    private const string MANIFEST_FILE = 'plugin.json';

    /**
     * @param string $baseDirectory The directory whose immediate sub-directories are scanned.
     */
    public function __construct(private readonly string $baseDirectory)
    {
    }

    /**
     * Discover the plugins whose directories contain a valid `plugin.json`, ordered by name.
     *
     * @return list<DiscoveredPlugin>
     *
     * @throws PluginManifestException When a discovered `plugin.json` is malformed or invalid.
     * @throws PlatformException       When a manifest file cannot be read or decoded.
     */
    public function discover(): array
    {
        if (!is_dir($this->baseDirectory)) {
            return [];
        }

        $discovered = [];
        foreach ($this->manifestFiles() as $directory => $manifestPath) {
            $discovered[] = new DiscoveredPlugin(
                $this->readManifest($manifestPath),
                $directory,
            );
        }

        usort(
            $discovered,
            static fn (DiscoveredPlugin $a, DiscoveredPlugin $b): int => $a->name() <=> $b->name(),
        );

        return $discovered;
    }

    /**
     * The manifest file path found under each immediate sub-directory, keyed by that directory.
     *
     * @return array<string, string> Directory path => manifest file path.
     */
    private function manifestFiles(): array
    {
        $entries = scandir($this->baseDirectory);
        if ($entries === false) {
            return [];
        }

        $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $directory = $this->baseDirectory . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($directory)) {
                continue;
            }
            $manifestPath = $directory . DIRECTORY_SEPARATOR . self::MANIFEST_FILE;
            if (is_file($manifestPath)) {
                $files[$directory] = $manifestPath;
            }
        }

        return $files;
    }

    /**
     * Read, decode and validate a single `plugin.json` into a manifest.
     *
     * @throws PluginManifestException When the manifest content is invalid.
     * @throws PlatformException       When the file cannot be read or is not valid JSON.
     */
    private function readManifest(string $manifestPath): PluginManifest
    {
        $contents = @file_get_contents($manifestPath);
        if ($contents === false) {
            throw new PlatformException(sprintf('Unable to read plugin manifest at "%s".', $manifestPath));
        }

        return PluginManifest::fromArray(Json::decode($contents));
    }
}
