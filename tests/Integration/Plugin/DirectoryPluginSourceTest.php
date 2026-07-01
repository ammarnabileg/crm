<?php

declare(strict_types=1);

namespace Nizam\Tests\Integration\Plugin;

use Nizam\Platform\Plugin\Exception\PluginManifestException;
use Nizam\Platform\Plugin\Infrastructure\Source\DirectoryPluginSource;
use Nizam\Platform\Plugin\Infrastructure\Testing\ReferencePlugin;
use Nizam\Platform\Plugin\Port\DiscoveredPlugin;
use Nizam\Platform\Support\Json;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for {@see DirectoryPluginSource} scanning real `plugin.json` files on disk.
 *
 * Each test writes plugin directories into a fresh temporary base directory, each containing a
 * `plugin.json` (the on-disk manifest form), then exercises discovery: that valid manifests are found
 * and turned into {@see DiscoveredPlugin}s with the containing directory as their locator, that
 * directories without a manifest are ignored, that a missing base directory yields nothing, and that a
 * malformed manifest surfaces as a manifest exception rather than being silently skipped.
 */
#[CoversClass(DirectoryPluginSource::class)]
#[CoversClass(DiscoveredPlugin::class)]
final class DirectoryPluginSourceTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nizam-plugin-src-' . bin2hex(random_bytes(6));
        if (!mkdir($this->baseDir, 0o777, true) && !is_dir($this->baseDir)) {
            self::fail('Could not create temp base directory.');
        }
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->baseDir);
    }

    /**
     * Write a plugin directory containing a `plugin.json` built from the given manifest array.
     *
     * @param array<string, mixed> $manifest
     */
    private function writePlugin(string $dirName, array $manifest): void
    {
        $dir = $this->baseDir . DIRECTORY_SEPARATOR . $dirName;
        mkdir($dir, 0o777, true);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'plugin.json', Json::encode($manifest, true));
    }

    /**
     * Recursively delete a directory tree.
     */
    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $entries = scandir($path) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($full) ? $this->removeTree($full) : @unlink($full);
        }
        @rmdir($path);
    }

    #[Test]
    public function itDiscoversAValidPluginJson(): void
    {
        $this->writePlugin('reference', ReferencePlugin::manifestDescriptor()->toArray());

        $discovered = (new DirectoryPluginSource($this->baseDir))->discover();

        self::assertCount(1, $discovered);
        self::assertSame(ReferencePlugin::NAME, $discovered[0]->name());
        self::assertSame(
            $this->baseDir . DIRECTORY_SEPARATOR . 'reference',
            $discovered[0]->locator(),
        );
        self::assertSame('1.0.0', (string) $discovered[0]->manifest()->version());
    }

    #[Test]
    public function itDiscoversMultiplePluginsOrderedByName(): void
    {
        $this->writePlugin('c-dir', $this->minimal('pkg.c'));
        $this->writePlugin('a-dir', $this->minimal('pkg.a'));
        $this->writePlugin('b-dir', $this->minimal('pkg.b'));

        $discovered = (new DirectoryPluginSource($this->baseDir))->discover();

        $names = array_map(static fn (DiscoveredPlugin $d): string => $d->name(), $discovered);
        self::assertSame(['pkg.a', 'pkg.b', 'pkg.c'], $names);
    }

    #[Test]
    public function itIgnoresDirectoriesWithoutAManifest(): void
    {
        mkdir($this->baseDir . DIRECTORY_SEPARATOR . 'empty', 0o777, true);
        $this->writePlugin('real', $this->minimal('pkg.real'));

        $discovered = (new DirectoryPluginSource($this->baseDir))->discover();

        self::assertCount(1, $discovered);
        self::assertSame('pkg.real', $discovered[0]->name());
    }

    #[Test]
    public function aMissingBaseDirectoryYieldsNothing(): void
    {
        $source = new DirectoryPluginSource($this->baseDir . DIRECTORY_SEPARATOR . 'does-not-exist');

        self::assertSame([], $source->discover());
    }

    #[Test]
    public function anEmptyBaseDirectoryYieldsNothing(): void
    {
        self::assertSame([], (new DirectoryPluginSource($this->baseDir))->discover());
    }

    #[Test]
    public function aMalformedManifestSurfacesAsAnException(): void
    {
        $broken = $this->minimal('pkg.broken');
        unset($broken['version']);
        $this->writePlugin('broken', $broken);

        $this->expectException(PluginManifestException::class);

        (new DirectoryPluginSource($this->baseDir))->discover();
    }

    #[Test]
    public function theReadManifestRebuildsTheFullDescriptor(): void
    {
        $this->writePlugin('reference', ReferencePlugin::manifestDescriptor()->toArray());

        $discovered = (new DirectoryPluginSource($this->baseDir))->discover();

        self::assertEquals(
            ReferencePlugin::manifestDescriptor()->toArray(),
            $discovered[0]->manifest()->toArray(),
        );
    }

    /**
     * A minimal valid manifest array for a worker plugin with the given name.
     *
     * @return array<string, mixed>
     */
    private function minimal(string $name): array
    {
        return [
            'name' => $name,
            'displayName' => 'Plugin ' . $name,
            'version' => '1.0.0',
            'kind' => 'worker',
            'description' => 'A discovered plugin.',
            'author' => 'Nizam Platform',
            'license' => 'MIT',
            'entryPointClass' => ReferencePlugin::class,
            'platformConstraint' => '>=1.0.0',
        ];
    }
}
