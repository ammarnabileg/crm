<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Core;

use HaHireAI\Core\Container\Container;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Routing\Dispatcher;
use HaHireAI\Core\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RoutingTest extends TestCase
{
    private function dispatch(Router $router, string $method, string $path): Response
    {
        $container = new Container();

        return (new Dispatcher($container))->dispatch(new Request($method, $path), $router);
    }

    public function test_matches_a_simple_route(): void
    {
        $router = new Router();
        $router->get('/up', static fn (): Response => Response::text('OK'));

        $response = $this->dispatch($router, 'GET', '/up');

        $this->assertSame(200, $response->status());
        $this->assertSame('OK', $response->content());
    }

    public function test_captures_route_parameters(): void
    {
        $router = new Router();
        $router->get('/jobs/{id}', static fn (string $id): Response => Response::text("job:{$id}"));

        $this->assertSame('job:42', $this->dispatch($router, 'GET', '/jobs/42')->content());
    }

    public function test_unknown_path_returns_404(): void
    {
        $router = new Router();

        $this->assertSame(404, $this->dispatch($router, 'GET', '/missing')->status());
    }

    public function test_wrong_method_returns_405_with_allow_header(): void
    {
        $router = new Router();
        $router->get('/up', static fn (): Response => Response::text('OK'));

        $response = $this->dispatch($router, 'POST', '/up');

        $this->assertSame(405, $response->status());
        $this->assertArrayHasKey('Allow', $response->headers());
    }

    public function test_groups_apply_prefix(): void
    {
        $router = new Router();
        $router->group(['prefix' => '/api/v1'], static function (Router $r): void {
            $r->get('/ping', static fn (): Response => Response::text('pong'));
        });

        $this->assertSame('pong', $this->dispatch($router, 'GET', '/api/v1/ping')->content());
    }

    public function test_named_route_url_generation(): void
    {
        $router = new Router();
        $route = $router->get('/teams/{team}/members', static fn (): Response => Response::text('ok'));
        $router->name('team.members', $route);

        $this->assertSame('/teams/eng/members', $router->url('team.members', ['team' => 'eng']));
    }

    public function test_json_handler_result_is_normalised(): void
    {
        $router = new Router();
        $router->get('/data', static fn (): array => ['a' => 1]);

        $response = $this->dispatch($router, 'GET', '/data');

        $this->assertSame('application/json; charset=UTF-8', $response->headers()['Content-Type']);
        $this->assertSame('{"a":1}', $response->content());
    }
}
