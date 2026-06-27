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
        // Use keys that are NOT set as real env vars, so we test file parsing in
        // isolation (real env vars intentionally take precedence over the file).
        $file = sys_get_temp_dir() . '/env_' . bin2hex(random_bytes(4));
        file_put_contents($file, "ZTEST_NAME=\"HaHireAI\"\nZTEST_DEBUG=true\nZTEST_EMPTY=\nZTEST_NOPE=false\n# comment\n");

        $env = (new Environment())->load($file);

        $this->assertSame('HaHireAI', $env->get('ZTEST_NAME'));
        $this->assertTrue($env->get('ZTEST_DEBUG'));
        $this->assertFalse($env->get('ZTEST_NOPE'));
        $this->assertSame('', $env->get('ZTEST_EMPTY'));
        $this->assertSame('fallback', $env->get('ZTEST_UNSET', 'fallback'));

        unlink($file);
    }

    public function test_real_env_takes_precedence_over_file(): void
    {
        putenv('ZTEST_PRECEDENCE=from_real_env');
        $file = sys_get_temp_dir() . '/env_' . bin2hex(random_bytes(4));
        file_put_contents($file, "ZTEST_PRECEDENCE=from_file\n");

        $env = (new Environment())->load($file);

        $this->assertSame('from_real_env', $env->get('ZTEST_PRECEDENCE'));

        putenv('ZTEST_PRECEDENCE');
        unlink($file);
    }

    public function test_environment_validate_reports_missing(): void
    {
        $env = (new Environment())->load('/nonexistent/.env');

        $missing = $env->validate(['DEFINITELY_MISSING_KEY_XYZ']);

        $this->assertContains('DEFINITELY_MISSING_KEY_XYZ', $missing);
    }
}
