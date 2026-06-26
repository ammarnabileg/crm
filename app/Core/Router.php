<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use App\Core\Middleware\MiddlewareInterface;
use Closure;
use RuntimeException;

/**
 * HTTP router with route groups, named routes and a middleware pipeline.
 */
final class Router
{
    /** @var Route[] */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $named = [];

    /** @var array<string, class-string> */
    private array $aliases = [];

    /** @var array<string, mixed> */
    private array $groupStack = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }

    public function get(string $uri, mixed $action): Route
    {
        return $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, mixed $action): Route
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, mixed $action): Route
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function patch(string $uri, mixed $action): Route
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function delete(string $uri, mixed $action): Route
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * @param string[] $methods
     */
    public function match(array $methods, string $uri, mixed $action): Route
    {
        $route = null;
        foreach ($methods as $method) {
            $route = $this->addRoute(strtoupper($method), $uri, $action);
        }

        return $route ?? throw new RuntimeException('match() requires at least one method.');
    }

    public function group(array $attributes, Closure $callback): void
    {
        $previous = $this->groupStack;

        $this->groupStack = [
            'prefix'     => trim(($previous['prefix'] ?? '') . '/' . trim($attributes['prefix'] ?? '', '/'), '/'),
            'middleware' => array_merge($previous['middleware'] ?? [], (array) ($attributes['middleware'] ?? [])),
            'namespace'  => $attributes['namespace'] ?? ($previous['namespace'] ?? null),
            'name'       => ($previous['name'] ?? '') . ($attributes['name'] ?? ''),
        ];

        $callback($this);

        $this->groupStack = $previous;
    }

    private function addRoute(string $method, string $uri, mixed $action): Route
    {
        $prefix = $this->groupStack['prefix'] ?? '';
        $fullUri = '/' . trim($prefix . '/' . trim($uri, '/'), '/');
        $fullUri = $fullUri === '/' ? '/' : rtrim($fullUri, '/');

        if (is_string($action) && isset($this->groupStack['namespace'])) {
            $action = $this->groupStack['namespace'] . '\\' . $action;
        }

        $route = new Route($method, $fullUri, $action);
        $route->middleware($this->groupStack['middleware'] ?? []);

        $this->routes[] = $route;

        return $route;
    }

    public function name(string $name): self
    {
        $route = end($this->routes);
        if ($route instanceof Route) {
            $prefix = $this->groupStack['name'] ?? '';
            $route->name($prefix . $name);
            $this->named[$route->name] = $route;
        }

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $this->indexNamedRoutes();

        $path = $request->path();
        $method = $request->method();
        $methodMatchedButNotPath = false;

        foreach ($this->routes as $route) {
            $params = $route->match($path);
            if ($params === null) {
                continue;
            }

            if ($route->method !== $method) {
                $methodMatchedButNotPath = true;
                continue;
            }

            $request->setRouteParams($params);

            return $this->runThroughPipeline($route, $request);
        }

        if ($methodMatchedButNotPath) {
            throw new HttpException(405, 'Method Not Allowed');
        }

        throw new HttpException(404, 'Not Found');
    }

    private function runThroughPipeline(Route $route, Request $request): Response
    {
        $core = fn (Request $req): Response => $this->callAction($route->action, $req);

        $pipeline = array_reduce(
            array_reverse($route->middleware),
            function (Closure $next, string $middleware): Closure {
                return function (Request $request) use ($next, $middleware): Response {
                    $instance = $this->resolveMiddleware($middleware);

                    return $instance->handle($request, $next);
                };
            },
            $core
        );

        return $pipeline($request);
    }

    private function resolveMiddleware(string $middleware): MiddlewareInterface
    {
        // Allow "alias:argument" syntax, e.g. permission:users.view
        $argument = null;
        if (str_contains($middleware, ':')) {
            [$middleware, $argument] = explode(':', $middleware, 2);
        }

        $class = $this->aliases[$middleware] ?? $middleware;

        if (! class_exists($class)) {
            throw new RuntimeException("Middleware [{$class}] does not exist.");
        }

        $instance = $argument !== null ? new $class($argument) : new $class();

        if (! $instance instanceof MiddlewareInterface) {
            throw new RuntimeException("Middleware [{$class}] must implement MiddlewareInterface.");
        }

        return $instance;
    }

    private function callAction(mixed $action, Request $request): Response
    {
        if ($action instanceof Closure) {
            return $this->normalizeResponse($action($request, ...array_values($request->routeParams())));
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);
        } elseif (is_array($action)) {
            [$class, $method] = $action;
        } else {
            throw new RuntimeException('Unsupported route action.');
        }

        if (! class_exists($class)) {
            throw new RuntimeException("Controller [{$class}] not found.");
        }

        $controller = new $class();

        if (! method_exists($controller, $method)) {
            throw new RuntimeException("Method [{$method}] not found on [{$class}].");
        }

        $result = $controller->{$method}($request, ...array_values($request->routeParams()));

        return $this->normalizeResponse($result);
    }

    private function normalizeResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }

        return Response::make((string) $result);
    }

    public function url(string $name, array $params = []): string
    {
        $this->indexNamedRoutes();

        if (! isset($this->named[$name])) {
            throw new RuntimeException("Route [{$name}] is not defined.");
        }

        return rtrim(base_url(), '/') . $this->named[$name]->buildUrl($params);
    }

    private function indexNamedRoutes(): void
    {
        foreach ($this->routes as $route) {
            if ($route->name !== null) {
                $this->named[$route->name] = $route;
            }
        }
    }
}
