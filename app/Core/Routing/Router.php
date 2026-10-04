<?php

declare(strict_types=1);

namespace HaHireAI\Core\Routing;

use HaHireAI\Core\Routing\Exceptions\RouteNotFoundException;

/**
 * Route registry. Modules declare routes in their Routes/ dir; the global
 * /routes layer aggregates them here. No complex DSL — verbs, params, groups,
 * names. See docs/ROUTING_GUIDE.md.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $named = [];

    /** @var array{prefix: string, middleware: list<string>} */
    private array $group = ['prefix' => '', 'middleware' => []];

    public function get(string $path, mixed $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, mixed $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, mixed $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, mixed $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, mixed $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Group routes under a shared prefix and/or middleware.
     *
     * @param  array{prefix?: string, middleware?: list<string>}  $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $previous = $this->group;

        $this->group = [
            'prefix' => $previous['prefix'] . ($attributes['prefix'] ?? ''),
            'middleware' => [...$previous['middleware'], ...($attributes['middleware'] ?? [])],
        ];

        $callback($this);

        $this->group = $previous;
    }

    public function addRoute(string $method, string $path, mixed $handler): Route
    {
        $fullPath = $this->normalize($this->group['prefix'] . $path);
        $route = new Route($method, $fullPath, $handler, $this->group['middleware']);
        $this->routes[] = $route;

        return $route;
    }

    public function name(string $name, Route $route): void
    {
        $route->setName($name);
        $this->named[$name] = $route;
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * Generate a URL for a named route.
     *
     * @param  array<string, string|int>  $params
     */
    public function url(string $name, array $params = []): string
    {
        $route = $this->named[$name] ?? throw new RouteNotFoundException("No route named [{$name}].");
        $path = $route->path();

        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }

        return $path;
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
