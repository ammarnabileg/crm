<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Core;

use HaHireAI\Core\Config\Environment;
use HaHireAI\Core\Config\Repository;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function test_repository_dot_notation(): void
    {
        $config = new Repository(['app' => ['name' => 'HaHireAI', 'nested' => ['x' => 1]]]);

        $this->assertSame('HaHireAI', $config->get('app.name'));
        $this->assertSame(1, $config->get('app.nested.x'));
        $this->assertSame('def', $config->get('app.missing', 'def'));
    }

    public function test_repository_set_and_has(): void
    {
        $config = new Repository();
        $config->set('path.storage', '/tmp/storage');

        $this->assertTrue($config->has('path.storage'));
        $this->assertSame('/tmp/storage', $config->get('path.storage'));
        $this->assertFalse($config->has('path.nope'));
    }

    public function test_repository_loads_directory(): void
    {
        $dir = sys_get_temp_dir() . '/cfg_' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/svc.php', "<?php return ['enabled' => true];");

        $config = (new Repository())->loadDirectory($dir);

        $this->assertTrue($config->get('svc.enabled'));

        unlink($dir . '/svc.php');
        rmdir($dir);
    }

    public function test_environment_parses_and_casts(): void
    {
        $file = sys_get_temp_dir() . '/env_' . bin2hex(random_bytes(4));
        file_put_contents($file, "APP_NAME=\"HaHireAI\"\nAPP_DEBUG=true\nEMPTY=\nNOPE=false\n# comment\n");

        $env = (new Environment())->load($file);

        $this->assertSame('HaHireAI', $env->get('APP_NAME'));
        $this->assertTrue($env->get('APP_DEBUG'));
        $this->assertFalse($env->get('NOPE'));
        $this->assertSame('', $env->get('EMPTY'));
        $this->assertSame('fallback', $env->get('UNSET', 'fallback'));

        unlink($file);
    }

    public function test_environment_validate_reports_missing(): void
    {
        $env = (new Environment())->load('/nonexistent/.env');

        $missing = $env->validate(['DEFINITELY_MISSING_KEY_XYZ']);

        $this->assertContains('DEFINITELY_MISSING_KEY_XYZ', $missing);
    }
}
