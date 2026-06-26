<?php

declare(strict_types=1);

/**
 * Test bootstrap: load the application autoloader, boot the kernel (so the
 * container, config, db, cache, etc. are available), and load the test base
 * classes. Used by tests/run.php.
 */

require dirname(__DIR__) . '/bootstrap/autoload.php';
require __DIR__ . '/AssertionFailed.php';
require __DIR__ . '/TestCase.php';

/** @var \App\Core\Application $app */
$app = new \App\Core\Application(dirname(__DIR__));
$app->boot();

return $app;
