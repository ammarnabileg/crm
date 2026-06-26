<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response value object. Buffers status, headers and body so middleware
 * can inspect/modify a response before it is sent to the client.
 */
final class Response
{
    private string $content = '';
    private int $status = 200;

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<int, array{0:string,1:string,2:array}> */
    private array $cookies = [];

    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->status = $status;
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
    }

    public static function make(string $content = '', int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers);
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $response = new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            $status,
            $headers
        );
        $response->header('Content-Type', 'application/json; charset=utf-8');

        return $response;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        $response = new self('', $status);
        $response->header('Location', $url);

        return $response;
    }

    public static function noContent(int $status = 204): self
    {
        return new self('', $status);
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function withCookie(string $name, string $value, array $options = []): self
    {
        $this->cookies[] = [$name, $value, $options];

        return $this;
    }

    public function send(): void
    {
        if (! headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }

            foreach ($this->cookies as [$name, $value, $options]) {
                setcookie($name, $value, $options);
            }
        }

        echo $this->content;
    }
}
