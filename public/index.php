<?php

declare(strict_types=1);

use App\Core\Request;

/*
 * HalaOps front controller — the single entry point for every web request.
 *
 * Deployment models supported out of the box:
 *   1. Document root = this /public directory (recommended).
 *   2. Document root = project root; the root .htaccess forwards here.
 */

define('HALAOPS_START', microtime(true));

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$request = Request::capture();
$response = $app->handle($request);
$response->send();
