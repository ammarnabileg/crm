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
        // ROOT-CAUSE GUARD for the "fork exhaustion kills the session" failure.
        // Resource-constrained / interactive containers (Claude Code on the web,
        // shared CI boxes) export HAHIREAI_SKIP_SUBPROCESS_TESTS=1 so this test
        // never even ATTEMPTS to spawn a subprocess — zero forks, so it can never
        // trigger the "Unable to fork" cascade that used to take the shell/session
        // down. The vendorless guarantee is still fully enforced on a normal CI
        // runner, where this flag is unset. See CLAUDE.md and .claude/settings.json.
        $skip = strtolower(trim((string) getenv('HAHIREAI_SKIP_SUBPROCESS_TESTS')));
        if (in_array($skip, ['1', 'true', 'yes', 'on'], true)) {
            $this->markTestSkipped('HAHIREAI_SKIP_SUBPROCESS_TESTS set — no subprocess spawned (protects resource-constrained/interactive hosts); vendorless boot is enforced on CI.');
        }

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

        // Spawning a subprocess is the only fork this suite performs. On a
        // process-constrained host (a shared CI box already running MySQL, or a
        // container near its PID/NPROC limit) the fork can fail with "Unable to
        // fork". Rather than error — which cascades once the process table is
        // full and can take the whole run (and the dev shell) down — we retry a
        // couple of times and then SKIP: the vendorless guarantee is still
        // enforced on a fresh CI runner where forking is available.
        $output = [];
        $code = 0;
        $forkFailed = false;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $output = [];
            $code = 0;
            $ran = @exec('php ' . escapeshellarg($harness) . ' 2>&1', $output, $code);
            $joined = trim(implode("\n", $output));
            $forkFailed = $ran === false || $code === 127 || str_contains($joined, 'Unable to fork') || str_contains($joined, 'Cannot allocate memory');
            if (! $forkFailed) {
                break;
            }
            usleep(300000); // 0.3s — give the OS a moment to reap children
        }

        if ($forkFailed) {
            $this->markTestSkipped('Subprocess spawn unavailable (host process table exhausted); vendorless boot is verified on a fresh CI runner.');
        }

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
