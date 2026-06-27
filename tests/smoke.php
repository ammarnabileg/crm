<?php

declare(strict_types=1);

/*
 * Phase 7 acceptance smoke test (runs WITHOUT PHPUnit).
 *
 * Validates that the Core Kernel can boot, load env/config, register services,
 * route, run health probes, and handle requests — all WITHOUT any module.
 * Run: php tests/smoke.php   (exit code 0 = pass)
 */

use HaHireAI\Core\Config\Repository;
use HaHireAI\Core\Container\Container;
use HaHireAI\Core\Contracts\Container as ContainerContract;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Contracts\Logger;
use HaHireAI\Core\Health\HealthChecker;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Modules\ModuleRegistry;
use HaHireAI\Core\Routing\Dispatcher;
use HaHireAI\Core\Routing\Router;

$passed = 0;
$failed = 0;

function check(string $name, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  \033[32mPASS\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31mFAIL\033[0m {$name}\n";
    }
}

/** @var \HaHireAI\Core\Kernel $kernel */
$kernel = require dirname(__DIR__) . '/bootstrap/app.php';

echo "HaHireAI — Core Kernel smoke test\n\n";

// 1. Boot
$kernel->boot();
check('kernel boots', $kernel->isBooted());
check('boots with zero modules', $kernel->container()->make(ModuleRegistry::class)->count() === 0);

// 2. Configuration & environment
$config = $kernel->container()->make(Repository::class);
check('config loads app.name', $config->get('app.name') === 'HaHireAI');
check('config dot-notation default', $config->get('does.not.exist', 'fallback') === 'fallback');
check('config path.storage set', is_string($config->get('path.storage')));

// 3. Container resolves core services (singletons)
$c = $kernel->container();
check('resolves Container contract', $c->make(ContainerContract::class) instanceof Container);
check('resolves Router', $c->make(Router::class) instanceof Router);
check('resolves Dispatcher', $c->make(Dispatcher::class) instanceof Dispatcher);
check('resolves Logger contract', $c->make(Logger::class) instanceof Logger);
check('resolves EventDispatcher contract', $c->make(EventDispatcher::class) instanceof EventDispatcher);
check('Router is a singleton', $c->make(Router::class) === $c->make(Router::class));

// 4. Autowiring
class SmokeDependency
{
    public function value(): string
    {
        return 'wired';
    }
}
class SmokeConsumer
{
    public function __construct(public SmokeDependency $dep)
    {
    }
}
check('autowires constructor dependencies', $c->make(SmokeConsumer::class)->dep->value() === 'wired');

// 5. Container::call resolves parameters
$called = $c->call(static fn (SmokeDependency $dep): string => $dep->value());
check('container call() resolves args', $called === 'wired');

// 6. Events
$events = $c->make(EventDispatcher::class);
$heard = null;
$events->listen('smoke.ping', static function ($payload) use (&$heard) {
    $heard = $payload;
    return 'pong';
});
$results = $events->dispatch('smoke.ping', 'data');
check('event listener fires', $heard === 'data');
check('event returns listener values', $results === ['pong']);
check('no listeners for unknown event', $events->dispatch('smoke.none') === []);

// 7. Routing through the kernel
$home = $kernel->handle(new Request('GET', '/'));
check('GET / returns 200', $home->status() === 200);
check('GET / returns app status', str_contains($home->content(), '"status":"ok"'));

$up = $kernel->handle(new Request('GET', '/up'));
check('GET /up returns 200 OK', $up->status() === 200 && $up->content() === 'OK');

$missing = $kernel->handle(new Request('GET', '/nope'));
check('unknown path returns 404', $missing->status() === 404);

$wrongMethod = $kernel->handle(new Request('POST', '/up'));
check('wrong method returns 405', $wrongMethod->status() === 405);
check('405 includes Allow header', isset($wrongMethod->headers()['Allow']));

// 8. Route params
$router = $c->make(Router::class);
$router->get('/jobs/{id}', static fn (string $id): Response => Response::text("job:{$id}"));
$param = $kernel->handle(new Request('GET', '/jobs/01HZX'));
check('route param is captured', $param->content() === 'job:01HZX');

// 9. Named routes & URL generation
$route = $router->get('/teams/{team}/members', static fn (): Response => Response::text('ok'));
$router->name('team.members', $route);
check('URL generation from name', $router->url('team.members', ['team' => 'eng']) === '/teams/eng/members');

// 10. Health checker
$health = $c->make(HealthChecker::class);
$report = $health->run();
check('health checker has probes', $health->count() >= 2);
check('health overall is healthy', $report['status']->value === 'healthy');
check('php.version probe healthy', ($report['probes']['php.version']['status'] ?? '') === 'healthy');

// 11. Response helpers
check('Response::json sets content-type', (Response::json(['a' => 1])->headers()['Content-Type'] ?? '') === 'application/json; charset=UTF-8');

echo "\n";
echo $failed === 0
    ? "\033[32mALL {$passed} CHECKS PASSED\033[0m\n"
    : "\033[31m{$failed} FAILED\033[0m, {$passed} passed\n";

exit($failed === 0 ? 0 : 1);
