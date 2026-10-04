<?php

declare(strict_types=1);

/*
 * Autoloader bootstrap.
 *
 * HaHireAI has ZERO runtime Composer dependencies: composer.json `require` lists
 * only `php` and `ext-*`. Composer is used purely for dev tooling (PHPUnit) and to
 * generate the optimized classmap. That means the application boots with no
 * `vendor/` directory at all — a genuine upload-and-run, native-PHP deployment
 * (docs/INSTALLATION.md §2; PROJECT_CONSTITUTION.md §5).
 *
 * Order of preference:
 *   1. If `vendor/autoload.php` exists (developer machines, CI), use it — it gives
 *      the optimized classmap plus the dev autoloader for the test suite.
 *   2. Otherwise register a lightweight PSR-4 autoloader that mirrors the mappings
 *      declared in composer.json, so production hosts without Composer still boot.
 *
 * Keep the PSR-4 prefixes here in sync with composer.json "autoload"/"autoload-dev".
 */

$hahireaiRoot = dirname(__DIR__);

// `HAHIREAI_NO_VENDOR=1` forces the vendor-free path even when vendor/ exists, so
// CI can verify the production (Composer-less) boot without deleting vendor/.
$hahireaiForceVendorless = getenv('HAHIREAI_NO_VENDOR') === '1';

$hahireaiComposer = $hahireaiRoot . '/vendor/autoload.php';
if (! $hahireaiForceVendorless && is_file($hahireaiComposer)) {
    require $hahireaiComposer;

    return;
}

// --- Vendor-free fallback (mirrors composer.json "autoload") -----------------

/**
 * PSR-4 prefixes => base directories. Most specific prefix first so the test
 * namespace wins over the catch-all application namespace.
 *
 * @var array<string,string> $hahireaiPsr4
 */
$hahireaiPsr4 = [
    'HaHireAI\\Tests\\' => $hahireaiRoot . '/tests/',
    'HaHireAI\\' => $hahireaiRoot . '/app/',
];

spl_autoload_register(static function (string $class) use ($hahireaiPsr4): void {
    foreach ($hahireaiPsr4 as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) {
            continue;
        }

        // Most-specific prefix matched: resolve within its base dir and stop —
        // never fall through to a broader prefix (which would look in the wrong tree).
        $relative = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }

        return;
    }
});

// composer.json "files" autoload — global helper functions.
$hahireaiHelpers = $hahireaiRoot . '/app/Support/helpers.php';
if (is_file($hahireaiHelpers)) {
    require $hahireaiHelpers;
}
