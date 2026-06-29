<?php

declare(strict_types=1);

namespace HaHireAI\Core\Routing;

/**
 * A single route definition. Patterns use `{param}` placeholders, e.g.
 * `/jobs/{id}`. See docs/ROUTING_GUIDE.md.
 */
final class Route
{
    private ?string $name = null;

    /** @var list<string> */
    private array $middleware;

    /**
     * @param  Closure|array{0:class-string,1:string}|string  $handler
     * @param  list<string>  $middleware
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly mixed $handler,
        array $middleware = [],
    ) {
        $this->middleware = $middleware;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function handler(): mixed
    {
        return $this->handler;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** @return list<string> */
    public function middleware(): array
    {
        return $this->middleware;
    }

    /**
     * Match a path against this route's pattern.
     *
     * @return array<string, string>|null  captured params, or null on no match
     */
    public function match(string $path): ?array
    {
        $regex = $this->compile();

        if (preg_match($regex, $path, $matches) !== 1) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    private function compile(): string
    {
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $this->path,
        );

        return '#^' . $pattern . '$#';
    }
}
