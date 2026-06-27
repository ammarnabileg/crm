<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Contracts\Http\HttpClient;
use App\Contracts\Http\HttpResponse;

/**
 * A scripted HttpClient for tests. Queue the responses each request should return
 * (or a transport error), and inspect the captured requests to assert that an
 * adapter built the right URL/headers/body — all without a network call.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var array<int, HttpResponse> */
    private array $queue = [];

    /** @var array<int, array{method:string,url:string,headers:array<int,string>,body:?string}> */
    public array $requests = [];

    public function pushResponse(int $status, string $body = ''): self
    {
        $this->queue[] = new HttpResponse($status, $body);

        return $this;
    }

    public function pushTransportError(string $error): self
    {
        $this->queue[] = new HttpResponse(0, '', $error);

        return $this;
    }

    public function lastRequest(): ?array
    {
        return $this->requests[count($this->requests) - 1] ?? null;
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 60): HttpResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        return array_shift($this->queue) ?? new HttpResponse(200, '{}');
    }
}
