<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP request abstraction over PHP superglobals.
 */
final class Request
{
    private array $query;
    private array $body;
    private array $server;
    private array $cookies;
    private array $files;
    private array $headers;
    private ?array $json = null;
    private array $routeParams = [];

    public function __construct(array $query, array $body, array $server, array $cookies, array $files)
    {
        $this->query = $query;
        $this->body = $body;
        $this->server = $server;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->headers = $this->extractHeaders($server);
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_COOKIE, $_FILES);
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        // Support method spoofing for HTML forms (<input name="_method" value="PUT">).
        if ($method === 'POST') {
            $spoofed = strtoupper((string) ($this->body['_method'] ?? ''));
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofed;
            }
        }

        return $method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);

        // Strip a base subdirectory if the app is not at the domain root.
        $scriptDir = str_replace('\\', '/', dirname($this->server['SCRIPT_NAME'] ?? ''));
        if ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function fullUrl(): string
    {
        return rtrim(base_url(), '/') . $this->uri();
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $body = $this->body();

        return $body[$key] ?? $this->query[$key] ?? $default;
    }

    public function body(): array
    {
        if ($this->isJson()) {
            return $this->jsonBody();
        }

        return $this->body;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body());
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $result[$key] = $all[$key];
            }
        }

        return $result;
    }

    public function has(string $key): bool
    {
        $all = $this->all();

        return array_key_exists($key, $all);
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);

        return $value !== null && $value !== '';
    }

    public function boolean(string $key): bool
    {
        return filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = (string) $this->header('authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    public function ip(): string
    {
        $remote = trim((string) ($this->server['REMOTE_ADDR'] ?? ''));

        // Forwarding headers (CF-Connecting-IP / X-Forwarded-For) are
        // client-spoofable, so they are honoured ONLY when the connecting peer is
        // a configured trusted proxy/CDN. Otherwise an attacker could rotate the
        // header to defeat rate limiting or an IP allow-list. Default: no trusted
        // proxies → always use REMOTE_ADDR.
        $trusted = (array) config('app.trusted_proxies', []);
        if ($remote !== '' && $trusted !== [] && in_array($remote, $trusted, true)) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $key) {
                if (! empty($this->server[$key])) {
                    $forwarded = trim(explode(',', (string) $this->server[$key])[0]);
                    if ($forwarded !== '') {
                        return $forwarded;
                    }
                }
            }
        }

        return $remote !== '' ? $remote : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function isJson(): bool
    {
        return str_contains((string) $this->header('content-type', ''), 'application/json');
    }

    public function wantsJson(): bool
    {
        $accept = (string) $this->header('accept', '');

        return $this->isJson()
            || $this->isAjax()
            || str_contains($accept, 'application/json');
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';
        if ($https !== '' && strtolower((string) $https) !== 'off') {
            return true;
        }

        return ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || (int) ($this->server['SERVER_PORT'] ?? 80) === 443;
    }

    private function jsonBody(): array
    {
        if ($this->json === null) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            $this->json = is_array($decoded) ? $decoded : [];
        }

        return $this->json;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    private function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $serverKey => $headerName) {
            if (isset($server[$serverKey])) {
                $headers[$headerName] = $server[$serverKey];
            }
        }

        return $headers;
    }
}
