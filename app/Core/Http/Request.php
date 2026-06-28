<?php

declare(strict_types=1);

namespace HaHireAI\Core\Http;

/**
 * An immutable-ish HTTP request abstraction. See docs/BOOTSTRAP_FLOW.md.
 */
final class Request
{
    /**
     * @param  array<string, string>  $query
     * @param  array<string, mixed>   $body
     * @param  array<string, string>  $headers  lower-cased header names
     * @param  array<string, mixed>   $server
     * @param  array<string, mixed>   $files    normalised $_FILES entries
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $headers = [],
        private readonly array $server = [],
        private readonly array $files = [],
    ) {
    }

    public static function capture(): self
    {
        $server = $_SERVER;
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($server['REQUEST_URI'] ?? '/');
        $path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');

        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr((string) $key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        return new self($method, $path === '//' ? '/' : $path, $_GET, $_POST, $headers, $server, $_FILES);
    }

    /**
     * A single uploaded file by field name, or null if absent/errored.
     *
     * @return array{name: string, type: string, tmp_name: string, error: int, size: int}|null
     */
    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;

        if (! is_array($f) || ! isset($f['tmp_name']) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        return [
            'name' => (string) ($f['name'] ?? ''),
            'type' => (string) ($f['type'] ?? 'application/octet-stream'),
            'tmp_name' => (string) $f['tmp_name'],
            'error' => (int) $f['error'],
            'size' => (int) ($f['size'] ?? 0),
        ];
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return [...$this->query, ...$this->body];
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }
}
