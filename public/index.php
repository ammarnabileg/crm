<?php

declare(strict_types=1);

/*
 * HaHireAI front controller — the single web-exposed entry point.
 * See docs/BOOTSTRAP_FLOW.md, docs/PROJECT_STRUCTURE.md.
 */

/** @var \HaHireAI\Core\Kernel $kernel */
$kernel = require dirname(__DIR__) . '/bootstrap/app.php';

$kernel->run();
