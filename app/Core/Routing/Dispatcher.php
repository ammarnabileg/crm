<?php

declare(strict_types=1);

namespace HaHireAI\Core\Routing;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;

/**
 * Matches a Request against the Router's routes and executes the handler,
 * normalising the result to a Response. Produces 404/405 responses directly so
 * the kernel always receives a Response. See docs/ROUTING_GUIDE.md.
 */
final class Dispatcher
{
    public function __construct(private readonly Container $container)
    {
    }

    public function dispatch(Request $request, Router $router): Response
    {
        $allowed = [];

        foreach ($router->routes() as $route) {
            $params = $route->match($request->path());

            if ($params === null) {
                continue;
            }

            if (! $request->isMethod($route->method())) {
                $allowed[$route->method()] = true;

                continue;
            }

            return $this->run($route, $request, $params);
        }

        if ($allowed !== []) {
            return Response::text('Method Not Allowed', 405)
                ->withHeader('Allow', implode(', ', array_keys($allowed)));
        }

        return Response::text('Not Found', 404);
    }

    /** @param array<string, string> $params */
    private function run(Route $route, Request $request, array $params): Response
    {
        // Make the active request resolvable by type-hint, and route params by name.
        $this->container->instance(Request::class, $request);
        $overrides = [...$params, 'request' => $request];

        $handler = $this->resolveHandler($route->handler());
        $result = $this->container->call($handler, $overrides);

        return $this->normalize($result);
    }

    /** @return callable */
    private function resolveHandler(mixed $handler): callable
    {
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $handler = [$class, $method];
        }

        if (is_array($handler) && is_string($handler[0])) {
            $handler = [$this->container->make($handler[0]), $handler[1]];
        }

        if (! is_callable($handler)) {
            throw new \RuntimeException('Route handler is not callable.');
        }

        return $handler;
    }

    private function normalize(mixed $result): Response
    {
        return match (true) {
            $result instanceof Response => $result,
            is_array($result) => Response::json($result),
            is_string($result) => Response::html($result),
            $result === null => Response::make('', 204),
            default => Response::text((string) $result),
        };
    }
}
