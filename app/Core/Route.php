<?php

declare(strict_types=1);

namespace App\Core;

/**
 * A single route definition. Compiles its URI template ("/users/{id}") into a
 * regular expression and extracts named parameters at match time.
 */
final class Route
{
    public ?string $name = null;

    /** @var string[] */
    public array $middleware = [];

    private string $regex;

    /** @var string[] */
    private array $parameterNames = [];

    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly mixed $action,
    ) {
        $this->compile();
    }

    private function compile(): void
    {
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?\}/',
            function (array $matches): string {
                $this->parameterNames[] = $matches[1];
                $optional = isset($matches[2]);
                // Optional params also swallow the preceding slash.
                return $optional ? '(?:/(?P<' . $matches[1] . '>[^/]+))?' : '(?P<' . $matches[1] . '>[^/]+)';
            },
            $this->uri
        ) ?? $this->uri;

        $pattern = str_replace('/(?:/', '(?:/', $pattern);

        $this->regex = '#^' . $pattern . '$#u';
    }

    /**
     * @return array<string, string>|null Matched parameters, or null on no match.
     */
    public function match(string $path): ?array
    {
        if (! preg_match($this->regex, $path, $matches)) {
            return null;
        }

        $params = [];
        foreach ($this->parameterNames as $name) {
            if (isset($matches[$name]) && $matches[$name] !== '') {
                $params[$name] = rawurldecode($matches[$name]);
            }
        }

        return $params;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string|string[] $middleware
     */
    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);

        return $this;
    }

    /**
     * Build a concrete URL from this route's template using the given params.
     */
    public function buildUrl(array $params): string
    {
        $uri = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?\}/',
            function (array $matches) use (&$params): string {
                $key = $matches[1];
                if (array_key_exists($key, $params)) {
                    $value = (string) $params[$key];
                    unset($params[$key]);
                    return rawurlencode($value);
                }
                return '';
            },
            $this->uri
        ) ?? $this->uri;

        $uri = '/' . trim(preg_replace('#/+#', '/', $uri) ?? $uri, '/');
        $query = http_build_query($params);

        return ($uri === '/' ? $uri : rtrim($uri, '/')) . ($query !== '' ? '?' . $query : '');
    }
}
