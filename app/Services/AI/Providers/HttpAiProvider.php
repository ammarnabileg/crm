<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Contracts\AI\AiProvider;
use App\Contracts\Http\HttpClient;
use App\Services\AI\AiPrompt;
use App\Services\AI\AiResult;
use Throwable;

/**
 * Base for real, HTTP-backed AI providers (docs/51 §12). It owns the parts every
 * adapter shares — pulling the tenant's API key from its decrypted credentials,
 * performing the call through the injected HttpClient, and mapping transport/HTTP
 * failures to a clean AiResult — so a concrete provider only declares its endpoint,
 * request body and response shape. Per the contract it NEVER throws: every failure
 * (missing key, 401/403 → invalid_api_key, 429 → rate_limited, 5xx → upstream_error,
 * connect/TLS → network_error, timeout → timeout) returns AiResult::failure() so the
 * AiGateway's fallback chain takes over. Credentials are never logged.
 */
abstract class HttpAiProvider implements AiProvider
{
    protected const JSON = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(
        protected readonly HttpClient $http,
        protected readonly int $timeout = 60,
    ) {
    }

    public function supports(string $capability): bool
    {
        return true;
    }

    public function complete(AiPrompt $prompt, array $credentials, array $options = []): AiResult
    {
        try {
            $apiKey = $this->apiKey($credentials);
            if ($apiKey === '') {
                return AiResult::failure('missing_api_key', $this->key());
            }
            $model = (string) ($options['model'] ?? $prompt->options['model'] ?? $this->defaultModel());

            [$url, $headers, $body] = $this->buildRequest($apiKey, $model, $prompt, $credentials);
            $res = $this->http->request('POST', $url, $headers, $body, $this->timeout);

            if ($res->transportError !== null) {
                $reason = $res->transportError === 'timeout' ? 'timeout' : 'network_error';

                return AiResult::failure($reason, $this->key(), $model);
            }
            if (! $res->ok()) {
                return AiResult::failure($this->mapStatus($res->status), $this->key(), $model);
            }

            return $this->parse($res->json(), $model);
        } catch (Throwable) {
            // Defensive: an adapter bug must never bubble up past the gateway.
            return AiResult::failure('provider_exception', $this->key());
        }
    }

    /** Extract the API key from the tenant's decrypted secrets (several common names). */
    protected function apiKey(array $credentials): string
    {
        return (string) ($credentials['api_key'] ?? $credentials['key'] ?? $credentials['secret'] ?? $credentials['token'] ?? '');
    }

    protected function defaultModel(): string
    {
        return '';
    }

    protected function mapStatus(int $status): string
    {
        return match (true) {
            $status === 401, $status === 403 => 'invalid_api_key',
            $status === 429                  => 'rate_limited',
            $status >= 500                   => 'upstream_error:' . $status,
            default                          => 'http_error:' . $status,
        };
    }

    /** @return array<int, array{role:string, content:string}> */
    protected function plainMessages(AiPrompt $prompt): array
    {
        return array_map(
            static fn (array $m): array => ['role' => (string) ($m['role'] ?? 'user'), 'content' => (string) ($m['content'] ?? '')],
            $prompt->messages
        );
    }

    protected function temperature(AiPrompt $prompt): ?float
    {
        return isset($prompt->options['temperature']) ? (float) $prompt->options['temperature'] : null;
    }

    protected function maxTokens(AiPrompt $prompt): ?int
    {
        return isset($prompt->options['max_tokens']) ? (int) $prompt->options['max_tokens'] : null;
    }

    /**
     * Build the HTTP request for this provider.
     *
     * @param array<string,mixed> $credentials
     * @return array{0:string, 1:array<int,string>, 2:string} [url, headerLines, jsonBody]
     */
    abstract protected function buildRequest(string $apiKey, string $model, AiPrompt $prompt, array $credentials): array;

    /** @param array<string,mixed> $json */
    abstract protected function parse(array $json, string $model): AiResult;
}
