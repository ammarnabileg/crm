<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * HaHireAI must run with NO Composer `vendor/` directory: composer.json declares
 * zero runtime dependencies, and bootstrap/autoload.php ships a PSR-4 fallback so
 * the product is a true upload-and-run deployment (docs/INSTALLATION.md §2). This
 * is exactly what unblocks shared hosts (Plesk/cPanel) where Composer is absent.
 *
 * The check runs in a subprocess with HAHIREAI_NO_VENDOR=1, which forces the
 * fallback path even though vendor/ exists here — so we never have to move or
 * delete the directory the test runner itself depends on.
 */
final class VendorlessBootTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/hahireai_vendorless_' . bin2hex(random_bytes(4));
        @mkdir($this->tmp, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmp);
    }

    public function test_application_autoloads_without_composer_vendor(): void
    {
        $root = dirname(__DIR__, 2);
        $harness = $this->tmp . '/probe.php';

        // The probe registers ONLY the fallback autoloader, then proves that:
        //  - application classes (app/) resolve,
        //  - the "files" autoload (helpers) loaded,
        //  - Composer's ClassLoader was never required (true vendor-free path).
        file_put_contents($harness, <<<PHP
            <?php
            putenv('HAHIREAI_NO_VENDOR=1');
            require '{$root}/bootstrap/autoload.php';
            \$classes = class_exists(\\HaHireAI\\Core\\Kernel::class)
                && class_exists(\\HaHireAI\\Core\\Container\\Container::class)
                && class_exists(\\HaHireAI\\Modules\\Installer\\Application\\Installer::class);
            \$helpers = function_exists('base_path') && function_exists('config');
            \$composerLoaded = class_exists(\\Composer\\Autoload\\ClassLoader::class, false);
            echo (\$classes && \$helpers && ! \$composerLoaded) ? 'VENDORLESS_OK' : 'VENDORLESS_FAIL';
            PHP);

        $output = [];
        $code = 0;
        exec('php ' . escapeshellarg($harness) . ' 2>&1', $output, $code);

        $this->assertSame(0, $code, 'probe exited non-zero: ' . implode("\n", $output));
        $this->assertSame('VENDORLESS_OK', trim(implode("\n", $output)), implode("\n", $output));
    }

    public function test_composer_has_no_runtime_dependencies(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);
        $require = array_keys($composer['require'] ?? []);

        // Only the PHP version and ext-* are allowed in `require`. Any real library
        // here would break the vendor-free deployment promise.
        $nonPlatform = array_filter($require, static fn (string $p): bool => $p !== 'php' && ! str_starts_with($p, 'ext-'));

        $this->assertSame([], array_values($nonPlatform), 'runtime require must contain only php + ext-*');
    }
}
