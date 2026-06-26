<?php

declare(strict_types=1);

/**
 * Zero-dependency PSR-4 autoloader.
 *
 * The platform intentionally ships without any runtime Composer dependency so
 * it can be deployed by uploading files only (no `composer install` on the
 * server). We therefore register our own class loader: a namespace prefix is
 * mapped to a base directory and "App\Core\Router" resolves to
 * app/Core/Router.php.
 */

$basePath = dirname(__DIR__);

$prefixes = [
    'App\\'      => $basePath . '/app',
    'Database\\' => $basePath . '/database',
];

spl_autoload_register(static function (string $class) use ($prefixes): void {
    foreach ($prefixes as $prefix => $baseDir) {
        $length = strlen($prefix);
        if (strncmp($class, $prefix, $length) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $length);
        $file = $baseDir . '/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require $basePath . '/app/Support/helpers.php';
