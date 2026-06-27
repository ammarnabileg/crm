<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Plugins\PersonalityAssessment\PersonalityAssessmentPlugin;
use App\Services\Plugin\AbstractPlugin;
use App\Services\Plugin\PluginApi;
use App\Services\Plugin\PluginManifest;
use App\Services\Plugin\PluginRegistry;
use RuntimeException;
use Tests\TestCase;

/**
 * Plugin SDK (docs/51) — registry + lifecycle (install/enable/disable/uninstall),
 * dependency validation, platform-version compatibility, and the PluginApi sandbox
 * capability gateway. Plugins extend the platform only through that API.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        $workspaceId = (int) app('db')->table('workspaces')->orderBy('id')->value('id');
        tenant()->setById($workspaceId);
    }

    private function registry(): PluginRegistry
    {
        return new PluginRegistry();
    }

    public function test_manifest_from_array(): void
    {
        $m = PluginManifest::fromArray('demo', [
            'name' => 'Demo', 'version' => '2.1.0', 'dependencies' => ['a', 'b'],
            'min_platform_version' => '0.5.0',
        ]);
        $this->assertSame('Demo', $m->name);
        $this->assertSame('2.1.0', $m->version);
        $this->assertSame(['a', 'b'], $m->dependencies);
        $this->assertSame('0.5.0', $m->minPlatformVersion);
    }

    public function test_install_enable_disable_lifecycle(): void
    {
        $reg = $this->registry()->register(new PersonalityAssessmentPlugin());

        $reg->install('personality_assessment');
        $this->assertSame('installed', $reg->statusOf('personality_assessment'));
        $this->assertTrue($reg->isInstalled('personality_assessment'));

        $reg->enable('personality_assessment');
        $this->assertTrue($reg->isEnabled('personality_assessment'));

        $reg->disable('personality_assessment');
        $this->assertSame('disabled', $reg->statusOf('personality_assessment'));
        $this->assertFalse($reg->isEnabled('personality_assessment'));
    }

    public function test_capabilities_collected_from_enabled_plugins(): void
    {
        $reg = $this->registry()->register(new PersonalityAssessmentPlugin());
        $reg->install('personality_assessment');

        // Before enable: nothing aggregated.
        $this->assertSame(0, count($reg->capabilities()->permissions()));

        $reg->enable('personality_assessment');
        $api = $reg->capabilities();
        $this->assertInstanceOf(PluginApi::class, $api);

        $permKeys = array_map(static fn ($p) => $p['key'], $api->permissions());
        $this->assertTrue(in_array('plugin.personality.manage', $permKeys, true));
        $typeKeys = array_map(static fn ($t) => $t['key'], $api->interviewTypes());
        $this->assertTrue(in_array('personality_assessment', $typeKeys, true));
        $this->assertSame(1, count($api->automationActions()));
        $this->assertSame('personality_score', $api->automationActions()[0]->key());
    }

    public function test_incompatible_platform_version_is_rejected(): void
    {
        $reg = $this->registry()->register($this->futurePlugin());
        $threw = false;
        try {
            $reg->install('future_plugin');
        } catch (RuntimeException) {
            $threw = true;
        }
        $this->assertTrue($threw);
    }

    public function test_missing_dependency_is_rejected(): void
    {
        $reg = $this->registry()->register($this->dependentPlugin('needs_missing', ['no_such_plugin']));
        $threw = false;
        try {
            $reg->install('needs_missing');
        } catch (RuntimeException) {
            $threw = true;
        }
        $this->assertTrue($threw);
    }

    public function test_dependency_must_be_enabled_before_dependent(): void
    {
        $reg = $this->registry()
            ->register($this->dependentPlugin('dep_a', []))
            ->register($this->dependentPlugin('dep_b', ['dep_a']));

        $reg->install('dep_a');
        $reg->install('dep_b'); // dep_a installed → ok

        $threw = false;
        try {
            $reg->enable('dep_b'); // dep_a not enabled yet
        } catch (RuntimeException) {
            $threw = true;
        }
        $this->assertTrue($threw);

        $reg->enable('dep_a');
        $reg->enable('dep_b'); // now ok
        $this->assertTrue($reg->isEnabled('dep_b'));
    }

    public function test_uninstall_blocked_by_dependent(): void
    {
        $reg = $this->registry()
            ->register($this->dependentPlugin('base', []))
            ->register($this->dependentPlugin('addon', ['base']));
        $reg->install('base');
        $reg->install('addon');

        $threw = false;
        try {
            $reg->uninstall('base'); // addon depends on base
        } catch (RuntimeException) {
            $threw = true;
        }
        $this->assertTrue($threw);
    }

    // --- inline test plugins -----------------------------------------------

    private function futurePlugin(): AbstractPlugin
    {
        return new class extends AbstractPlugin {
            public function key(): string
            {
                return 'future_plugin';
            }

            public function manifest(): array
            {
                return ['name' => 'Future', 'version' => '1.0.0', 'min_platform_version' => '99.0.0'];
            }

            public function register(PluginApi $api): void
            {
            }
        };
    }

    /** @param string[] $deps */
    private function dependentPlugin(string $key, array $deps): AbstractPlugin
    {
        return new class($key, $deps) extends AbstractPlugin {
            /** @param string[] $deps */
            public function __construct(private string $k, private array $deps)
            {
            }

            public function key(): string
            {
                return $this->k;
            }

            public function manifest(): array
            {
                return ['name' => $this->k, 'version' => '1.0.0', 'dependencies' => $this->deps, 'min_platform_version' => '0.1.0'];
            }

            public function register(PluginApi $api): void
            {
            }
        };
    }
};
