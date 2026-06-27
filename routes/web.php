<?php

declare(strict_types=1);

/*
 * Global web routes. Modules register their own routes via their Routes/ dir;
 * this file holds only platform-level entry points. See docs/ROUTING_GUIDE.md.
 *
 * @var \HaHireAI\Core\Routing\Router $router
 */

use HaHireAI\Core\Health\HealthChecker;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Kernel;

$router->get('/', static fn (): Response => Response::json([
    'name' => config('app.name'),
    'status' => 'ok',
    'phase' => 'core-kernel',
]));

// Liveness probe.
$router->get('/up', static fn (): Response => Response::text('OK'));

// Aggregated health report.
$router->get('/health', static function (): Response {
    /** @var HealthChecker $health */
    $health = Kernel::instance()->container()->make(HealthChecker::class);
    $report = $health->run();

    return Response::json([
        'status' => $report['status']->value,
        'probes' => $report['probes'],
    ], $report['status']->value === 'unhealthy' ? 503 : 200);
});
