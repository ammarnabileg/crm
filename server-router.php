<?php

declare(strict_types=1);

/*
 * Router for PHP's built-in web server (development / automated testing only).
 *
 * Usage:  php -S 127.0.0.1:8000 server-router.php
 *
 * Static files that exist under /public are served as-is; every other request
 * is handed to the front controller, mirroring the production .htaccess rules.
 * This file is NOT used in production.
 */

$publicDir = __DIR__ . '/public';
$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

$requested = $publicDir . $uri;
if ($uri !== '/' && is_file($requested) && ! is_dir($requested)) {
    return false; // let the built-in server serve the static asset
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicDir . '/index.php';

require $publicDir . '/index.php';
