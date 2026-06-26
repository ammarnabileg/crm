<?php

declare(strict_types=1);

use App\Core\Application;

require __DIR__ . '/autoload.php';

/*
 * Build the application instance. The project root is one level above this
 * bootstrap directory. The returned instance is shared via the container.
 */
$app = new Application(dirname(__DIR__));

return $app;
