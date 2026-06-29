<?php

declare(strict_types=1);

/*
 * Application bootstrap: wire the autoloader, construct the container and the
 * Application Kernel, and return it. The front controller (public/index.php)
 * and the CLI/tests require this file. See docs/BOOTSTRAP_FLOW.md.
 */

use HaHireAI\Core\Container\Container;
use HaHireAI\Core\Kernel;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$container = new Container();

return new Kernel($container, dirname(__DIR__));
